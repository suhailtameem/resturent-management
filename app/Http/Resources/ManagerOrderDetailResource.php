<?php

namespace App\Http\Resources;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class ManagerOrderDetailResource extends JsonResource
{
    public static function formatArabicTime(Carbon $datetime): string
    {
        $timeStr = $datetime->setTimezone('Asia/Riyadh')->format('h:i A');

        return str_replace(['AM', 'PM'], ['ص', 'م'], $timeStr);
    }

    public static function normalizeSaudiPhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if (str_starts_with($clean, '05')) {
            return '966'.substr($clean, 1);
        }
        if (str_starts_with($clean, '5') && strlen($clean) === 9) {
            return '966'.$clean;
        }

        return $clean;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $statusKey = ManagerOrderSummaryResource::mapStatus((string) $this->order_status);
        $createdAt = $this->created_at ? Carbon::parse($this->created_at) : now();
        $prepMinutes = (int) ($this->estimated_prep_time_minutes ?? 30);
        $targetReadyTime = $this->scheduled_start_at
            ? Carbon::parse($this->scheduled_start_at)
            : $createdAt->copy()->addMinutes($prepMinutes);
        $targetDeliveryTime = $this->delivery_mode === 'delivery'
            ? ($this->scheduled_end_at
                ? Carbon::parse($this->scheduled_end_at)
                : $targetReadyTime->copy()->addMinutes(20))
            : $targetReadyTime;

        $now = now();
        $remainingMinutes = (int) round($now->diffInMinutes($targetReadyTime, false));
        if ($remainingMinutes < 0) {
            $remainingMinutes = 0;
        }

        $isPending = $statusKey === 'pending';
        $pendingOver5Min = $isPending && $createdAt->diffInMinutes($now) >= 5;
        $isUrgent = $isPending && ($pendingOver5Min || $remainingMinutes <= 10);

        $urgentReason = null;
        if ($isUrgent) {
            if ($remainingMinutes <= 10 && $remainingMinutes > 0) {
                $urgentReason = "متبقي {$remainingMinutes} دقائق على موعد التسليم المستهدف";
            } elseif ($pendingOver5Min) {
                $urgentReason = 'طلب جديد بانتظار التأكيد منذ أكثر من 5 دقائق';
            } else {
                $urgentReason = 'طلب عاجل بحاجة لإجراء سريع';
            }
        }

        // Stepper calculation:
        // 0: pending, 1: preparing, 2: ready, 3: out_for_delivery, 4: delivered
        $currentStepIndex = match ($statusKey) {
            'pending' => 0,
            'preparing' => 1,
            'ready' => 2,
            'out_for_delivery' => 3,
            'completed' => 4,
            'cancelled' => -1,
            default => 0,
        };

        // Customer details
        $customerName = $this->customer_name ?: ($this->user?->full_name ?? $this->user?->name ?? 'عميل');
        $rawPhone = $this->customer_phone ?: ($this->user?->phone ?? '');
        $normalizedPhone = self::normalizeSaudiPhone($rawPhone);
        $whatsappMessage = "مرحباً {$customerName}، بخصوص طلبك رقم {$this->order_number}";
        $whatsappUrl = $normalizedPhone !== ''
            ? "https://wa.me/{$normalizedPhone}?text=".urlencode($whatsappMessage)
            : null;

        $isVip = (bool) ($this->user?->is_vip);
        $totalOrdersCount = (int) ($this->user?->total_orders_count ?? 1);
        if ($isVip) {
            $badge = 'عميل مميز (VIP)';
            $badgeType = 'vip';
        } elseif ($totalOrdersCount > 3) {
            $badge = 'عميل نشط';
            $badgeType = 'active';
        } else {
            $badge = 'عميل جديد';
            $badgeType = 'new';
        }

        // Delivery details
        $address = $this->deliveryAddress;
        $lat = $address && $address->latitude !== null ? (float) $address->latitude : null;
        $lng = $address && $address->longitude !== null ? (float) $address->longitude : null;
        $googleMapsUrl = ($lat !== null && $lng !== null)
            ? "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}"
            : null;

        $driver = null;
        if ($this->courier) {
            $driver = [
                'id' => (int) $this->courier->id,
                'name' => $this->courier->full_name,
                'phone' => $this->courier->phone,
                'vehicle_type' => $this->courier->vehicle_type ?? 'دباب / سكوتر',
            ];
        }

        // Line Items
        $items = $this->items->map(function ($item) {
            $addons = $item->options->map(function ($option) {
                return [
                    'group_name' => 'الصلصات والإضافات',
                    'option_name' => $option->option_name_ar_snapshot,
                    'price' => number_format((float) $option->option_price_snapshot, 2, '.', ''),
                    'quantity' => 1,
                ];
            })->values()->all();

            $sizeName = $item->size_name_ar_snapshot ?: '';
            if ($item->piece_count_snapshot) {
                $sizeName = $sizeName !== ''
                    ? "{$sizeName} • {$item->piece_count_snapshot} قطعة"
                    : "{$item->piece_count_snapshot} قطعة";
            }

            return [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name_ar_snapshot,
                'size_name' => $sizeName,
                'quantity' => (int) $item->quantity,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'line_total' => number_format((float) $item->line_total, 2, '.', ''),
                'selected_addons' => $addons,
                'notes' => $item->special_instructions,
            ];
        })->values()->all();

