<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerStoreHoursResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $operation = $this->resource;

        return [
            'is_open' => $operation->is_open,
            'paused_until' => $operation->paused_until?->copy()->utc()->toIso8601String(),
            'timezone' => $operation->timezone,
            'orders_per_hour_limit' => $operation->orders_per_hour_limit,
            'estimated_prep_time_minutes' => $operation->estimated_prep_time_minutes,
            'rush_extra_time_enabled' => $operation->rush_extra_time_enabled,
            'rush_extra_time_minutes' => $operation->rush_extra_time_minutes,
            'weekly_schedule' => $operation->weekly_schedule,
        ];
    }
}
