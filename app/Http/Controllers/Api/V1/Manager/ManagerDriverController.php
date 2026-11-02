<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Manager\StoreManagerDriverRequest;
use App\Http\Requests\Api\V1\Manager\UpdateManagerDriverRequest;
use App\Http\Resources\ManagerDriverResource;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ManagerDriverController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $drivers = StaffUser::query()
            ->where('role', 'courier')
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('full_name')
            ->get();

        return ManagerDriverResource::collection($drivers);
    }

    public function store(StoreManagerDriverRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        unset($attributes['password_confirmation']);

        $driver = StaffUser::create([
            ...$attributes,
            'role' => 'courier',
            'is_active' => true,
        ]);

        return (new ManagerDriverResource($driver))->response()->setStatusCode(201);
    }

    public function update(UpdateManagerDriverRequest $request, StaffUser $driver): ManagerDriverResource
    {
        abort_unless($driver->role === 'courier', 404);

        $attributes = $request->validated();
        $shouldRevokeTokens = array_key_exists('is_active', $attributes)
            && ! filter_var($attributes['is_active'], FILTER_VALIDATE_BOOLEAN);
        unset($attributes['password_confirmation']);

        if (($attributes['password'] ?? null) === null) {
            unset($attributes['password']);
        }

        $driver->update($attributes);

        if ($shouldRevokeTokens) {
            $driver->tokens()->delete();
        }

        return new ManagerDriverResource($driver->refresh());
    }

    public function destroy(StaffUser $driver): JsonResponse
    {
        abort_unless($driver->role === 'courier', 404);

        $driver->update(['is_active' => false]);
        $driver->tokens()->delete();

        return response()->json(['message' => 'Courier deactivated successfully.']);
    }
}
