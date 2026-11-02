<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerProductSizeResource extends JsonResource
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
            'size_key' => $this->size_key,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'piece_count' => $this->piece_count,
            'portion_description_ar' => $this->portion_description_ar,
            'portion_description_en' => $this->portion_description_en,
            'price' => (float) $this->price,
            'products_count' => $this->whenCounted('variants'),
        ];
    }
}
