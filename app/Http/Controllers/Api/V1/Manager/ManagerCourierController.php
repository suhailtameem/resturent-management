<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Manager\ReportCourierDeliveryIssueRequest;
use App\Http\Requests\Api\V1\Manager\UpdateCourierAvailabilityRequest;
use App\Http\Resources\ManagerOrderDetailResource;
use App\Http\Resources\ManagerOrderSummaryResource;
use App\Models\CourierCashCollection;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerCourierController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $courier = $this->courier($request);
        $activeStatuses = ['ready', 'ready_for_pickup', 'out_for_delivery'];
        $orders = $courier->assignedOrders()
            ->where('delivery_mode', 'delivery')
            ->whereIn('order_status', $activeStatuses)
            ->with(['items.options', 'deliveryAddress', 'courier', 'user', 'coupon', 'statusLogs'])
            ->orderBy('created_at')
            ->get();
        $completedOrders = $courier->assignedOrders()
            ->whereIn('order_status', ['delivered', 'completed'])
            ->whereDate('delivered_at', today())
            ->with(['items.options', 'deliveryAddress', 'courier', 'user', 'coupon', 'statusLogs'])
            ->latest('delivered_at')
            ->get();
        $pendingCashCollections = $courier->cashCollections()
            ->whereNull('settlement_id')
            ->with('order:id,order_number')
            ->latest('collected_at')
            ->get();

        return response()->json([
            'data' => [
                'is_available' => (bool) $courier->is_available,
                'stats' => [
                    'active_orders_count' => $orders->count(),
                    'pending_cash_amount' => number_format((float) $courier->cashCollections()
                        ->whereNull('settlement_id')
                        ->sum('amount'), 2, '.', ''),
                    'completed_today_count' => $completedOrders->count(),
                ],
                'orders' => $orders->map(function (Order $order) use ($request): array {
                    return $this->courierOrderDetails($order, $request);
                })->values(),
                'completed_orders' => $completedOrders->map(function (Order $order) use ($request): array {
                    return $this->courierOrderDetails($order, $request);
                })->values(),
                'pending_cash_collections' => $pendingCashCollections->map(fn (CourierCashCollection $collection): array => [
                    'id' => (int) $collection->id,
                    'order_id' => (int) $collection->order_id,
                    'order_number' => $collection->order?->order_number,
                    'amount' => $collection->amount,
                    'collected_at' => $collection->collected_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function updateAvailability(UpdateCourierAvailabilityRequest $request): JsonResponse
    {
        $courier = $this->courier($request);
        $courier->update($request->validated());

        return response()->json([
            'data' => ['is_available' => (bool) $courier->is_available],
        ]);
    }

    public function pickUp(Request $request, Order $order): JsonResponse
    {
        $courier = $this->courier($request);
        $updatedOrder = DB::transaction(function () use ($courier, $order): Order {
            $lockedOrder = Order::query()
                ->whereBelongsTo($courier, 'courier')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->order_status === 'out_for_delivery') {
                return $lockedOrder;
            }

            if ($lockedOrder->delivery_mode !== 'delivery' || ! in_array($lockedOrder->order_status, ['ready', 'ready_for_pickup'], true)) {
                abort(409, 'This order is not ready for courier pickup.');
            }

            $fromStatus = $lockedOrder->order_status;
            $lockedOrder->update([
                'order_status' => 'out_for_delivery',
                'dispatched_at' => now(),
            ]);
            $this->recordStatusChange($lockedOrder, $courier, $fromStatus, 'out_for_delivery', 'استلم المندوب الطلب من الفرع');

            return $lockedOrder;
        });

        $this->broadcastStatus($updatedOrder);

        return $this->mutationResponse($updatedOrder, 'تم تسجيل استلام الطلب من الفرع.');
    }

    public function deliver(Request $request, Order $order): JsonResponse
    {
        $courier = $this->courier($request);
        $updatedOrder = DB::transaction(function () use ($courier, $order): Order {
            $lockedOrder = Order::query()
                ->whereBelongsTo($courier, 'courier')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (in_array($lockedOrder->order_status, ['delivered', 'completed'], true)) {
                return $lockedOrder;
            }

            if ($lockedOrder->order_status !== 'out_for_delivery') {
                abort(409, 'This order is not currently out for delivery.');
            }

            $fromStatus = $lockedOrder->order_status;
            $lockedOrder->update([
                'order_status' => 'delivered',
                'delivered_at' => now(),
                ...($lockedOrder->payment_method === 'cod' ? ['payment_status' => 'paid'] : []),
            ]);

            if ($lockedOrder->payment_method === 'cod') {
                CourierCashCollection::query()->firstOrCreate(
                    ['order_id' => $lockedOrder->id],
                    [
                        'courier_id' => $courier->id,
                        'amount' => $lockedOrder->total_final_amount,
                        'collected_at' => now(),
                    ],
                );
            }

            $this->recordStatusChange($lockedOrder, $courier, $fromStatus, 'delivered', 'أكد المندوب تسليم الطلب للعميل');

            return $lockedOrder;
        });

        $this->broadcastStatus($updatedOrder);

        return $this->mutationResponse($updatedOrder, 'تم تأكيد تسليم الطلب.');
    }

    public function reportIssue(ReportCourierDeliveryIssueRequest $request, Order $order): JsonResponse
    {
        $courier = $this->courier($request);
        $statusLog = DB::transaction(function () use ($courier, $order, $request): OrderStatusLog {
            $ownedOrder = Order::query()
                ->whereBelongsTo($courier, 'courier')
                ->lockForUpdate()
                ->findOrFail($order->id);

            abort_unless($ownedOrder->order_status === 'out_for_delivery', 409, 'Only an active delivery can receive an issue report.');

            return OrderStatusLog::create([
                'order_id' => $ownedOrder->id,
                'from_status' => $ownedOrder->order_status,
                'to_status' => $ownedOrder->order_status,
                'action_by_staff_id' => $courier->id,
                'notes' => 'بلاغ مندوب: '.$request->validated('reason'),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'تم تسجيل بلاغ تعذر التسليم.',
            'data' => [
                'id' => (int) $statusLog->id,
                'order_id' => (int) $order->id,
                'reported_at' => $statusLog->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function courier(Request $request): StaffUser
    {
        $courier = $request->user('staff_api');
        abort_unless($courier instanceof StaffUser && $courier->role === 'courier', 403);

        return $courier;
    }

    private function recordStatusChange(
        Order $order,
        StaffUser $courier,
        string $fromStatus,
        string $toStatus,
        string $notes,
    ): void {
        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action_by_staff_id' => $courier->id,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }

    private function broadcastStatus(Order $order): void
    {
        $order->load(['items.options', 'deliveryAddress', 'courier', 'user']);
        OrderStatusUpdated::dispatch((new ManagerOrderSummaryResource($order))->resolve());
    }

    /** @return array<string, mixed> */
    private function courierOrderDetails(Order $order, Request $request): array
    {
        $details = (new ManagerOrderDetailResource($order))->resolve($request);
        unset($details['delivery']['google_maps_url'], $details['delivery']['latitude'], $details['delivery']['longitude']);

        return $details;
    }

    private function mutationResponse(Order $order, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => ManagerOrderSummaryResource::mapStatus($order->order_status),
                'status_label' => ManagerOrderSummaryResource::statusLabel($order->order_status),
            ],
        ]);
    }
}
