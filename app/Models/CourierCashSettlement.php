<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierCashSettlement extends Model
{
    protected $fillable = [
        'courier_id',
        'reconciled_by_staff_user_id',
        'orders_count',
        'amount',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'orders_count' => 'integer',
            'amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'courier_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'reconciled_by_staff_user_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CourierCashCollection::class, 'settlement_id');
    }
}
