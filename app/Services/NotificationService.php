<?php

namespace App\Services;

use App\Jobs\SendNotificationRecipient;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /** @param list<int> $customerIds */
    public function send(array $data, StaffUser $manager): Notification
    {
        $customers = User::query()->where('status', 'active')
            ->when(! ($data['send_to_all'] ?? false), fn ($q) => $q->whereIn('id', $data['customer_ids']))
            ->with(['deviceTokens' => fn ($q) => $q->whereNull('staff_user_id')])->get();

        return DB::transaction(function () use ($data, $manager, $customers): Notification {
            if (! empty($data['idempotency_key'])) {
                $existing = Notification::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    return $existing;
                }
            }
            $notification = Notification::query()->create([
                'title' => $data['title'], 'message' => $data['body'], 'type' => 'push',
                'destination' => $data['destination'] ?? null, 'coupon_code' => $data['coupon_code'] ?? null,
                'total_recipients' => $customers->count(), 'created_by' => $manager->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);
            foreach ($customers as $customer) {
                if ($customer->deviceTokens->isEmpty()) {
                    NotificationRecipient::query()->create([
                        'notification_id' => $notification->id,
                        'user_id' => $customer->id,
                        'device_token_id' => null,
                        'status' => 'inbox',
                    ]);

                    continue;
                }

                foreach ($customer->deviceTokens as $device) {
                    $recipient = NotificationRecipient::query()->create([
                        'notification_id' => $notification->id, 'user_id' => $customer->id,
                        'device_token_id' => $device->id, 'status' => 'queued',
                    ]);
                    dispatch(new SendNotificationRecipient($recipient->id))->afterCommit();
                }
            }

            return $notification;
        });
    }
}
