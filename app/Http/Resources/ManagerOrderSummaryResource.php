<?php

namespace App\Http\Resources;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class ManagerOrderSummaryResource extends JsonResource
{
    public static function mapStatus(string $status): string
    {
        return match ($status) {
            'received', 'pending_confirmation', 'pending' => 'pending',
            'preparing' => 'preparing',
            'ready', 'ready_for_pickup' => 'ready',
            'out_for_delivery' => 'out_for_delivery',
            'delivered', 'completed' => 'completed',
            'cancelled', 'rejected' => 'cancelled',
            default => $status,
        };
    }

    public static function statusLabel(string $status): string
    {
        return match (self::mapStatus($status)) {
            'pending' => 'جديدة',
            'preparing' => 'قيد التجهيز',
            'ready' => 'جاهز للاستلام',
            'out_for_delivery' => 'مع المندوب',
            'completed' => 'مكتملة',
            'cancelled' => 'ملغية',
            default => $status,
        };
    }

    public static function statusColorHex(string $status): string
    {
        return match (self::mapStatus($status)) {
            'pending' => '#D32F2F',
            'preparing' => '#D4AF37',
            'ready' => '#2E7D32',
            'out_for_delivery' => '#1976D2',
            'completed' => '#388E3C',
            'cancelled' => '#757575',
            default => '#9E9E9E',
        };
    }

    public static function formatArabicTimeAgo(Carbon $datetime): string
    {
        $now = now();
        $diffInSeconds = max(0, $now->diffInSeconds($datetime));

        if ($diffInSeconds < 60) {
            return 'الآن';
        }

        $diffInMinutes = (int) floor($diffInSeconds / 60);
        if ($diffInMinutes < 60) {
            return "قبل {$diffInMinutes} دقيقة";
        }

        $diffInHours = (int) floor($diffInMinutes / 60);
        if ($diffInHours < 24) {
            return "قبل {$diffInHours} ساعة";
        }

        $diffInDays = (int) floor($diffInHours / 24);

        return "قبل {$diffInDays} يوم";
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusKey = self::mapStatus((string) $this->order_status);
        $createdAt = $this->created_at ? Carbon::parse($this->created_at) : now();
        $prepMinutes = (int) ($this->estimated_prep_time_minutes ?? 30);
        $targetReadyTime = $createdAt->copy()->addMinutes($prepMinutes);

        $now = now();
        $remainingMinutes = (int) round($now->diffInMinutes($targetReadyTime, false));
        if ($remainingMinutes < 0) {
            $remainingMinutes = 0;
        }

        $isPending = $statusKey === 'pending';
        $pendingOver5Min = $isPending && $createdAt->diffInMinutes($now) >= 5;
        $isUrgent = $isPending && ($pendingOver5Min || $remainingMinutes <= 10);

        // Customer badge calculation
        $isVip = (bool) ($this->user?->is_vip);
        $ordersCount = (int) ($this->user?->total_orders_count ?? 1);
        if ($isVip) {
            $badge = 'عميل مميز (VIP)';
            $badgeType = 'vip';
        } elseif ($ordersCount > 3) {
            $badge = 'عميل نشط';
            $badgeType = 'active';
        } else {
            $badge = 'عميل جديد';
            $badgeType = 'new';
        }

        // Delivery short address
        $shortAddress = 'استلام من المطعم';
        if ($this->delivery_mode === 'delivery') {
            if ($this->deliveryAddress) {
                $parts = array_filter([
                    $this->deliveryAddress->district_ar,
                    $this->deliveryAddress->street_details_ar ?: $this->deliveryAddress->title_ar,
                ]);
                $shortAddress = count($parts) > 0 ? implode(' - ', $parts) : ($this->deliveryAddress->title_ar ?: 'عنوان التوصيل');
            } else {
                $shortAddress = 'توصيل منزلي';
            }
        }

        // Items preview
        $itemsPreview = [];
        $itemsCount = 0;
        if ($this->relationLoaded('items')) {
            $itemsCount = $this->items->sum('quantity');
            $itemsPreview = $this->items->take(3)->map(function ($item) {
                $line = $item->product_name_ar_snapshot;
                if ($item->size_name_ar_snapshot) {
                    $line .= " ({$item->size_name_ar_snapshot})";
                }

                return $line;
            })->values()->all();
        }

        $isPaid = $this->payment_status === 'paid';
        $paymentType = $this->payment_method === 'cod' ? 'cash' : 'online';
        $paymentMethod = $this->payment_method === 'cod' ? 'cash_on_delivery' : ($this->payment_method ?? 'cash_on_delivery');
        $paymentMethodLabel = $this->payment_method === 'cod' ? 'الدفع عند الاستلام' : 'مدى / أبل باي';

        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'status' => $statusKey,
            'status_label' => self::statusLabel((string) $this->order_status),
            'status_color_hex' => self::statusColorHex((string) $this->order_status),
            'is_urgent' => $isUrgent,
            'created_at' => $createdAt->toIso8601String(),
            'time_ago' => self::formatArabicTimeAgo($createdAt),
            'target_ready_time' => $targetReadyTime->toIso8601String(),
            'remaining_minutes' => $remainingMinutes,
            'customer' => [
                'id' => (int) ($this->user_id ?? 0),
                'name' => $this->customer_name ?: ($this->user?->full_name ?? $this->user?->name ?? 'عميل'),
                'phone' => $this->customer_phone ?: ($this->user?->phone ?? ''),
                'badge' => $badge,
                'badge_type' => $badgeType,
            ],
            'delivery' => [
                'type' => $this->delivery_mode === 'delivery' ? 'delivery' : 'pickup',
                'type_label' => $this->delivery_mode === 'delivery' ? 'توصيل منزلي' : 'استلام من المطعم',
                'short_address' => $shortAddress,
                'driver_name' => $this->courier?->full_name,
                'driver_phone' => $this->courier?->phone,
            ],
            'items_count' => (int) $itemsCount,
            'items_preview' => $itemsPreview,
            'notes' => $this->chef_special_notes,
            'total_amount' => number_format((float) $this->total_final_amount, 2, '.', ''),
            'currency' => 'SAR',
            'payment' => [
                'method' => $paymentMethod,
                'method_label' => $paymentMethodLabel,
                'type' => $paymentType,
                'is_paid' => $isPaid,
                'status' => $isPaid ? 'paid' : ($this->payment_status === 'refunded' ? 'refunded' : 'unpaid'),
            ],
        ];
    }
}
