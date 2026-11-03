<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\CustomerApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAddressResource;
use App\Models\CustomerRefreshToken;
use App\Models\User;
use App\Support\SaudiPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerAuthController extends Controller
{
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(?:\+?966|0)?5[0-9]{8}$/'],
        ]);

        $phone = SaudiPhone::normalize($validated['phone']);

        if (! config('auth.customer_otp_bypass')) {
            throw new CustomerApiException(
                'OTP delivery is not configured.',
                'OTP_DELIVERY_UNAVAILABLE',
                503,
            );
        }

        $retryKey = 'otp_retry:'.$phone;
        $remaining = Cache::get($retryKey, 0);
        $cooldownKey = 'otp_next_send:'.$phone;

        if ($remaining >= 3) {
            throw new CustomerApiException(
                'Too many OTP attempts. Please try again later.',
                'OTP_RATE_LIMITED',
                429,
                ['phone' => ['Too many OTP attempts. Please try again later.']],
            );
        }

        if (Cache::has($cooldownKey)) {
            throw new CustomerApiException(
                'Please wait before requesting another verification code.',
                'OTP_RATE_LIMITED',
                429,
                ['phone' => ['Please wait before requesting another verification code.']],
            );
        }

        Cache::put($retryKey, $remaining + 1, now()->addMinutes(10));
        Cache::put($cooldownKey, true, now()->addSeconds(60));

        return response()->json([
            'data' => [
                'message' => 'Verification code sent successfully.',
                'retry_in_seconds' => 60,
            ],
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        if (! $request->has('code') && $request->has('otp')) {
            $request->merge(['code' => $request->input('otp')]);
        }

        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^(?:\+?966|0)?5[0-9]{8}$/'],
            'code' => ['required', 'string', 'digits:4'],
        ]);

        $phone = SaudiPhone::normalize($validated['phone']);
        if (! config('auth.customer_otp_bypass')) {
            $storedOtp = Cache::get('otp:'.$phone);
            $attempts = $storedOtp ? Cache::increment('otp_attempts:'.$phone) : 0;

            if (! $storedOtp || $attempts > 5 || ! hash_equals((string) $storedOtp, $validated['code'])) {
                if ($attempts >= 5) {
                    Cache::forget('otp:'.$phone);
                    Cache::forget('otp_attempts:'.$phone);
                }

                throw new CustomerApiException(
                    'The provided verification code is invalid or expired.',
                    'INVALID_OTP',
                    422,
                    ['code' => ['The provided verification code is invalid or expired.']],
                );
            }
        }

        $user = User::query()->whereIn('phone', SaudiPhone::equivalentFormats($phone))->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Customer',
                'email' => null,
                'password' => Str::random(40),
                'phone' => $phone,
                'full_name' => 'Customer',
                'preferred_language' => 'ar',
                'is_vip' => false,
                'status' => 'active',
                'phone_verified_at' => now(),
            ]);
        } else {
            $phoneChanged = $user->phone !== $phone;
            $user->update([
                'phone' => $phone,
                'phone_verified_at' => now(),
                'manager_verified_at' => $phoneChanged ? null : $user->manager_verified_at,
                'manager_verified_by_staff_user_id' => $phoneChanged
                    ? null
                    : $user->manager_verified_by_staff_user_id,
            ]);
        }

        Cache::forget('otp:'.$phone);
        Cache::forget('otp_attempts:'.$phone);
        Cache::forget('otp_retry:'.$phone);
        Cache::forget('otp_next_send:'.$phone);

        if ($user->status !== 'active') {
            throw new CustomerApiException('This customer account is blocked.', 'CUSTOMER_BLOCKED', 403);
        }

        return response()->json(['data' => $this->issueSession($user)]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string', 'size:64'],
        ]);

        $session = DB::transaction(function () use ($validated): array {
            $refreshToken = CustomerRefreshToken::query()
                ->where('token_hash', hash('sha256', $validated['refresh_token']))
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (! $refreshToken || $refreshToken->expires_at->isPast()) {
                throw new CustomerApiException('The saved session has expired. Please sign in again.', 'SESSION_EXPIRED', 401);
            }

            $user = User::query()->with('addresses')->findOrFail($refreshToken->user_id);
            if ($user->status !== 'active') {
                throw new CustomerApiException('This customer account is blocked.', 'CUSTOMER_BLOCKED', 403);
            }

            $refreshToken->update(['revoked_at' => now(), 'last_used_at' => now()]);
            if ($refreshToken->access_token_id) {
                PersonalAccessToken::query()->whereKey($refreshToken->access_token_id)->delete();
            }

            return $this->issueSession($user);
        });

        return response()->json(['data' => $session]);
    }

    public function renew(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->status !== 'active') {
            throw new CustomerApiException('This customer account is blocked.', 'CUSTOMER_BLOCKED', 403);
        }

        $currentAccessToken = $user->currentAccessToken();
        if ($currentAccessToken instanceof PersonalAccessToken) {
            DB::transaction(function () use ($currentAccessToken): void {
                CustomerRefreshToken::query()
                    ->where('access_token_id', $currentAccessToken->id)
                    ->whereNull('revoked_at')
                    ->update(['revoked_at' => now()]);
                $currentAccessToken->delete();
            });
        }

        return response()->json(['data' => $this->issueSession($user)]);
    }

    public function logout(Request $request): Response
    {
        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken) {
            CustomerRefreshToken::query()
                ->where('access_token_id', $accessToken->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            $accessToken->delete();
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function issueSession(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $plainRefreshToken = bin2hex(random_bytes(32));
            $expiresAt = now()->addDays(90);
            $accessToken = $user->createToken('customer-token');
            CustomerRefreshToken::create([
                'user_id' => $user->id,
                'access_token_id' => $accessToken->accessToken->id,
                'token_hash' => hash('sha256', $plainRefreshToken),
                'expires_at' => $expiresAt,
            ]);

            return [
                'access_token' => $accessToken->plainTextToken,
                'refresh_token' => $plainRefreshToken,
                'refresh_expires_at' => $expiresAt->toIso8601String(),
                'token_type' => 'Bearer',
                'customer' => [
                    'id' => (string) $user->id,
                    'name' => $user->full_name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'saved_addresses' => CustomerAddressResource::collection($user->addresses),
                ],
            ];
        });
    }
}
