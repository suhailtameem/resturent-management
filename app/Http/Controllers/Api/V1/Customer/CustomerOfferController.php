<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\OfferCouponConfig;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOfferController extends Controller
{
    /**
     * Validate and calculate a coupon discount for a cart.
     * No authentication required.
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'coupon_code' => ['required', 'string', 'max:64'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'cart_subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $code = strtoupper(trim((string) $validated['coupon_code']));
        $customerPhone = (string) $validated['customer_phone'];
        $cartSubtotal = (float) $validated['cart_subtotal'];

        // 1. Find the coupon config and its parent offer
        $couponConfig = OfferCouponConfig::query()
            ->where('code', $code)
            ->with('offer')
            ->first();

        if (! $couponConfig || ! $couponConfig->offer) {
            return $this->couponError('كود الكوبون المدخل غير صحيح', 'COUPON_NOT_FOUND');
        }

        $offer = $couponConfig->offer;

        // 2. Check offer is active and within date range
        if (! $offer->isValidNow()) {
            return $this->couponError('انتهت صلاحية هذا العرض الترويجي', 'OFFER_EXPIRED');
        }

        // 3. Check minimum order value
        if ($cartSubtotal < (float) $offer->min_order_amount) {
            $minFormatted = number_format((float) $offer->min_order_amount, 2, '.', '');

            return $this->couponError(
                "لم تبلغ قيمة السلة الحد الأدنى لتفعيل الكوبون (الحد الأدنى: {$minFormatted} ر.س)",
                'MINIMUM_ORDER_NOT_MET',
            );
        }

        // 4. Check per-customer usage limit
        $maxUsesPerUser = $couponConfig->getMaxUsesPerUser();
        if ($maxUsesPerUser !== null) {
            $usageCount = $couponConfig->usages()
                ->where('customer_phone', $customerPhone)
                ->count();

            if ($usageCount >= $maxUsesPerUser) {
                return $this->couponError(
                    'لقد قمت باستخدام هذا الكوبون مسبقاً، ولا يمكن تكراره لنفس رقم الجوال',
                    'COUPON_USAGE_LIMIT_EXCEEDED',
                );
            }
        }

        // 5. Check total usage limit
        if ($couponConfig->total_usage_limit !== null && $couponConfig->total_used_count >= $couponConfig->total_usage_limit) {
            return $this->couponError(
                'لقد قمت باستخدام هذا الكوبون مسبقاً، ولا يمكن تكراره لنفس رقم الجوال',
                'COUPON_USAGE_LIMIT_EXCEEDED',
            );
        }

        // 6. Calculate discount
        $discountPercentage = (float) $offer->discount_percentage;
        $maxDiscountCap = (float) $offer->max_discount_cap;

        $rawDiscount = $cartSubtotal * ($discountPercentage / 100);
        $finalDiscount = min($rawDiscount, $maxDiscountCap);
        $discountedSubtotal = $cartSubtotal - $finalDiscount;

        $finalDiscountFormatted = number_format($finalDiscount, 2, '.', '');

        return response()->json([
            'data' => [
                'is_valid' => true,
                'coupon_code' => $couponConfig->code,
                'discount_percentage' => $discountPercentage,
                'raw_calculated_discount' => number_format($rawDiscount, 2, '.', ''),
                'max_discount_cap' => number_format($maxDiscountCap, 2, '.', ''),
                'final_discount_amount' => $finalDiscountFormatted,
                'discounted_subtotal' => number_format($discountedSubtotal, 2, '.', ''),
                'currency' => 'SAR',
                'message' => "تم تطبيق كود الخصم بنجاح: خصم {$finalDiscountFormatted} ر.س",
            ],
        ]);
    }

    /**
     * List all products that currently have an active auto_discount offer applied.
     */
    public function discountedItems(): JsonResponse
    {
        $now = now();

        $activeAutoDiscounts = Offer::query()
            ->where('type', 'auto_discount')
            ->activeNow()
            ->with(['items.product', 'category.products'])
            ->get();

        /** @var array<int, array<string, mixed>> $productMap */
        $productMap = [];

        foreach ($activeAutoDiscounts as $offer) {
            $discountPct = (float) $offer->discount_percentage;
            $maxCap = (float) $offer->max_discount_cap;

            $products = match ($offer->discount_scope) {
                'specific_products' => $offer->items
                    ->filter(fn ($item) => $item->product !== null)
                    ->pluck('product'),
                'category' => $offer->category?->products ?? collect(),
                default => collect(),
            };

            foreach ($products as $product) {
                /** @var Product $product */
                if (! $product || ! $product->is_available) {
                    continue;
                }

                $originalPrice = (float) $product->base_price;
                $rawDiscount = $originalPrice * ($discountPct / 100);
                $finalDiscount = min($rawDiscount, $maxCap);
                $discountedPrice = round($originalPrice - $finalDiscount, 2);

                $candidate = [
                    'product_id' => (int) $product->id,
                    'product_name' => $product->name_ar,
                    'original_price' => number_format($originalPrice, 2, '.', ''),
                    'discounted_price' => number_format($discountedPrice, 2, '.', ''),
                    'saved_amount' => number_format($finalDiscount, 2, '.', ''),
                    'discount_badge' => 'خصم '.rtrim(rtrim(number_format($discountPct, 2, '.', ''), '0'), '.').'%',
                    'discount_percentage' => $discountPct,
                    'max_discount_cap' => number_format($maxCap, 2, '.', ''),
                    'offer_id' => (int) $offer->id,
                    'offer_title' => $offer->title,
                    'currency' => 'SAR',
                ];
                if (! isset($productMap[$product->id]) || (float) $candidate['saved_amount'] > (float) $productMap[$product->id]['saved_amount']) {
                    $productMap[$product->id] = $candidate;
                }
            }
        }

        return response()->json([
            'data' => array_values($productMap),
        ]);
    }

    /**
     * Return a 422 response for a coupon validation failure.
     */
    private function couponError(string $message, string $code): JsonResponse
    {
        return response()->json([
            'data' => ['is_valid' => false],
            'message' => $message,
            'code' => $code,
        ], 422);
    }
}
