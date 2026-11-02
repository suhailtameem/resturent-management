<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStoreHoursRequest;
use App\Http\Resources\ManagerStoreHoursResource;
use App\Models\StoreOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerStoreOperationController extends Controller
{
    public function show(): ManagerStoreHoursResource
    {
        return new ManagerStoreHoursResource(StoreOperation::current()->refreshExpiredPause()->fresh());
    }

    public function update(SaveStoreHoursRequest $request): ManagerStoreHoursResource
    {
        $days = array_flip(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday']);
        $schedule = collect($request->validated('weekly_schedule'))
            ->sortBy(fn (array $day): int => $days[$day['day_of_week']])
            ->values()
            ->all();

        $operation = StoreOperation::current();
        $operation->update([
            ...$request->safe()->except('weekly_schedule'),
            'weekly_schedule' => $schedule,
        ]);

        return new ManagerStoreHoursResource($operation->fresh());
    }

    public function pause(Request $request): ManagerStoreHoursResource
    {
        $validated = $request->validate([
            'duration_minutes' => ['sometimes', 'required', 'integer', Rule::in([30, 60])],
            'until_tomorrow' => ['sometimes', 'required', 'boolean'],
        ]);
        $hasDuration = array_key_exists('duration_minutes', $validated);
        $hasUntilTomorrow = array_key_exists('until_tomorrow', $validated);
        $isUntilTomorrow = ($validated['until_tomorrow'] ?? false) === true
            || ($validated['until_tomorrow'] ?? null) === 1
            || ($validated['until_tomorrow'] ?? null) === '1';

        if ($hasDuration === $hasUntilTomorrow || ($hasUntilTomorrow && ! $isUntilTomorrow)) {
            throw ValidationException::withMessages([
                'until_tomorrow' => ['Provide either duration_minutes (30 or 60) or until_tomorrow=true.'],
            ]);
        }

        $operation = StoreOperation::current();
        $pausedUntil = $isUntilTomorrow
            ? now($operation->timezone)->addDay()->startOfDay()->utc()
            : Carbon::now('UTC')->addMinutes($validated['duration_minutes']);

        $operation->update([
            'is_open' => false,
            'paused_until' => $pausedUntil,
        ]);

        return new ManagerStoreHoursResource($operation->fresh());
    }

    public function close(): ManagerStoreHoursResource
    {
        $operation = StoreOperation::current();
        $operation->update([
            'is_open' => false,
            'paused_until' => null,
        ]);

        return new ManagerStoreHoursResource($operation->fresh());
    }

    public function resume(): ManagerStoreHoursResource
    {
        $operation = StoreOperation::current();
        $operation->update([
            'is_open' => true,
            'paused_until' => null,
        ]);

        return new ManagerStoreHoursResource($operation->fresh());
    }
}
