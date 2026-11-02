<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'maximum_discount_amount',
        'min_order_value',
        'allow_combine_with_product_discount',
        'start_date',
        'end_date',
        'usage_limit_total',
        'usage_limit_per_user',
        'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'maximum_discount_amount' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'allow_combine_with_product_discount' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'usage_limit_total' => 'integer',
        'usage_limit_per_user' => 'integer',
        'is_active' => 'boolean',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
