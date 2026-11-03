<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $order_id
 * @property string $original_amount
 * @property string $final_discount_given
 * @property Carbon|null $created_at
 */
class OfferRedemptionLog extends Model
{
    /** Only has created_at, no updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'offer_id',
        'order_id',
        'original_amount',
        'final_discount_given',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'original_amount' => 'decimal:2',
        'final_discount_given' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
