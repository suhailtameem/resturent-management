<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerAddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('addresses');

        return response()->json([
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->full_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'preferred_language' => $user->preferred_language,
                'is_vip' => (bool) $user->is_vip,
                'saved_addresses' => CustomerAddressResource::collection($user->addresses),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'preferred_language' => ['sometimes', 'in:ar,en'],
        ]);

        if (array_key_exists('full_name', $validated)) {
            $validated['name'] = $validated['full_name'] ?? 'Customer';
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'data' => [
                'id' => (string) $user->id,
                'name' => $user->full_name,
                'phone' => $user->phone,
                'email' => $user->email,
                'preferred_language' => $user->preferred_language,
            ],
        ]);
    }
}
