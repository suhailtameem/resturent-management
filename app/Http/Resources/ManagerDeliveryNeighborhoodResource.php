<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerDeliveryNeighborhoodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'delivery_fee' => (float) $this->delivery_fee,
            'minimum_order_amount' => (float) $this->minimum_order_amount,
            'is_active' => $this->is_active,
        ];
    }
}
