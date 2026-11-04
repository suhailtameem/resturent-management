<?php

namespace App\Http\Resources;

use App\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StaffUser
 */
class ManagerDriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeOrdersCount = $this->assignedOrders()
            ->whereIn('order_status', ['out_for_delivery', 'preparing', 'ready', 'ready_for_pickup'])
            ->count();

        return [
            'id' => (int) $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->full_name,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'is_available' => (bool) $this->is_active && (bool) $this->is_available && $activeOrdersCount < 5,
            'active_orders_count' => $activeOrdersCount,
            'vehicle_type' => $this->vehicle_type ?? 'دباب / سكوتر',
        ];
    }
}
