<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class ManagerCustomerMetrics
{
    public const FREQUENT_ORDER_THRESHOLD = 10;

    /**
     * @param  array<int, int>  $customerIds
     * @return array<int, array<string, mixed>>
     */
    public function forCustomers(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $completedStatuses = ['delivered', 'completed'];
        $completed = Order::query()
            ->whereIn('user_id', $customerIds)
            ->whereIn('order_status', $completedStatuses);

        $completedAggregates = (clone $completed)
            ->selectRaw('user_id, COUNT(*) as completed_orders_count, COALESCE(SUM(total_final_amount), 0) as completed_spend')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $lastOrders = Order::query()
            ->whereIn('user_id', $customerIds)
            ->selectRaw('user_id, MAX(created_at) as last_order_at')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $recentlyActiveIds = Order::query()
            ->whereIn('user_id', $customerIds)
            ->whereIn('order_status', $completedStatuses)
            ->whereRaw('COALESCE(delivered_at, updated_at) >= ?', [now()->subDays(30)])
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $favoriteRows = Order::query()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.user_id', $customerIds)
            ->whereIn('orders.order_status', $completedStatuses)
            ->selectRaw('orders.user_id, order_items.product_name_ar_snapshot as item_name, SUM(order_items.quantity) as quantity_total')
            ->groupBy('orders.user_id', 'order_items.product_name_ar_snapshot')
            ->orderBy('orders.user_id')
            ->orderByDesc('quantity_total')
            ->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows): ?string => $rows->first()?->item_name);

        $metrics = [];
        foreach ($customerIds as $customerId) {
            $aggregate = $completedAggregates->get($customerId);
            $metrics[$customerId] = [
                'completed_orders_count' => (int) ($aggregate?->completed_orders_count ?? 0),
                'completed_spend' => number_format((float) ($aggregate?->completed_spend ?? 0), 2, '.', ''),
                'last_order_at' => $lastOrders->get($customerId)?->last_order_at,
                'favorite_item' => $favoriteRows->get($customerId),
                'is_recently_active' => in_array($customerId, $recentlyActiveIds, true),
            ];
        }

        return $metrics;
    }
}
