<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $offer_id
 * @property string $code
 * @property string $usage_limit_per_user once|twice|three_times|unlimited
 * @property int|null $total_usage_limit
 * @property int $total_used_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OfferCouponConfig extends Model
{
    protected $fillable = [
        'offer_id',
        'code',
        'usage_limit_per_user',
        'total_usage_limit',
        'total_used_count',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'total_usage_limit' => 'integer',
        'total_used_count' => 'integer',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_config_id');
    }

    /**
     * Return the max number of uses per user (null = unlimited).
     */
    public function getMaxUsesPerUser(): ?int
    {
        return match ($this->usage_limit_per_user) {
            'once' => 1,
            'twice' => 2,
            'three_times' => 3,
            'unlimited' => null,
            default => 1,
        };
    }

    /**
     * Return the Arabic label for usage_limit_per_user.
     */
    public function getUsageLimitLabel(): string
    {
        return match ($this->usage_limit_per_user) {
            'once' => 'مرة واحدة لكل عميل',
            'twice' => 'مرتان لكل عميل',
            'three_times' => 'ثلاث مرات لكل عميل',
            'unlimited' => 'غير محدود',
            default => $this->usage_limit_per_user,
        };
    }
}
