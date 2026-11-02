<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerOptionGroupResource extends JsonResource
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
            'type' => $this->type,
            'option_category' => $this->option_category,
            'is_required' => (bool) $this->is_required,
            'is_reusable' => (bool) $this->is_reusable,
            'products_count' => $this->whenCounted('productRelations'),
            'options' => $this->whenLoaded('options', fn () => $this->options
                ->map(fn ($option) => [
                    'id' => (string) $option->id,
                    'name_ar' => $option->name_ar,
                    'name_en' => $option->name_en,
                    'description_ar' => $option->description_ar,
                    'description_en' => $option->description_en,
                    'price' => (float) $option->price,
                    'is_available' => (bool) $option->is_available,
                ])->values()),
        ];
    }
}
