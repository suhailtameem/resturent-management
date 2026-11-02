<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $owner = match (true) {
            $user instanceof StaffUser => ['user_id' => null, 'staff_user_id' => $user->getAuthIdentifier()],
            $user instanceof User => ['user_id' => $user->getAuthIdentifier(), 'staff_user_id' => null],
            default => throw new \LogicException('Unsupported device token owner.'),
        };

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [...$owner, 'platform' => $validated['platform']],
        );

        return response()->json(['message' => 'Device token registered.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);
        $user = $request->user();

        $query = DeviceToken::query()->where('token', $validated['token']);
        if ($user instanceof StaffUser) {
            $query->where('staff_user_id', $user->getAuthIdentifier());
        } elseif ($user instanceof User) {
            $query->where('user_id', $user->getAuthIdentifier());
        } else {
            throw new \LogicException('Unsupported device token owner.');
        }

        $query->delete();

        return response()->json(['message' => 'Device token removed.']);
    }
}
