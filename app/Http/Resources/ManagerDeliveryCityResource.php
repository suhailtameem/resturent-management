<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerDeliveryCityResource extends JsonResource
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
            'service_status' => $this->service_status,
            'neighborhoods' => ManagerDeliveryNeighborhoodResource::collection(
                $this->whenLoaded('neighborhoods')
            ),
        ];
    }
}
