<?php

namespace App\Http\Resources;

use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManagerProductResource extends JsonResource
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
            'category_id' => (string) $this->category_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'image_url' => ProductImageUrl::fromStoredValue($this->image_url),
            'base_price' => (float) $this->base_price,
            'has_discount' => (bool) $this->has_discount,
            'discount_percentage' => (float) $this->discount_percentage,
            'discounted_price' => (float) $this->discounted_price,
            'is_available' => (bool) $this->is_available,
            'is_featured' => (bool) $this->is_featured,
            'is_best_seller' => (bool) $this->is_best_seller,
            'badge_text_ar' => $this->badge_text_ar,
            'sort_order' => $this->sort_order,
            'sizes' => $this->whenLoaded('variants', fn () => $this->variants
                ->map(fn ($variant) => [
                    'id' => (string) $variant->id,
                    'template_id' => $variant->size_template_id === null ? null : (string) $variant->size_template_id,
                    'size_key' => $variant->size_key,
                    'name_ar' => $variant->name_ar,
                    'name_en' => $variant->name_en,
                    'piece_count' => $variant->piece_count,
                    'portion_description_ar' => $variant->portion_description_ar,
                    'portion_description_en' => $variant->portion_description_en,
                    'price' => (float) $variant->price,
                    'is_default' => (bool) $variant->is_default,
                ])->values()),
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups
                ->map(fn ($relation) => $relation->optionGroup)
                ->filter()
                ->values()
                ->map(fn ($group) => [
                    'id' => (string) $group->id,
                    'name_ar' => $group->name_ar,
                    'name_en' => $group->name_en,
                    'type' => $group->type,
                    'option_category' => $group->option_category,
                    'is_required' => (bool) $group->is_required,
                    'is_reusable' => (bool) $group->is_reusable,
                    'options' => $group->options
                        ->map(fn ($option) => [
                            'id' => (string) $option->id,
                            'name_ar' => $option->name_ar,
                            'name_en' => $option->name_en,
                            'description_ar' => $option->description_ar,
                            'description_en' => $option->description_en,
                            'price' => (float) $option->price,
                            'is_available' => (bool) $option->is_available,
                        ])->values(),
                ])),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
