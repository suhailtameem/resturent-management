<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class StaffUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'staff_users';

    protected $fillable = [
        'username',
        'email',
        'password',
        'pin_code_hash',
        'role',
        'vehicle_type',
        'full_name',
        'phone',
        'is_active',
        'is_available',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'password' => 'hashed',
    ];

    protected $hidden = [
        'password',
        'pin_code_hash',
    ];

    public function assignedOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'courier_id');
    }

    public function cashCollections(): HasMany
    {
        return $this->hasMany(CourierCashCollection::class, 'courier_id');
    }

    public function cashSettlements(): HasMany
    {
        return $this->hasMany(CourierCashSettlement::class, 'courier_id');
    }
}
