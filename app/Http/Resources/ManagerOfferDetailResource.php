<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Offer
 */
class ManagerOfferDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Offer $offer */
        $offer = $this->resource;

        $couponData = null;
        if ($offer->type === 'coupon' && $offer->relationLoaded('couponConfig') && $offer->couponConfig) {
            $cfg = $offer->couponConfig;
            $couponData = [
                'code' => $cfg->code,
                'usage_limit_per_user' => $cfg->usage_limit_per_user,
                'total_usage_limit' => $cfg->total_usage_limit,
                'total_used_count' => (int) $cfg->total_used_count,
            ];
        }

        $selectedProductIds = [];
        if ($offer->relationLoaded('items')) {
            $selectedProductIds = $offer->items->pluck('product_id')->values()->all();
        }

        return [
            'id' => (int) $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'image_url' => $offer->image_path ? Storage::disk('public')->url($offer->image_path) : null,
            'type' => $offer->type,
            'discount_scope' => $offer->discount_scope,
            'discount_percentage' => (float) $offer->discount_percentage,
            'max_discount_cap' => number_format((float) $offer->max_discount_cap, 2, '.', ''),
            'min_order_amount' => number_format((float) $offer->min_order_amount, 2, '.', ''),
            'is_active' => (bool) $offer->is_active,
            'is_ongoing' => (bool) $offer->is_ongoing,
            'starts_at' => $offer->starts_at?->toIso8601String(),
            'ends_at' => $offer->ends_at?->toIso8601String(),
            'coupon' => $couponData,
            'selected_product_ids' => $selectedProductIds,
            'selected_category_id' => $offer->category_id,
        ];
    }
}
