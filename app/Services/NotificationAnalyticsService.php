<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;

class NotificationAnalyticsService
{
    public function reach(): array
    {
        return [
            'customers' => User::query()->where('status', 'active')->whereHas('deviceTokens', fn ($q) => $q->whereNull('staff_user_id'))->count(),
            'devices' => DeviceToken::query()->whereNotNull('user_id')->whereNull('staff_user_id')->count(),
        ];
    }

    public function open(int $notificationId, int $userId): void
    {
        $notification = Notification::query()->findOrFail($notificationId);
        $recipients = $notification->recipients()->where('user_id', $userId);
        abort_unless($recipients->exists(), 404);
        $recipients->whereNull('opened_at')->update(['opened_at' => now()]);
    }
}
