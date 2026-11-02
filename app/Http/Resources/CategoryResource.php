<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'icon_code' => $this->icon_code,
            'display_order' => $this->sort_order,
        ];
    }
}
