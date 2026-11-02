<?php

namespace App\Models;

use Database\Factories\DeliveryNeighborhoodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryNeighborhood extends Model
{
    /** @use HasFactory<DeliveryNeighborhoodFactory> */
    use HasFactory;

    protected $fillable = [
        'delivery_city_id',
        'name',
        'delivery_fee',
        'minimum_order_amount',
        'is_active',
    ];

    protected $casts = [
        'delivery_fee' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(DeliveryCity::class, 'delivery_city_id');
    }
}
