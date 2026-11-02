<?php

namespace App\Jobs;

use App\Models\NotificationRecipient;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $recipientId) {}

    public function handle(FirebaseNotificationService $firebase): void
    {
        $recipient = NotificationRecipient::query()->with(['notification', 'deviceToken'])->find($this->recipientId);
        if (! $recipient || ! $this->claim($recipient)) {
            return;
        }
        if (! $recipient->deviceToken) {
            $this->finish($recipient, 'failed', null, 'Device token no longer exists.');

            return;
        }
        try {
            $n = $recipient->notification;
            $result = $firebase->send($recipient->deviceToken, array_filter([
                'notification_id' => (string) $n->id, 'destination' => $n->destination, 'coupon_code' => $n->coupon_code,
            ], static fn (?string $v): bool => $v !== null), $n->title, $n->message);
            $status = $result['invalid'] || $result['error'] !== null ? 'failed' : 'sent';
            $this->finish($recipient, $status, $result['message_id'], $result['error']);
        } catch (Throwable $exception) {
            if ($this->attempts() < $this->tries) {
                NotificationRecipient::query()->whereKey($recipient->id)->where('status', 'sending')->update(['status' => 'queued']);
                throw $exception;
            }
            Log::error('Firebase notification delivery exhausted retries.', [
                'recipient_id' => $recipient->id,
                'exception' => $exception::class,
                'message' => substr($exception->getMessage(), 0, 500),
            ]);
            $this->finish($recipient, 'failed', null, 'Firebase send failed after retries: '.substr($exception->getMessage(), 0, 400));
        }
    }

    private function claim(NotificationRecipient $recipient): bool
    {
        return DB::transaction(function () use ($recipient): bool {
            $locked = NotificationRecipient::query()->lockForUpdate()->find($recipient->id);
            if (! $locked || $locked->status !== 'queued') {
                return false;
            }
            $locked->update(['status' => 'sending']);

            return true;
        });
    }

    private function finish(NotificationRecipient $recipient, string $status, ?string $messageId, ?string $error): void
    {
        DB::transaction(function () use ($recipient, $status, $messageId, $error): void {
            $locked = NotificationRecipient::query()->lockForUpdate()->find($recipient->id);
            if (! $locked || ! in_array($locked->status, ['sending', 'queued'], true)) {
                return;
            }
            $locked->update(['status' => $status, 'fcm_message_id' => $messageId, 'error_message' => $error, 'sent_at' => now()]);
            $locked->notification()->increment($status === 'sent' ? 'successful_sends' : 'failed_sends');
        });
    }
}
