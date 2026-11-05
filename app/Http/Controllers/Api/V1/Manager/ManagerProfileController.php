<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Manager\UpdateManagerPasswordRequest;
use App\Http\Requests\Api\V1\Manager\UpdateManagerProfileRequest;
use App\Http\Resources\ManagerProfileResource;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManagerProfileController extends Controller
{
    public function show(Request $request): ManagerProfileResource
    {
        /** @var StaffUser $staffUser */
        $staffUser = $request->user('staff_api');

        return new ManagerProfileResource($staffUser);
    }

    public function update(UpdateManagerProfileRequest $request): ManagerProfileResource
    {
        /** @var StaffUser $staffUser */
        $staffUser = $request->user('staff_api');
        $staffUser->update($request->validated());

        return new ManagerProfileResource($staffUser->refresh());
    }

    public function updatePassword(UpdateManagerPasswordRequest $request): JsonResponse
    {
        /** @var StaffUser $staffUser */
        $staffUser = $request->user('staff_api');
        $staffUser->update(['password' => $request->validated('password')]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
