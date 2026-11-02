<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    protected $fillable = ['user_id', 'title', 'message', 'type', 'destination', 'coupon_code', 'total_recipients', 'successful_sends', 'failed_sends', 'created_by', 'idempotency_key'];

    public function recipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }
}
