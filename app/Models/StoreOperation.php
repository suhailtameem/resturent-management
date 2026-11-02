<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StoreOperation extends Model
{
    protected $fillable = [
        'is_open',
        'paused_until',
        'timezone',
        'orders_per_hour_limit',
        'estimated_prep_time_minutes',
        'rush_extra_time_enabled',
        'rush_extra_time_minutes',
        'weekly_schedule',
    ];

    protected $casts = [
        'is_open' => 'boolean',
        'paused_until' => 'datetime',
        'orders_per_hour_limit' => 'integer',
        'estimated_prep_time_minutes' => 'integer',
        'rush_extra_time_enabled' => 'boolean',
        'rush_extra_time_minutes' => 'integer',
        'weekly_schedule' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'is_open' => true,
            'timezone' => 'Asia/Riyadh',
            'orders_per_hour_limit' => 18,
            'estimated_prep_time_minutes' => 30,
            'rush_extra_time_enabled' => false,
            'rush_extra_time_minutes' => 15,
            'weekly_schedule' => static::defaultWeeklySchedule(),
        ]);
    }

    /**
     * @return array<int, array{day_of_week: string, is_active: bool, sessions: array<int, mixed>}>
     */
    public static function defaultWeeklySchedule(): array
    {
        return collect(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])
            ->map(fn (string $day): array => [
                'day_of_week' => $day,
                'is_active' => false,
                'sessions' => [],
            ])
            ->all();
    }

    public function refreshExpiredPause(): self
    {
        if ($this->paused_until !== null && $this->paused_until->isPast()) {
            $this->forceFill([
                'is_open' => true,
                'paused_until' => null,
            ])->save();
        }

        return $this->refresh();
    }

    public function acceptsOrders(): bool
    {
        return $this->refreshExpiredPause()->is_open;
    }

    public function estimatedPrepTime(): int
    {
        return $this->estimated_prep_time_minutes
            + ($this->rush_extra_time_enabled ? $this->rush_extra_time_minutes : 0);
    }

    public function scopePausedAndExpired(Builder $query): Builder
    {
        return $query->whereNotNull('paused_until')->where('paused_until', '<=', now());
    }
}
