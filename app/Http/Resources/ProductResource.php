<?php

namespace App\Http\Resources;

use App\Models\ProductOption;
use App\Support\ProductImageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $groups = $this->resource->relationLoaded('optionGroups')
            ? $this->optionGroups
                ->map(fn ($relation) => $relation->optionGroup)
                ->filter()
                ->values()
            : collect();

        return [
            'id' => (string) $this->id,
            'category_id' => (string) $this->category_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'image_url' => ProductImageUrl::fromStoredValue($this->image_url),
            'is_featured' => (bool) $this->is_featured,
            'is_best_seller' => (bool) $this->is_best_seller,
            'badge_text_ar' => $this->badge_text_ar,
            'is_available' => (bool) $this->is_available,
            'price' => (float) ($this->has_discount && (float) $this->discounted_price > 0
                ? min((float) $this->base_price, (float) $this->discounted_price)
                : $this->base_price),
            'has_discount' => (bool) $this->has_discount,
            'discount_percentage' => (float) $this->discount_percentage,
            'original_price' => (float) $this->base_price,
            'sizes' => $this->whenLoaded('variants', fn () => $this->variants
                ->map(fn ($variant) => [
                    'id' => (string) $variant->id,
                    'name_ar' => $variant->name_ar,
                    'name_en' => $variant->name_en,
                    'price' => (float) ($this->has_discount
                        ? round((float) $variant->price * (100 - min(100, max(0, (float) $this->discount_percentage))) / 100, 2)
                        : $variant->price),
                    'piece_count' => $variant->piece_count,
                    'subtitle_ar' => $variant->portion_description_ar,
                    'is_recommended' => (bool) $variant->is_default,
                ])->values()),
            'flavor_options' => $groups === null ? [] : $groups
                ->filter(fn ($group) => $group->option_category === 'flavor')
                ->flatMap(fn ($group) => $group->options
                    ->where('is_available', true)
                    ->map(fn ($option) => $this->optionData($option)))
                ->values(),
            'add_ons' => $groups === null ? [] : $groups
                ->filter(fn ($group) => $group->option_category === 'add_on')
                ->flatMap(fn ($group) => $group->options
                    ->where('is_available', true)
                    ->map(fn ($option) => $this->optionData($option)))
                ->values(),
        ];
    }

    private function optionData(ProductOption $option): array
    {
        return [
            'id' => (string) $option->id,
            'name_ar' => $option->name_ar,
            'name_en' => $option->name_en,
            'description_ar' => $option->description_ar,
            'price' => (float) $option->price,
        ];
    }
}
