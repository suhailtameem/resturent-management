<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $coupon_config_id
 * @property int $order_id
 * @property int|null $customer_id
 * @property string $customer_phone
 * @property string $discount_amount_applied
 * @property Carbon|null $created_at
 */
class CouponUsage extends Model
{
    /** Only has created_at, no updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'coupon_config_id',
        'order_id',
        'customer_id',
        'customer_phone',
        'discount_amount_applied',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'discount_amount_applied' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function couponConfig(): BelongsTo
    {
        return $this->belongsTo(OfferCouponConfig::class, 'coupon_config_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
