<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierCashCollection extends Model
{
    protected $fillable = [
        'order_id',
        'courier_id',
        'settlement_id',
        'amount',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'collected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'courier_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CourierCashSettlement::class, 'settlement_id');
    }
}
