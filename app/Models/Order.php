<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_phone',
        'delivery_address_id',
        'delivery_zone_id',
        'courier_id',
        'order_status',
        'payment_method',
        'payment_status',
        'subtotal_amount',
        'product_discount_amount',
        'coupon_id',
        'coupon_discount_amount',
        'delivery_fee',
        'vat_tax_amount',
        'total_final_amount',
        'chef_special_notes',
        'estimated_prep_time_minutes',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
        'delivery_mode',
        'scheduled_start_at',
        'scheduled_end_at',
        'idempotency_key',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'product_discount_amount' => 'decimal:2',
        'coupon_discount_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'vat_tax_amount' => 'decimal:2',
        'total_final_amount' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function couponUsages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function offerRedemptionLogs(): HasMany
    {
        return $this->hasMany(OfferRedemptionLog::class);
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'delivery_address_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'order_id')->orderBy('id', 'asc');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->order_status, ['received', 'pending_confirmation', 'pending'], true);
    }

    public function statusLabelAr(): string
    {
        return match ($this->order_status) {
            'received', 'pending' => 'تم استلام الطلب',
            'pending_confirmation' => 'بانتظار التأكيد',
            'preparing' => 'قيد التحضير',
            'ready_for_pickup', 'ready' => 'جاهز للاستلام',
            'out_for_delivery' => 'في الطريق إليك',
            'delivered', 'completed' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            'rejected' => 'مرفوض',
            default => (string) $this->order_status,
        };
    }

    public function statusLabelEn(): string
    {
        return match ($this->order_status) {
            'received', 'pending' => 'Order Received',
            'pending_confirmation' => 'Pending Confirmation',
            'preparing' => 'Preparing',
            'ready_for_pickup', 'ready' => 'Ready for Pickup',
            'out_for_delivery' => 'Out for Delivery',
            'delivered', 'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->order_status)),
        };
    }
}
