<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\CustomerApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.options', 'deliveryAddress', 'statusLogs', 'couponUsages.couponConfig', 'coupon'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, Order $order): OrderResource
    {
        $order = $request->user()
            ->orders()
            ->with(['items.options', 'deliveryAddress', 'statusLogs', 'couponUsages.couponConfig', 'coupon'])
            ->whereKey($order->id)
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function track(Request $request, Order $order): JsonResponse
    {
        $order = $request->user()
            ->orders()
            ->with('statusLogs')
            ->whereKey($order->id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => (string) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->order_status,
                'status_label_ar' => $order->statusLabelAr(),
                'status_label_en' => $order->statusLabelEn(),
                'can_cancel' => $order->canBeCancelled(),
                'delivery_mode' => $order->delivery_mode,
                'scheduled_start_at' => $order->scheduled_start_at?->toIso8601String(),
                'scheduled_end_at' => $order->scheduled_end_at?->toIso8601String(),
                'payment_status' => $order->payment_status,
                'estimated_prep_time_minutes' => $order->estimated_prep_time_minutes,
                'dispatched_at' => $order->dispatched_at?->toIso8601String(),
                'delivered_at' => $order->delivered_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
                'status_timeline' => $order->statusLogs->map(fn ($log) => [
                    'status' => $log->to_status,
                    'status_label_ar' => $log->statusLabelAr(),
                    'status_label_en' => $log->statusLabelEn(),
                    'notes' => $log->notes,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function cancel(Request $request, Order $order): OrderResource
    {
        $order = DB::transaction(function () use ($request, $order): Order {
            $ownedOrder = $request->user()
                ->orders()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $ownedOrder->canBeCancelled()) {
                throw new CustomerApiException(
                    'This order can no longer be cancelled.',
                    'ORDER_CANNOT_BE_CANCELLED',
                    409,
                );
            }

            $fromStatus = $ownedOrder->order_status;
            $ownedOrder->update(['order_status' => 'cancelled']);
            CouponRedemption::query()->where('order_id', $ownedOrder->id)->delete();
            $this->releaseOfferRedemptions($ownedOrder);
            OrderStatusLog::create([
                'order_id' => $ownedOrder->id,
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'notes' => 'Cancelled by customer before preparation.',
            ]);

            return $ownedOrder->load(['items.options', 'deliveryAddress', 'statusLogs']);
        });

        return new OrderResource($order);
    }

    private function releaseOfferRedemptions(Order $order): void
    {
        $usages = $order->couponUsages()->with('couponConfig')->get();
        foreach ($usages as $usage) {
            if ($usage->couponConfig && $usage->couponConfig->total_used_count > 0) {
                $usage->couponConfig->decrement('total_used_count');
            }
        }
        $order->couponUsages()->delete();
        $order->offerRedemptionLogs()->delete();
    }
}
