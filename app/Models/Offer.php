<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string|null $image_path
 * @property string $type coupon|auto_discount
 * @property string $discount_scope cart|specific_products|category
 * @property int|null $category_id
 * @property string $discount_percentage
 * @property string $max_discount_cap
 * @property string $min_order_amount
 * @property bool $is_active
 * @property bool $is_ongoing
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Offer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'type',
        'discount_scope',
        'category_id',
        'discount_percentage',
        'max_discount_cap',
        'min_order_amount',
        'is_active',
        'is_ongoing',
        'starts_at',
        'ends_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'is_ongoing' => 'boolean',
        'discount_percentage' => 'decimal:2',
        'max_discount_cap' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function couponConfig(): HasOne
    {
        return $this->hasOne(OfferCouponConfig::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class);
    }

    public function redemptionLogs(): HasMany
    {
        return $this->hasMany(OfferRedemptionLog::class);
    }

    /**
     * Determine the offer's current status key.
     *
     * @return string active|scheduled|expired
     */
    public function getStatusKey(): string
    {
        $now = now();

        if (! $this->is_active) {
            return 'expired';
        }

        if ($this->starts_at > $now) {
            return 'scheduled';
        }

        if (! $this->is_ongoing && $this->ends_at && $this->ends_at < $now) {
            return 'expired';
        }

        return 'active';
    }

    /**
     * Return an Arabic label for the current status.
     */
    public function getStatusLabel(): string
    {
        return match ($this->getStatusKey()) {
            'active' => 'نشط الآن',
            'scheduled' => 'مجدول',
            default => 'منتهي',
        };
    }

    /**
     * Arabic label for type.
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'coupon' => 'كوبون خصم',
            'auto_discount' => 'خصم تلقائي على المنتجات',
            default => $this->type,
        };
    }

    /**
     * Arabic label for discount_scope.
     */
    public function getScopeLabel(): string
    {
        return match ($this->discount_scope) {
            'cart' => 'سلة المشتريات بالكامل',
            'specific_products' => 'منتجات محددة',
            'category' => $this->category?->name_ar ?? 'قسم كامل',
            default => $this->discount_scope,
        };
    }

    /**
     * Check whether the offer is valid and active right now.
     */
    public function isValidNow(): bool
    {
        return $this->getStatusKey() === 'active';
    }

    /**
     * Scope to only active, currently running offers.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActiveNow($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->where('is_ongoing', true)
                    ->orWhere('ends_at', '>=', $now);
            });
    }
}
