<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Exceptions\CustomerApiException;
use App\Http\Resources\ManagerOrderSummaryResource;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CouponUsage;
use App\Models\CustomerAddress;
use App\Models\DeliveryNeighborhood;
use App\Models\Offer;
use App\Models\OfferCouponConfig;
use App\Models\OfferRedemptionLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\StoreOperation;
use App\Models\StoreSettings;
use App\Models\User;
use App\Support\ProductImageUrl;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerCheckoutService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createQuote(User $user, array $input): array
    {
        $totals = $this->calculate($user, $input);
        $quoteId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10);
        $quote = [
            'quote_id' => $quoteId,
            'expires_at' => $expiresAt->toIso8601String(),
            ...$this->publicTotals($totals),
        ];

        Cache::put('customer_quote:'.$quoteId, [
            'user_id' => $user->id,
            'input' => $input,
            'totals' => $totals,
        ], $expiresAt);

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{order: Order, created: bool}
     */
    public function placeOrder(User $user, array $input): array
    {
        $existingOrder = Order::query()
            ->whereBelongsTo($user)
            ->where('idempotency_key', $input['idempotency_key'])
            ->first();

        if ($existingOrder) {
            return ['order' => $existingOrder->load(['items.options', 'deliveryAddress', 'statusLogs', 'coupon', 'couponUsages.couponConfig']), 'created' => false];
        }

        try {
            $result = DB::transaction(function () use ($user, $input): array {
                $quote = Cache::get('customer_quote:'.$input['quote_id']);

                if (! $quote || $quote['user_id'] !== $user->id) {
                    throw new CustomerApiException(
                        'The quote is missing or has expired. Request a new quote.',
                        'QUOTE_EXPIRED',
                        409,
                    );
                }

                $totals = $this->calculate($user, $quote['input'], true);

                $operation = StoreOperation::query()
                    ->whereKey(StoreOperation::current()->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->refreshExpiredPause();
                $scheduledWindow = $this->resolveScheduledWindow(
                    $input,
                    $operation->timezone ?: config('app.timezone'),
                );
                if (! $operation->is_open) {
                    throw new CustomerApiException(
                        'The store is not currently accepting orders.',
                        'STORE_CLOSED',
                        409,
                    );
                }
                $this->ensureOrderCapacity($operation);

                $settings = StoreSettings::current();
                $paymentMethod = $input['payment_method'] ?? 'cod';
                if ($paymentMethod === 'cod' && ! $settings->cash_on_delivery_enabled) {
                    throw new CustomerApiException(
                        'Cash on delivery is not currently available.',
                        'PAYMENT_METHOD_UNAVAILABLE',
                        409,
                        ['payment_method' => ['Cash on delivery is disabled.']],
                    );
                }
                if ($paymentMethod === 'online' && ! $settings->online_enabled) {
                    throw new CustomerApiException(
                        'Online payment is not currently available.',
                        'PAYMENT_METHOD_UNAVAILABLE',
                        409,
                        ['payment_method' => ['Online payment is disabled.']],
                    );
                }

                if ($totals !== $quote['totals']) {
                    throw new CustomerApiException(
                        'The cart or current prices have changed. Request a new quote.',
                        'QUOTE_EXPIRED',
                        409,
                    );
                }

                $order = Order::create([
                    'order_number' => 'ORD-'.Str::upper(Str::random(12)),
                    'user_id' => $user->id,
                    'customer_name' => $user->full_name ?? $user->name,
                    'customer_phone' => $input['contact_phone'] ?? $user->phone,
                    'order_status' => 'received',
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'pending',
                    'subtotal_amount' => $totals['subtotal'],
                    'product_discount_amount' => $totals['product_discount'],
                    'coupon_id' => $totals['coupon']['id'] ?? null,
                    'coupon_discount_amount' => $totals['coupon_discount'],
                    'delivery_fee' => $totals['delivery_fee'],
                    'vat_tax_amount' => 0,
                    'total_final_amount' => $totals['total'],
                    'estimated_prep_time_minutes' => $totals['estimated_prep_time_minutes'],
                    'chef_special_notes' => $input['special_notes'] ?? null,
                    'delivery_mode' => $totals['delivery_mode'],
                    'scheduled_start_at' => $scheduledWindow['start'],
                    'scheduled_end_at' => $scheduledWindow['end'],
                    'delivery_address_id' => $totals['delivery_address_id'] ?? null,
                    'idempotency_key' => $input['idempotency_key'],
                ]);

                foreach ($totals['items'] as $itemData) {
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $itemData['product_id'],
                        'variant_id' => $itemData['size_id'],
                        'product_name_ar_snapshot' => $itemData['product_name_ar'],
                        'product_name_en_snapshot' => $itemData['product_name_en'],
                        'size_name_ar_snapshot' => $itemData['size_name_ar'],
                        'size_name_en_snapshot' => $itemData['size_name_en'],
                        'piece_count_snapshot' => $itemData['piece_count'],
                        'image_url_snapshot' => $itemData['image_url'],
                        'unit_price' => $itemData['unit_price'],
                        'quantity' => $itemData['quantity'],
                        'line_total' => $itemData['line_total'],
                        'special_instructions' => $itemData['special_instructions'],
                    ]);

                    $orderItem->options()->createMany($itemData['options']);
                }

                collect($totals['items'])
                    ->filter(fn (array $itemData) => ! empty($itemData['auto_offer_id']) && (float) $itemData['auto_offer_discount'] > 0)
                    ->groupBy('auto_offer_id')
                    ->each(function ($offerItems, $offerId) use ($order): void {
                        OfferRedemptionLog::create([
                            'offer_id' => (int) $offerId,
                            'order_id' => $order->id,
                            'original_amount' => $offerItems->sum('auto_offer_original_amount'),
                            'final_discount_given' => $offerItems->sum('auto_offer_discount'),
                        ]);
                    });

                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'to_status' => 'received',
                    'notes' => 'Order placed by customer.',
                ]);

                if (isset($totals['coupon']['id'])) {
                    CouponRedemption::create([
                        'coupon_id' => $totals['coupon']['id'],
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                    ]);
                }

                if (isset($totals['offer_coupon']['id'])) {
                    $couponConfig = OfferCouponConfig::query()->lockForUpdate()->findOrFail($totals['offer_coupon']['id']);
                    CouponUsage::create([
                        'coupon_config_id' => $couponConfig->id,
                        'order_id' => $order->id,
                        'customer_id' => $user->id,
                        'customer_phone' => (string) ($input['contact_phone'] ?? $user->phone ?? ''),
                        'discount_amount_applied' => $totals['coupon_discount'],
                    ]);
                    $couponConfig->increment('total_used_count');
                    OfferRedemptionLog::create([
                        'offer_id' => $couponConfig->offer_id,
                        'order_id' => $order->id,
                        'original_amount' => $totals['coupon_eligible_subtotal'],
                        'final_discount_given' => $totals['coupon_discount'],
                    ]);
                }

                return ['order' => $order->load(['items.options', 'deliveryAddress', 'statusLogs', 'coupon', 'couponUsages.couponConfig']), 'created' => true];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existingOrder = Order::query()
                ->whereBelongsTo($user)
                ->where('idempotency_key', $input['idempotency_key'])
                ->first();

            if (! $existingOrder) {
                throw $exception;
            }

            return ['order' => $existingOrder->load(['items.options', 'deliveryAddress', 'statusLogs']), 'created' => false];
        }

        Cache::forget('customer_quote:'.$input['quote_id']);

        if ($result['created']) {
            $orderSummary = (new ManagerOrderSummaryResource($result['order']))->resolve();
            OrderCreated::dispatch($orderSummary);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function calculate(User $user, array $input, bool $lockCoupon = false): array
    {
        $operation = StoreOperation::current()->refreshExpiredPause();
        if (! $operation->is_open) {
            throw new CustomerApiException(
                'The store is not currently accepting orders.',
                'STORE_CLOSED',
                409,
            );
        }

        $settings = StoreSettings::current();
        $paymentMethod = $input['payment_method'] ?? 'cod';
        if ($paymentMethod === 'cod' && ! $settings->cash_on_delivery_enabled) {
            throw new CustomerApiException(
                'Cash on delivery is not currently available.',
                'PAYMENT_METHOD_UNAVAILABLE',
                409,
                ['payment_method' => ['Cash on delivery is disabled.']],
            );
        }
        if ($paymentMethod === 'online' && ! $settings->online_enabled) {
            throw new CustomerApiException(
                'Online payment is not currently available.',
                'PAYMENT_METHOD_UNAVAILABLE',
                409,
                ['payment_method' => ['Online payment is disabled.']],
            );
        }

        $this->ensureOrderCapacity($operation);

        $deliveryMode = $input['delivery_mode'] ?? 'pickup';
        $deliveryFeeCents = 0;
        $deliveryAddressData = null;
        $deliveryAddressId = null;
        $deliveryNeighborhoodData = null;
        $neighborhood = null;

        if ($deliveryMode === 'delivery') {
            $addressId = $input['address_id'] ?? null;
            if (! $addressId) {
                throw new CustomerApiException(
                    'A delivery address is required for delivery orders.',
                    'ADDRESS_REQUIRED',
                    422,
                    ['address_id' => ['Delivery address is required.']],
                );
            }

            $address = CustomerAddress::query()
                ->whereBelongsTo($user)
                ->whereKey($addressId)
                ->first();

            if (! $address) {
                throw new CustomerApiException(
                    'The selected delivery address was not found.',
                    'ADDRESS_NOT_FOUND',
                    422,
                    ['address_id' => ['The selected address does not belong to your account.']],
                );
            }

            if (! empty($input['neighborhood_id'])) {
                $neighborhood = DeliveryNeighborhood::query()
                    ->with('city')
                    ->whereKey($input['neighborhood_id'])
                    ->first();
            }

            if (! $neighborhood && ! empty($address->district_ar)) {
                $neighborhood = DeliveryNeighborhood::query()
                    ->with('city')
                    ->where('name', $address->district_ar)
                    ->first();
            }

            if (! $neighborhood) {
                throw new CustomerApiException(
                    'Delivery service is not available for this address.',
                    'DELIVERY_ZONE_UNAVAILABLE',
                    422,
                    ['address_id' => ['The neighborhood is outside our delivery coverage.']],
                );
            }

            if (! $neighborhood->is_active || $neighborhood->city?->service_status !== 'active') {
                throw new CustomerApiException(
                    'Delivery is temporarily unavailable in this neighborhood.',
                    'DELIVERY_ZONE_UNAVAILABLE',
                    422,
                    ['address_id' => ['Delivery service to this neighborhood is currently disabled.']],
                );
            }

            $deliveryFeeCents = $this->toCents($neighborhood->delivery_fee);
            $deliveryAddressId = $address->id;
            $deliveryAddressData = [
                'id' => (string) $address->id,
                'title_ar' => $address->title_ar,
                'title_en' => $address->title_en,
                'city_ar' => $address->city_ar,
                'city_en' => $address->city_en,
                'district_ar' => $address->district_ar,
                'district_en' => $address->district_en,
                'street_details' => $address->street_details_ar,
                'latitude' => $address->latitude ? (float) $address->latitude : null,
                'longitude' => $address->longitude ? (float) $address->longitude : null,
                'notes' => $address->notes,
            ];
            $deliveryNeighborhoodData = [
                'id' => (string) $neighborhood->id,
                'name' => $neighborhood->name,
                'delivery_fee' => (float) $neighborhood->delivery_fee,
                'minimum_order_amount' => (float) $neighborhood->minimum_order_amount,
            ];
        }

        $subtotalCents = 0;
        $productDiscountCents = 0;
        $items = [];
        $autoOffers = Offer::query()->where('type', 'auto_discount')->activeNow()->with('items')->get();
        $offerCapRemaining = $autoOffers->mapWithKeys(fn (Offer $offer) => [$offer->id => $this->toCents($offer->max_discount_cap)])->all();

        foreach ($input['items'] as $index => $item) {
            $product = Product::query()
                ->with(['variants', 'optionGroups.optionGroup.options'])
                ->whereKey($item['product_id'])
                ->where('is_available', true)
                ->whereHas('category', fn ($query) => $query->where('is_active', true))
                ->first();

            if (! $product) {
                throw new CustomerApiException(
                    'One or more products are no longer available.',
                    'PRODUCT_UNAVAILABLE',
                    422,
                    ["items.{$index}.product_id" => ['The selected product is unavailable.']],
                );
            }

            $variant = null;
            if ($product->variants->isNotEmpty()) {
                $variant = $product->variants->firstWhere('id', $item['size_id'] ?? null);
                if (! $variant) {
                    throw new CustomerApiException(
                        'Select a valid size for each product.',
                        'INVALID_SELECTION',
                        422,
                        ["items.{$index}.size_id" => ['A valid size is required.']],
                    );
                }
            } elseif (! empty($item['size_id'])) {
                throw new CustomerApiException(
                    'The selected size does not belong to this product.',
                    'INVALID_SELECTION',
                    422,
                    ["items.{$index}.size_id" => ['The selected size is invalid.']],
                );
            }

            $selectedOptionIds = array_values(array_filter([
                $item['flavor_id'] ?? null,
                ...($item['addon_ids'] ?? []),
            ]));
            $options = ProductOption::query()
                ->with('group')
                ->whereIn('id', $selectedOptionIds)
                ->where('is_available', true)
                ->whereHas('group.productRelations', fn ($query) => $query->where('product_id', $product->id))
                ->get()
                ->keyBy('id');

            if ($options->count() !== count(array_unique($selectedOptionIds))) {
                throw new CustomerApiException(
                    'One or more selected options are unavailable for this product.',
                    'INVALID_SELECTION',
                    422,
                    ["items.{$index}.addon_ids" => ['A selected option is invalid.']],
                );
            }

            $flavor = $item['flavor_id'] ?? null;
            if ($flavor && $options[$flavor]->group->option_category !== 'flavor') {
                throw new CustomerApiException('The selected flavor is invalid.', 'INVALID_SELECTION', 422);
            }

            foreach ($item['addon_ids'] ?? [] as $addonId) {
                if ($options[$addonId]->group->option_category !== 'add_on') {
                    throw new CustomerApiException('The selected add-on is invalid.', 'INVALID_SELECTION', 422);
                }
            }

            foreach ($product->optionGroups as $relation) {
                $group = $relation->optionGroup;
                if (! $group) {
                    continue;
                }

                $groupSelections = $options->filter(fn ($option) => $option->group_id === $group->id)->count();
                if ($group->is_required && $groupSelections === 0) {
                    throw new CustomerApiException(
                        'Choose all required product options.',
                        'INVALID_SELECTION',
                        422,
                        ["items.{$index}.addon_ids" => ["An option from {$group->name_ar} is required."]],
                    );
                }

                if ($group->type === 'single' && $groupSelections > 1) {
                    throw new CustomerApiException(
                        'Choose only one option from each single-choice group.',
                        'INVALID_SELECTION',
                        422,
                    );
                }
            }

            $basePriceCents = $variant
                ? $this->toCents($variant->price)
                : $this->toCents($product->base_price);
            $discountedBaseCents = $basePriceCents;
            if ($product->has_discount) {
                $discountedBaseCents = $variant
                    ? (int) round($basePriceCents * (100 - min(100, max(0, (float) $product->discount_percentage))) / 100)
                    : min($basePriceCents, $this->toCents($product->discounted_price) ?: $basePriceCents);
            }

            $quantity = (int) $item['quantity'];
            $offerBaseCents = $discountedBaseCents;
            $matchedOfferId = null;
            $matchedOfferDiscountCents = 0;
            foreach ($autoOffers as $offer) {
                $matches = match ($offer->discount_scope) {
                    'specific_products' => $offer->items->contains('product_id', $product->id),
                    'category' => (int) $offer->category_id === (int) $product->category_id,
                    default => false,
                };
                if (! $matches || $offerBaseCents <= 0) {
                    continue;
                }
                $offerDiscount = min(
                    (int) round($offerBaseCents * (float) $offer->discount_percentage / 100) * $quantity,
                    $offerCapRemaining[$offer->id] ?? 0,
                );
                if ($offerDiscount > $matchedOfferDiscountCents) {
                    $matchedOfferId = $offer->id;
                    $matchedOfferDiscountCents = $offerDiscount;
                }
            }
            if ($matchedOfferId !== null && $matchedOfferDiscountCents > 0) {
                $offerCapRemaining[$matchedOfferId] -= $matchedOfferDiscountCents;
                $offerBaseCents -= intdiv($matchedOfferDiscountCents, $quantity);
            }

            $optionPriceCents = $options->sum(fn (ProductOption $option) => $this->toCents($option->price));
            $unitPriceCents = $offerBaseCents + $optionPriceCents;
            $autoOfferRemainderCents = $matchedOfferDiscountCents % $quantity;
            $lineTotalCents = $unitPriceCents * $quantity - $autoOfferRemainderCents;
            $subtotalCents += ($basePriceCents + $optionPriceCents) * $quantity;
            $productDiscountCents += ($basePriceCents - $discountedBaseCents) * $quantity + $matchedOfferDiscountCents;

            $items[] = [
                'product_id' => $product->id,
                'size_id' => $variant?->id,
                'product_name_ar' => $product->name_ar,
                'product_name_en' => $product->name_en,
                'size_name_ar' => $variant?->name_ar,
                'size_name_en' => $variant?->name_en,
                'piece_count' => $variant?->piece_count,
                'image_url' => ProductImageUrl::fromStoredValue($product->image_url),
                'quantity' => $quantity,
                'unit_price' => $this->fromCents($unitPriceCents),
                'line_total' => $this->fromCents($lineTotalCents),
                'auto_offer_id' => $matchedOfferId,
                'auto_offer_original_amount' => $this->fromCents($discountedBaseCents * $quantity),
                'auto_offer_discount' => $this->fromCents($matchedOfferDiscountCents),
                'special_instructions' => $item['special_instructions'] ?? null,
                'options' => $options->map(fn (ProductOption $option) => [
                    'option_id' => $option->id,
                    'option_name_ar_snapshot' => $option->name_ar,
                    'option_name_en_snapshot' => $option->name_en,
                    'option_price_snapshot' => $option->price,
                ])->values()->all(),
            ];
        }

        $coupon = null;
        $offerCoupon = null;
        $couponDiscountCents = 0;
        if (! empty($input['coupon_code'])) {
            $offerCouponQuery = OfferCouponConfig::query()
                ->where('code', Str::upper($input['coupon_code']))
                ->with('offer');
            if ($lockCoupon) {
                $offerCouponQuery->lockForUpdate();
            }
            $offerCouponConfig = $offerCouponQuery->first();

            if ($offerCouponConfig) {
                $offer = $offerCouponConfig->offer;
                $eligibleSubtotalCents = $subtotalCents - $productDiscountCents;
                $maxUses = $offerCouponConfig->getMaxUsesPerUser();
                $usageQuery = $offerCouponConfig->usages();
                if ($offerCouponConfig->total_usage_limit !== null && $offerCouponConfig->total_used_count >= $offerCouponConfig->total_usage_limit) {
                    throw new CustomerApiException('The coupon usage limit has been reached.', 'COUPON_INVALID', 422);
                }
                if ($maxUses !== null && (clone $usageQuery)->where('customer_id', $user->id)->count() >= $maxUses) {
                    throw new CustomerApiException('The coupon usage limit for this customer has been reached.', 'COUPON_INVALID', 422);
                }
                if (! $offer || ! $offer->isValidNow() || $eligibleSubtotalCents < $this->toCents($offer->min_order_amount)) {
                    throw new CustomerApiException('The coupon is invalid, expired, or its minimum order was not met.', 'COUPON_INVALID', 422);
                }
                $couponDiscountCents = min(
                    (int) round($eligibleSubtotalCents * (float) $offer->discount_percentage / 100),
                    $this->toCents($offer->max_discount_cap),
                    $eligibleSubtotalCents,
                );
                $coupon = ['code' => $offerCouponConfig->code, 'is_valid' => true];
                $offerCoupon = ['id' => $offerCouponConfig->id];
            } else {
                $couponQuery = Coupon::query()
                    ->where('code', Str::upper($input['coupon_code']))
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', today())
                    ->whereDate('end_date', '>=', today());
                if ($lockCoupon) {
                    $couponQuery->lockForUpdate();
                }
                $couponModel = $couponQuery->first();

                if (! $couponModel) {
                    throw new CustomerApiException('The coupon is invalid or expired.', 'COUPON_INVALID', 422);
                }

                $eligibleSubtotalCents = $subtotalCents - $productDiscountCents;
                if ($eligibleSubtotalCents < $this->toCents($couponModel->min_order_value)) {
                    throw new CustomerApiException('The minimum order amount for this coupon was not met.', 'COUPON_INVALID', 422);
                }

                if ($productDiscountCents > 0 && ! $couponModel->allow_combine_with_product_discount) {
                    throw new CustomerApiException('This coupon cannot be combined with product discounts.', 'COUPON_INVALID', 422);
                }

                $redemptions = CouponRedemption::query()->where('coupon_id', $couponModel->id);
                if ($couponModel->usage_limit_total !== null && (clone $redemptions)->count() >= $couponModel->usage_limit_total) {
                    throw new CustomerApiException('The coupon usage limit has been reached.', 'COUPON_INVALID', 422);
                }
                if ((clone $redemptions)->where('user_id', $user->id)->count() >= $couponModel->usage_limit_per_user) {
                    throw new CustomerApiException('The coupon usage limit for this customer has been reached.', 'COUPON_INVALID', 422);
                }

                $discountValue = (float) $couponModel->discount_value;
                $couponDiscountCents = $couponModel->discount_type === 'percentage'
                    ? (int) round($eligibleSubtotalCents * $discountValue / 100)
                    : $this->toCents($discountValue);
                if ($couponModel->maximum_discount_amount !== null) {
                    $couponDiscountCents = min(
                        $couponDiscountCents,
                        $this->toCents($couponModel->maximum_discount_amount),
                    );
                }
                $couponDiscountCents = min($couponDiscountCents, $eligibleSubtotalCents);
                $coupon = ['id' => $couponModel->id, 'code' => $couponModel->code, 'is_valid' => true];
            }
        }

        if ($deliveryMode === 'delivery' && $neighborhood) {
            $eligibleSubtotalCents = $subtotalCents - $productDiscountCents;
            if ($eligibleSubtotalCents < $this->toCents($neighborhood->minimum_order_amount)) {
                throw new CustomerApiException(
                    "The minimum order amount for delivery to {$neighborhood->name} is {$neighborhood->minimum_order_amount} SAR.",
                    'MINIMUM_ORDER_NOT_MET',
                    422,
                    ['subtotal' => ["The minimum order amount for delivery to {$neighborhood->name} is {$neighborhood->minimum_order_amount} SAR."]],
                );
            }
        }

        $finalTotalCents = max(0, $subtotalCents - $productDiscountCents - $couponDiscountCents + $deliveryFeeCents);

        return [
            'delivery_mode' => $deliveryMode,
            'delivery_address' => $deliveryAddressData,
            'delivery_address_id' => $deliveryAddressId,
            'delivery_neighborhood' => $deliveryNeighborhoodData,
            'items' => $items,
            'subtotal' => $this->fromCents($subtotalCents),
            'product_discount' => $this->fromCents($productDiscountCents),
            'coupon_discount' => $this->fromCents($couponDiscountCents),
            'delivery_fee' => $this->fromCents($deliveryFeeCents),
            'tax_amount' => 0.0,
            'total' => $this->fromCents($finalTotalCents),
            'currency' => 'SAR',
            ...$settings->currencyData(),
            'estimated_prep_time_minutes' => $operation->estimatedPrepTime(),
            'coupon' => $coupon,
            'offer_coupon' => $offerCoupon,
            'coupon_eligible_subtotal' => $this->fromCents($subtotalCents - $productDiscountCents),
        ];
    }

    private function ensureOrderCapacity(StoreOperation $operation): void
    {
        if (! $operation->is_open) {
            throw new CustomerApiException(
                'The store is not currently accepting orders.',
                'STORE_CLOSED',
                409,
            );
        }

        $hourStart = now($operation->timezone)->startOfHour()->utc();
        $hourEnd = $hourStart->copy()->addHour();
        $ordersThisHour = Order::query()
            ->where('created_at', '>=', $hourStart)
            ->where('created_at', '<', $hourEnd)
            ->count();

        if ($ordersThisHour >= $operation->orders_per_hour_limit) {
            throw new CustomerApiException(
                'The store has reached its hourly order capacity.',
                'ORDER_CAPACITY_REACHED',
                409,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    private function resolveScheduledWindow(array $input, string $timezone): array
    {
        if (empty($input['scheduled_day'])) {
            return ['start' => null, 'end' => null];
        }

        $date = now($timezone)->startOfDay();
        if ($input['scheduled_day'] === 'tomorrow') {
            $date->addDay();
        }

        return [
            'start' => Carbon::parse(
                $date->toDateString().' '.$input['scheduled_start_time'],
                $timezone,
            )->utc(),
            'end' => Carbon::parse(
                $date->toDateString().' '.$input['scheduled_end_time'],
                $timezone,
            )->utc(),
        ];
    }

    private function toCents(string|float|int $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function fromCents(int $amount): float
    {
        return round($amount / 100, 2);
    }

    /**
     * @param  array<string, mixed>  $totals
     * @return array<string, mixed>
     */
    private function publicTotals(array $totals): array
    {
        if (isset($totals['coupon']['id'])) {
            unset($totals['coupon']['id']);
        }
        unset($totals['offer_coupon'], $totals['coupon_eligible_subtotal']);
        unset($totals['delivery_address_id']);

        return $totals;
    }
}
