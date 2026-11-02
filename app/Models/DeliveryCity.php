<?php

namespace App\Models;

use Database\Factories\DeliveryCityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryCity extends Model
{
    /** @use HasFactory<DeliveryCityFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'service_status',
    ];

    public function neighborhoods(): HasMany
    {
        return $this->hasMany(DeliveryNeighborhood::class);
    }
}
