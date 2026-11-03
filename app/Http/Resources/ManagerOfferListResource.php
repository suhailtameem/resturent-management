<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Offer
 */
class ManagerOfferListResource extends JsonResource
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
        $statusKey = $offer->getStatusKey();

        $couponData = null;
        if ($offer->type === 'coupon' && $offer->relationLoaded('couponConfig') && $offer->couponConfig) {
            $cfg = $offer->couponConfig;
            $couponData = [
                'code' => $cfg->code,
                'usage_limit_per_user' => $cfg->usage_limit_per_user,
                'usage_limit_label' => $cfg->getUsageLimitLabel(),
                'total_used_count' => (int) $cfg->total_used_count,
            ];
        }

        $sampleProduct = null;
        if ($offer->type === 'auto_discount') {
            $p = null;
            if ($offer->relationLoaded('items') && $offer->items->isNotEmpty()) {
                $firstItem = $offer->items->first();
                if ($firstItem->relationLoaded('product') && $firstItem->product) {
                    $p = $firstItem->product;
                }
            } elseif ($offer->discount_scope === 'category' && $offer->relationLoaded('category') && $offer->category) {
                if ($offer->category->relationLoaded('products') && $offer->category->products->isNotEmpty()) {
                    $p = $offer->category->products->first();
                }
            }

            if ($p) {
                $rawDiscount = (float) $p->base_price * ((float) $offer->discount_percentage / 100);
                $finalDiscount = min($rawDiscount, (float) $offer->max_discount_cap);
                $discountedPrice = round((float) $p->base_price - $finalDiscount, 2);
                $sampleProduct = [
                    'name' => $p->name_ar,
                    'original_price' => number_format((float) $p->base_price, 2, '.', ''),
                    'discounted_price' => number_format($discountedPrice, 2, '.', ''),
                ];
            }
        }

        return [
            'id' => (int) $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'type' => $offer->type,
            'type_label' => $offer->getTypeLabel(),
            'status' => $statusKey,
            'status_label' => $offer->getStatusLabel(),
            'discount_percentage' => (float) $offer->discount_percentage,
            'max_discount_cap' => number_format((float) $offer->max_discount_cap, 2, '.', ''),
            'min_order_amount' => number_format((float) $offer->min_order_amount, 2, '.', ''),
            'currency' => 'SAR',
            'coupon' => $couponData,
            'is_valid_in_cart' => $offer->isValidNow(),
            'is_ongoing' => (bool) $offer->is_ongoing,
            'starts_at' => $offer->starts_at?->toIso8601String(),
            'ends_at' => $offer->ends_at?->toIso8601String(),
            'target_scope' => $offer->discount_scope,
            'target_scope_label' => $offer->getScopeLabel(),
            'sample_product' => $sampleProduct,
        ];
    }
}
