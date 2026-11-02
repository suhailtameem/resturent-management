<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerStoreSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'store_name_ar' => $this->store_name_ar,
            'store_name_en' => $this->store_name_en,
            'address' => $this->address,
            'support_phone' => $this->support_phone,
            'payment_methods' => [
                'cash_on_delivery_enabled' => $this->cash_on_delivery_enabled,
                'online_enabled' => $this->online_enabled,
            ],
            'auto_accept_prepaid_orders' => $this->auto_accept_prepaid_orders,
        ];
    }
}
