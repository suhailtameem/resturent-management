<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customerId = (int) $request->user()->getAuthIdentifier();
        $notifications = Notification::query()
            ->where('type', 'push')
            ->whereHas('recipients', function (Builder $query) use ($customerId): void {
                $query->where('user_id', $customerId);
            })
            ->withCount([
                'recipients as opened_count' => function (Builder $query) use ($customerId): void {
                    $query->where('user_id', $customerId)->whereNotNull('opened_at');
                },
            ])
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => $notifications->getCollection()->map(
                fn (Notification $notification): array => [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'body' => $notification->message,
                    'destination' => $notification->destination,
                    'coupon_code' => $notification->coupon_code,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'is_read' => (int) $notification->opened_count > 0,
                ],
            )->values(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }
}