        // Financials
        $isPaid = $this->payment_status === 'paid';
        $paymentType = $this->payment_method === 'cod' ? 'cash' : 'online';
        $paymentMethod = $this->payment_method === 'cod' ? 'cash_on_delivery' : ($this->payment_method ?? 'cash_on_delivery');
        $paymentMethodLabel = $this->payment_method === 'cod' ? 'الدفع عند الاستلام (كاش / شبكة)' : 'مدى / أبل باي';
        $discountAmount = (float) $this->product_discount_amount + (float) $this->coupon_discount_amount;

        $financials = [
            'items_subtotal' => number_format((float) $this->subtotal_amount, 2, '.', ''),
            'delivery_fee' => number_format((float) $this->delivery_fee, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'coupon_code' => $this->coupon?->code,
            'tax_rate_percent' => 15,
            'tax_amount' => number_format((float) $this->vat_tax_amount, 2, '.', ''),
            'tax_included_in_subtotal' => true,
            'total_amount' => number_format((float) $this->total_final_amount, 2, '.', ''),
            'currency' => 'SAR',
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethodLabel,
            'payment_type' => $paymentType,
            'payment_status' => $isPaid ? 'paid' : ($this->payment_status === 'refunded' ? 'refunded' : 'unpaid'),
            'payment_status_label' => $isPaid ? 'مسددة بالكامل' : 'غير مسددة بعد',
        ];

        // Workflow timeline mapping from status logs
        $logs = $this->relationLoaded('statusLogs') ? $this->statusLogs : $this->statusLogs()->get();

        $logTimestamps = [
            'pending' => $createdAt->toIso8601String(),
            'preparing' => null,
            'ready' => null,
            'out_for_delivery' => $this->dispatched_at?->toIso8601String(),
            'delivered' => $this->delivered_at?->toIso8601String(),
        ];

        foreach ($logs as $log) {
            $mapped = ManagerOrderSummaryResource::mapStatus($log->to_status);
            if ($mapped === 'completed') {
                $mapped = 'delivered';
            }
            if (isset($logTimestamps[$mapped]) && ! $logTimestamps[$mapped]) {
                $logTimestamps[$mapped] = $log->created_at?->toIso8601String();
            }
        }

        $steps = [
            [
                'step_index' => 0,
                'step_key' => 'pending',
                'title' => 'مستلم',
                'desc' => 'تم استلام الطلب وبانتظار الموافقة',
            ],
            [
                'step_index' => 1,
                'step_key' => 'preparing',
                'title' => 'بالتجهيز',
                'desc' => 'بالمطبخ الآن',
            ],
            [
                'step_index' => 2,
                'step_key' => 'ready',
                'title' => 'جاهز',
                'desc' => 'مغلّف وجاهز للتسليم',
            ],
            [
                'step_index' => 3,
                'step_key' => 'out_for_delivery',
                'title' => 'مع المندوب',
                'desc' => 'بالطريق للزبون',
            ],
            [
                'step_index' => 4,
                'step_key' => 'delivered',
                'title' => 'تم التسليم',
                'desc' => 'طلب ناجح ومكتمل',
            ],
        ];

        $timeline = array_map(function ($step) use ($currentStepIndex, $logTimestamps) {
            $isCompleted = $currentStepIndex >= 0 && $currentStepIndex >= $step['step_index'];

            return [
                'step_index' => $step['step_index'],
                'step_key' => $step['step_key'],
                'title' => $step['title'],
                'desc' => $step['desc'],
                'completed' => $isCompleted,
                'timestamp' => $isCompleted ? ($logTimestamps[$step['step_key']] ?? null) : null,
            ];
        }, $steps);

        return [
            'id' => (int) $this->id,
            'order_number' => $this->order_number,
            'status' => $statusKey,
            'status_label' => ManagerOrderSummaryResource::statusLabel((string) $this->order_status),
            'status_color_hex' => ManagerOrderSummaryResource::statusColorHex((string) $this->order_status),
            'current_step_index' => $currentStepIndex,
            'is_urgent' => $isUrgent,
            'urgent_reason' => $urgentReason,
            'created_at' => $createdAt->toIso8601String(),
            'time_ago' => ManagerOrderSummaryResource::formatArabicTimeAgo($createdAt),
            'order_time_formatted' => self::formatArabicTime($createdAt),
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601String(),
            'target_ready_time' => $targetReadyTime->toIso8601String(),
            'target_delivery_time' => $targetDeliveryTime->toIso8601String(),
            'remaining_minutes' => $remainingMinutes,

            'customer' => [
                'id' => (int) ($this->user_id ?? 0),
                'name' => $customerName,
                'phone' => $rawPhone,
                'badge' => $badge,
                'badge_type' => $badgeType,
                'total_orders_count' => $totalOrdersCount,
                'whatsapp_url' => $whatsappUrl,
            ],

            'delivery' => [
                'type' => $this->delivery_mode === 'delivery' ? 'delivery' : 'pickup',
                'type_label' => $this->delivery_mode === 'delivery' ? 'توصيل منزلي' : 'استلام من المطعم',
                'city' => $address?->city_ar ?? 'تبوك',
                'neighborhood' => $address?->district_ar ?? '',
                'street_address' => $address?->street_details_ar ?: ($this->delivery_mode === 'pickup' ? 'استلام من الفرع' : 'العنوان المحدد'),
                'building_details' => $address?->notes,
                'latitude' => $lat,
                'longitude' => $lng,
                'google_maps_url' => $googleMapsUrl,
                'driver' => $driver,
            ],

            'items' => $items,
            'financials' => $financials,
            'timeline' => $timeline,
        ];
    }
}
