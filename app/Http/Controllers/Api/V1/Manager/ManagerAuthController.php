<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class ManagerAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $staffUser = StaffUser::query()
            ->where('username', $validated['identifier'])
            ->orWhere('email', $validated['identifier'])
            ->first();

        if (! $staffUser || ! Hash::check($validated['password'], $staffUser->password)) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
                'code' => 'INVALID_CREDENTIALS',
                'errors' => (object) [],
            ], 401);
        }

        if (! $staffUser->is_active) {
            return response()->json([
                'message' => 'This staff account is inactive.',
                'code' => 'STAFF_ACCOUNT_INACTIVE',
                'errors' => (object) [],
            ], 403);
        }

        return response()->json([
            'data' => [
                'access_token' => $staffUser->createToken('manager-app')->plainTextToken,
                'token_type' => 'Bearer',
                'staff' => [
                    'id' => (string) $staffUser->id,
                    'username' => $staffUser->username,
                    'email' => $staffUser->email,
                    'full_name' => $staffUser->full_name,
                    'phone' => $staffUser->phone,
                    'role' => $staffUser->role,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return response()->json([], 204);
    }
}
