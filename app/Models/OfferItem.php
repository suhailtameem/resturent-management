<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $product_id
 * @property string|null $custom_discount_price
 * @property Carbon|null $created_at
 */
class OfferItem extends Model
{
    /** Only has created_at, no updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'product_id',
        'custom_discount_price',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'custom_discount_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
