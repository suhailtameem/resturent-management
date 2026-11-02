<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ManagerDriverResource;
use App\Http\Resources\ManagerOrderDetailResource;
use App\Http\Resources\ManagerOrderSummaryResource;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\StaffUser;
use App\Models\StoreSettings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ManagerOrderController extends Controller
{
    /**
     * Delete a selected set of orders and their database-cascaded records.
     */
    public function destroyMany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct', 'exists:orders,id'],
        ]);

        $deletedCount = DB::transaction(
            fn (): int => Order::query()->whereIn('id', $data['ids'])->delete(),
        );

        return response()->json(['data' => ['deleted_count' => $deletedCount]]);
    }

    /**
     * Get KPI statistics summary for orders dashboard.
     */
    public function stats(): JsonResponse
    {
        $today = Carbon::today('Asia/Riyadh');

        $needsActionCount = Order::query()
            ->whereIn('order_status', ['received', 'pending_confirmation', 'pending'])
            ->count();

        $preparingCount = Order::query()
            ->where('order_status', 'preparing')
            ->count();

        $outForDeliveryCount = Order::query()
            ->where('order_status', 'out_for_delivery')
            ->count();

        $completedTodayCount = Order::query()
            ->whereIn('order_status', ['delivered', 'completed'])
            ->where(function ($q) use ($today) {
                $q->whereDate('delivered_at', $today)
                    ->orWhere(function ($sub) use ($today) {
                        $sub->whereNull('delivered_at')->whereDate('updated_at', $today);
                    });
            })
            ->count();

        $totalTodayCount = Order::query()
            ->whereDate('created_at', $today)
            ->count();

        $urgentOrdersCount = Order::query()
            ->whereIn('order_status', ['received', 'pending_confirmation', 'pending'])
            ->where('created_at', '<=', now()->subMinutes(5))
            ->count();

        $todayRevenue = Order::query()
            ->whereIn('order_status', ['delivered', 'completed'])
            ->where(function ($q) use ($today) {
                $q->whereDate('delivered_at', $today)
                    ->orWhere(function ($sub) use ($today) {
                        $sub->whereNull('delivered_at')->whereDate('updated_at', $today);
                    });
            })
            ->sum('total_final_amount');

        return response()->json([
            'data' => [
                'needs_action_count' => $needsActionCount,
                'preparing_count' => $preparingCount,
                'out_for_delivery_count' => $outForDeliveryCount,
                'completed_today_count' => $completedTodayCount,
                'total_today_count' => $totalTodayCount,
                'has_urgent_orders' => $urgentOrdersCount > 0,
                'urgent_orders_count' => $urgentOrdersCount,
                'today_revenue' => number_format((float) $todayRevenue, 2, '.', ''),
                'currency' => 'SAR',
            ],
        ]);
    }

    /**
     * List orders with filtering and pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with(['items.options', 'deliveryAddress', 'courier', 'user'])
            ->select('orders.*');

        // Status filter
        if ($request->filled('status') && $request->query('status') !== 'all') {
            $status = (string) $request->query('status');
            match ($status) {
                'pending' => $query->whereIn('order_status', ['pending', 'received', 'pending_confirmation']),
                'preparing' => $query->where('order_status', 'preparing'),
                'ready' => $query->whereIn('order_status', ['ready', 'ready_for_pickup']),
                'out_for_delivery' => $query->where('order_status', 'out_for_delivery'),
                'completed', 'delivered' => $query->whereIn('order_status', ['completed', 'delivered']),
                'cancelled' => $query->whereIn('order_status', ['cancelled', 'rejected']),
                default => $query->where('order_status', $status),
            };
        }

        // Payment type filter
        if ($request->filled('payment_type') && $request->query('payment_type') !== 'all') {
            $paymentType = (string) $request->query('payment_type');
            if ($paymentType === 'cash') {
                $query->where('payment_method', 'cod');
            } elseif ($paymentType === 'online') {
                $query->where('payment_method', '!=', 'cod');
            }
        }

        // Delivery type filter
        if ($request->filled('delivery_type') && $request->query('delivery_type') !== 'all') {
            $query->where('delivery_mode', $request->query('delivery_type'));
        }

        // Search by order number, customer name, customer phone
        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'created_at') {
            $query->orderBy('created_at', $sortOrder)->orderBy('id', $sortOrder);
        } else {
            $query->orderBy('created_at', $sortOrder)->orderBy('id', $sortOrder);
        }

        $perPage = (int) $request->query('per_page', 15);
        if ($perPage < 1 || $perPage > 50) {
            $perPage = 15;
        }

        return ManagerOrderSummaryResource::collection($query->paginate($perPage));
    }

    /**
     * Show full order details.
     */
    public function show(Order $order): JsonResponse
    {
        $order->load(['items.options', 'deliveryAddress', 'courier', 'user', 'coupon', 'statusLogs']);

        return response()->json([
            'data' => (new ManagerOrderDetailResource($order))->toArray(request()),
        ]);
    }

    /**
     * Update order workflow status.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'nullable',
                'string',
                'in:pending,preparing,ready,out_for_delivery,delivered,completed,cancelled',
            ],
            'to_status' => [
                'nullable',
                'string',
                'in:received,pending_confirmation,pending,preparing,ready,ready_for_pickup,out_for_delivery,delivered,completed,cancelled,rejected',
            ],
            'note' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'payment_status' => ['nullable', 'string', 'in:pending,unpaid,paid,failed,refunded'],
        ]);

        $requestedStatus = $validated['status'] ?? $validated['to_status'] ?? null;
        if (! $requestedStatus) {
            return response()->json([
                'message' => 'Status field is required.',
                'errors' => ['status' => ['The status field is required.']],
            ], 422);
        }

        $note = $validated['note'] ?? $validated['notes'] ?? null;

        $updatedOrder = DB::transaction(function () use ($order, $requestedStatus, $note, $validated) {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $lockedOrder->order_status;

            // Map manager status to internal database status
            $toDbStatus = match ($requestedStatus) {
                'pending' => 'pending',
                'preparing' => 'preparing',
                'ready' => $lockedOrder->delivery_mode === 'pickup' ? 'ready_for_pickup' : 'ready',
                'out_for_delivery' => 'out_for_delivery',
                'delivered', 'completed' => 'delivered',
                'cancelled' => 'cancelled',
                default => $requestedStatus,
            };

            $updateData = ['order_status' => $toDbStatus];

            if ($toDbStatus === 'out_for_delivery' && ! $lockedOrder->dispatched_at) {
                $updateData['dispatched_at'] = now();
            }

            if (in_array($toDbStatus, ['delivered', 'completed'], true)) {
                if (! $lockedOrder->delivered_at) {
                    $updateData['delivered_at'] = now();
                }
                if ($lockedOrder->payment_method === 'cod') {
                    $updateData['payment_status'] = 'paid';
                }
            }

            if (in_array($toDbStatus, ['cancelled', 'rejected'], true)) {
                $updateData['cancelled_at'] = now();
                $updateData['cancellation_reason'] = $note;
                CouponRedemption::query()->where('order_id', $lockedOrder->id)->delete();
            }

            if (isset($validated['payment_status'])) {
                $paymentStatus = $validated['payment_status'] === 'unpaid' ? 'pending' : $validated['payment_status'];
                $updateData['payment_status'] = $paymentStatus;
            }

            $lockedOrder->update($updateData);

            OrderStatusLog::create([
                'order_id' => $lockedOrder->id,
                'from_status' => $fromStatus,
                'to_status' => $toDbStatus,
                'action_by_staff_id' => auth('staff_api')->id(),
                'notes' => $note,
                'created_at' => now(),
            ]);

            return $lockedOrder;
        });

        $this->broadcastOrderUpdate($updatedOrder);

        $managerStatus = ManagerOrderSummaryResource::mapStatus($updatedOrder->order_status);
        $statusLabel = ManagerOrderSummaryResource::statusLabel($updatedOrder->order_status);

        $stepIndex = match ($managerStatus) {
            'pending' => 0,
            'preparing' => 1,
            'ready' => 2,
            'out_for_delivery' => 3,
            'completed' => 4,
            default => -1,
        };

        return response()->json([
            'data' => [
                'id' => (int) $updatedOrder->id,
                'order_number' => $updatedOrder->order_number,
                'status' => $requestedStatus ?? $managerStatus,
                'status_label' => $statusLabel,
                'status_label_ar' => $updatedOrder->statusLabelAr(),
                'status_label_en' => $updatedOrder->statusLabelEn(),
                'current_step_index' => $stepIndex,
                'payment_status' => $updatedOrder->payment_status,
                'updated_at' => $updatedOrder->updated_at?->toIso8601String(),
            ],
            'message' => "تم تحديث حالة الطلب #{$updatedOrder->order_number} إلى '{$statusLabel}' بنجاح",
        ]);
    }

    /**
     * Assign a delivery driver to an order.
     */
    public function assignDriver(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => ['required', 'integer', 'exists:staff_users,id'],
            'notify_driver' => ['nullable', 'boolean'],
        ]);

        $driver = StaffUser::query()
            ->where('id', $validated['driver_id'])
            ->where('role', 'courier')
            ->where('is_active', true)
            ->first();

        if (! $driver) {
            return response()->json([
                'message' => 'The selected delivery driver is invalid or inactive.',
                'errors' => ['driver_id' => ['السائق المحدد غير متاح حالياً أو ليس له صلاحية توصيل']],
            ], 422);
        }

        $order = DB::transaction(function () use ($order, $driver) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $locked->order_status;

            $updateData = ['courier_id' => $driver->id];

            // If order was ready or preparing, dispatching with courier marks it as out_for_delivery
            if (in_array($locked->order_status, ['ready', 'ready_for_pickup', 'preparing'], true)) {
                $updateData['order_status'] = 'out_for_delivery';
                if (! $locked->dispatched_at) {
                    $updateData['dispatched_at'] = now();
                }
            }

            $locked->update($updateData);

            OrderStatusLog::create([
                'order_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $locked->order_status,
                'action_by_staff_id' => auth('staff_api')->id(),
                'notes' => "تم تعيين المندوب {$driver->full_name} للطلب",
                'created_at' => now(),
            ]);

            return $locked;
        });

        $this->broadcastOrderUpdate($order);

        return response()->json([
            'data' => [
                'order_id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => ManagerOrderSummaryResource::mapStatus($order->order_status),
                'driver' => [
                    'id' => (int) $driver->id,
                    'name' => $driver->full_name,
                    'phone' => $driver->phone,
                    'assigned_at' => now()->toIso8601String(),
                ],
            ],
            'message' => "تم تعيين المندوب {$driver->full_name} للطلب بنجاح",
        ]);
    }

    /**
     * List active delivery drivers available for assignment.
     */
    public function drivers(): AnonymousResourceCollection
    {
        $drivers = StaffUser::query()
            ->where('role', 'courier')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        return ManagerDriverResource::collection($drivers);
    }

    /**
     * Cancel / Reject an order with a reason.
     */
    public function cancel(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'notify_customer' => ['nullable', 'boolean'],
        ]);

        $cancelledOrder = DB::transaction(function () use ($order, $validated) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $locked->order_status;

            $now = now();
            $locked->update([
                'order_status' => 'cancelled',
                'cancelled_at' => $now,
                'cancellation_reason' => $validated['reason'],
            ]);

            CouponRedemption::query()->where('order_id', $locked->id)->delete();

            OrderStatusLog::create([
                'order_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'action_by_staff_id' => auth('staff_api')->id(),
                'notes' => $validated['reason'],
                'created_at' => $now,
            ]);

            return $locked;
        });

        $this->broadcastOrderUpdate($cancelledOrder);

        return response()->json([
            'data' => [
                'id' => (int) $cancelledOrder->id,
                'order_number' => $cancelledOrder->order_number,
                'status' => 'cancelled',
                'status_label' => 'ملغية',
                'cancelled_at' => $cancelledOrder->cancelled_at?->toIso8601String() ?? now()->toIso8601String(),
                'cancellation_reason' => $cancelledOrder->cancellation_reason,
            ],
            'message' => 'تم إلغاء الطلب بنجاح',
        ]);
    }

    /**
     * Generate thermal printer ESC/POS data and structured ticket for kitchen/invoice.
     */
    public function printTicket(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'ticket_type' => ['sometimes', 'string', 'in:kitchen,invoice'],
        ]);
        $ticketType = $validated['ticket_type'] ?? 'kitchen';
        $settings = StoreSettings::current();
        $order->load(['items.options', 'deliveryAddress', 'courier']);

        $ticketItems = $order->items->map(function ($item) {
            $addons = $item->options->pluck('option_name_ar_snapshot')->filter()->values()->all();

            return [
                'qty' => (int) $item->quantity,
                'name' => $item->product_name_ar_snapshot,
                'size' => $item->size_name_ar_snapshot,
                'addons' => $addons,
                'notes' => $item->special_instructions,
            ];
        })->values()->all();

        $deliveryLabel = $order->delivery_mode === 'delivery' ? 'توصيل منزلي' : 'استلام من المطعم';

        // ESC/POS minimal command buffer representation
        $escPosBytes = "\x1B\x40"; // Initialize printer
        $escPosBytes .= "\x1B\x61\x01"; // Center align
        $escPosBytes .= "{$settings->store_name_ar}\n";
        $escPosBytes .= "--------------------------------\n";
        $escPosBytes .= "Order: {$order->order_number}\n";
        $escPosBytes .= "Type: {$deliveryLabel}\n";
        $escPosBytes .= "--------------------------------\n";
        $escPosBytes .= "\x1B\x61\x00"; // Left align
        foreach ($ticketItems as $tItem) {
            $escPosBytes .= "{$tItem['qty']}x {$tItem['name']}";
            if ($tItem['size']) {
                $escPosBytes .= " ({$tItem['size']})";
            }
            $escPosBytes .= "\n";
            if (! empty($tItem['addons'])) {
                $escPosBytes .= '  + '.implode(', ', $tItem['addons'])."\n";
            }
            if ($tItem['notes']) {
                $escPosBytes .= "  * {$tItem['notes']}\n";
            }
        }
        $escPosBytes .= "--------------------------------\n";
        if ($order->chef_special_notes) {
            $escPosBytes .= "Notes: {$order->chef_special_notes}\n";
        }
        $escPosBytes .= "\x1D\x56\x41\x03"; // Cut paper

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'printed_at' => now()->toIso8601String(),
                'ticket_type' => $ticketType,
                'store_name' => $settings->store_name_ar,
                'kitchen_station' => 'المطبخ الرئيسي',
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'delivery_type' => $deliveryLabel,
                'delivery_address' => $order->deliveryAddress?->street_details_ar,
                'items' => $ticketItems,
                'order_notes' => $order->chef_special_notes,
                'raw_esc_pos_base64' => base64_encode($escPosBytes),
            ],
        ]);
    }

    /**
     * Real-time polling endpoint for orders updates.
     */
    public function poll(Request $request): JsonResponse
    {
        $sinceParam = $request->query('since');
        if ($sinceParam) {
            $normalized = str_replace(' ', '+', (string) $sinceParam);
            try {
                $since = Carbon::parse($normalized);
            } catch (\Throwable) {
                $since = now()->subMinutes(10);
            }
        } else {
            $since = now()->subMinutes(10);
        }

        $newOrders = Order::query()
            ->with(['items.options', 'deliveryAddress', 'courier', 'user'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->get();

        $updatedOrders = Order::query()
            ->with(['items.options', 'deliveryAddress', 'courier', 'user'])
            ->where('updated_at', '>=', $since)
            ->where('created_at', '<', $since)
            ->orderByDesc('updated_at')
            ->get();

        $statsResponse = $this->stats()->getData(true);

        return response()->json([
            'data' => [
                'new_orders' => ManagerOrderSummaryResource::collection($newOrders),
                'updated_orders' => ManagerOrderSummaryResource::collection($updatedOrders),
                'stats' => $statsResponse['data'] ?? [],
            ],
        ]);
    }

    private function broadcastOrderUpdate(Order $order): void
    {
        $order->load(['items.options', 'deliveryAddress', 'courier', 'user']);
        $orderSummary = (new ManagerOrderSummaryResource($order))->resolve();

        OrderStatusUpdated::dispatch($orderSummary);
    }
}
