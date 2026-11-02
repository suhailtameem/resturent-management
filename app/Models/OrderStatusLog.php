<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'action_by_staff_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabelAr(): string
    {
        return match ($this->to_status) {
            'received', 'pending' => 'تم استلام الطلب',
            'pending_confirmation' => 'بانتظار التأكيد',
            'preparing' => 'قيد التحضير',
            'ready_for_pickup', 'ready' => 'جاهز للاستلام',
            'out_for_delivery' => 'في الطريق إليك',
            'delivered', 'completed' => 'تم التوصيل',
            'cancelled' => 'ملغي',
            'rejected' => 'مرفوض',
            default => (string) $this->to_status,
        };
    }

    public function statusLabelEn(): string
    {
        return match ($this->to_status) {
            'received', 'pending' => 'Order Received',
            'pending_confirmation' => 'Pending Confirmation',
            'preparing' => 'Preparing',
            'ready_for_pickup', 'ready' => 'Ready for Pickup',
            'out_for_delivery' => 'Out for Delivery',
            'delivered', 'completed' => 'Delivered',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->to_status)),
        };
    }
}
