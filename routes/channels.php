<?php

use App\Models\StaffUser;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('manager.orders', function (StaffUser $staffUser): bool {
    return $staffUser->is_active && $staffUser->role === 'super_admin';
}, ['guards' => ['staff_api']]);
