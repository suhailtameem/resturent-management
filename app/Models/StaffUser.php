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
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
