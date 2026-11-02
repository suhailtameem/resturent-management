<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerAddressResource extends JsonResource
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
            'title_ar' => $this->title_ar,
            'city_ar' => $this->city_ar,
            'district_ar' => $this->district_ar,
            'street_details' => $this->street_details_ar,
            'is_default' => (bool) $this->is_default,
        ];
    }
}
