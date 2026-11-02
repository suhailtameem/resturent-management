<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\StaffUser;
use App\Services\NotificationAnalyticsService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerNotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications, private readonly NotificationAnalyticsService $analytics) {}

    public function reach(): JsonResponse
    {
        return response()->json(['data' => $this->analytics->reach()]);
    }

    public function index(): JsonResponse
    {
        $items = Notification::query()->where('type', 'push')->withCount(['recipients as opened_count' => fn ($q) => $q->whereNotNull('opened_at')])->latest()->paginate(20);

        return response()->json(['data' => $items->getCollection()->map(fn (Notification $n): array => $this->summary($n)), 'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'total' => $items->total()]]);
    }

    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['sometimes', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:notifications,id'],
        ]);

        $query = Notification::query()->where('type', 'push');
        if (isset($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        $deletedCount = DB::transaction(fn (): int => $query->delete());

        return response()->json(['data' => ['deleted_count' => $deletedCount]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:1000'],
            'destination' => ['nullable', 'string', 'max:255', 'in:/home,/orders'],
            'coupon_code' => ['nullable', 'string', 'max:100'], 'send_to_all' => ['sometimes', 'boolean'],
            'customer_ids' => ['required_unless:send_to_all,true', 'array', 'min:1', 'max:5000'],
            'customer_ids.*' => ['integer', 'distinct', 'exists:users,id'], 'idempotency_key' => ['required', 'string', 'max:80'],
        ]);
        /** @var StaffUser $manager */
        $manager = $request->user('staff_api');
        $notification = $this->notifications->send($data, $manager);

        return response()->json(['data' => $this->result($notification)], 202);
    }

    public function show(Notification $notification): JsonResponse
    {
        abort_unless($notification->type === 'push', 404);
        $notification->load(['recipients.user:id,full_name,name,phone', 'recipients.deviceToken:id,platform']);

        return response()->json(['data' => [...$this->summary($notification), 'recipients' => $notification->recipients->map(fn (NotificationRecipient $r): array => [
            'customer_id' => $r->user_id, 'customer_name' => $r->user?->full_name ?: $r->user?->name,
            'device_platform' => $r->deviceToken?->platform, 'status' => $r->status, 'error_message' => $r->error_message,
            'sent_at' => $r->sent_at?->toIso8601String(), 'opened_at' => $r->opened_at?->toIso8601String(),
        ])]]);
    }

    private function summary(Notification $n): array
    {
        return ['id' => $n->id, 'title' => $n->title, 'body' => $n->message, 'destination' => $n->destination,
            'coupon_code' => $n->coupon_code, 'created_at' => $n->created_at?->toIso8601String(),
            'total_recipients' => $n->total_recipients, 'successful_sends' => $n->successful_sends,
            'failed_sends' => $n->failed_sends, 'opened_count' => $n->opened_count ?? $n->recipients()->whereNotNull('opened_at')->count()];
    }

    private function result(Notification $n): array
    {
        return [...$this->summary($n), 'targeted_devices' => $n->recipients()->whereNotNull('device_token_id')->count(),
            'customers_without_devices' => max(0, $n->total_recipients - $n->recipients()->distinct('user_id')->count('user_id'))];
    }
}
