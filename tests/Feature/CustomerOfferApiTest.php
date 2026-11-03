<?php

use App\Models\Category;
use App\Models\CouponUsage;
use App\Models\Offer;
use App\Models\OfferCouponConfig;
use App\Models\OfferItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('customer can validate and apply valid coupon code', function () {
    $offer = Offer::create([
        'title' => 'كوبون ترحيبي للعملاء الجدد',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 20.00,
        'max_discount_cap' => 20.00,
        'min_order_amount' => 80.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(1),
    ]);

    OfferCouponConfig::create([
        'offer_id' => $offer->id,
        'code' => 'WELCOME20',
        'usage_limit_per_user' => 'once',
        'total_used_count' => 0,
    ]);

    $response = $this->postJson('/api/v1/customer/cart/apply-coupon', [
        'coupon_code' => 'welcome20',
        'customer_phone' => '0530697231',
        'cart_subtotal' => '95.00',
    ]);

    $response->assertOk()
        ->assertJson([
            'data' => [
                'is_valid' => true,
                'coupon_code' => 'WELCOME20',
                'discount_percentage' => 20.0,
                'raw_calculated_discount' => '19.00',
                'max_discount_cap' => '20.00',
                'final_discount_amount' => '19.00',
                'discounted_subtotal' => '76.00',
                'currency' => 'SAR',
            ],
        ]);
});

test('applying coupon fails when code does not exist', function () {
    $response = $this->postJson('/api/v1/customer/cart/apply-coupon', [
        'coupon_code' => 'NONEXISTENT',
        'customer_phone' => '0530697231',
        'cart_subtotal' => '100.00',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'data' => ['is_valid' => false],
            'code' => 'COUPON_NOT_FOUND',
        ]);
});

test('applying coupon fails when cart is below minimum order amount', function () {
    $offer = Offer::create([
        'title' => 'كوبون بحد أدنى',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 20.00,
        'max_discount_cap' => 20.00,
        'min_order_amount' => 80.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(1),
    ]);

    OfferCouponConfig::create([
        'offer_id' => $offer->id,
        'code' => 'MIN80',
        'usage_limit_per_user' => 'once',
    ]);

    $response = $this->postJson('/api/v1/customer/cart/apply-coupon', [
        'coupon_code' => 'MIN80',
        'customer_phone' => '0530697231',
        'cart_subtotal' => '50.00',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'data' => ['is_valid' => false],
            'code' => 'MINIMUM_ORDER_NOT_MET',
        ]);
});

test('applying coupon fails when customer usage limit exceeded', function () {
    $offer = Offer::create([
        'title' => 'كوبون لمرة واحدة',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 20.00,
        'max_discount_cap' => 20.00,
        'min_order_amount' => 0.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(1),
    ]);

    $config = OfferCouponConfig::create([
        'offer_id' => $offer->id,
        'code' => 'ONCEONLY',
        'usage_limit_per_user' => 'once',
    ]);

    $user = User::create([
        'name' => 'عميل تجريبي',
        'phone' => '0530697231',
        'password' => bcrypt('password123'),
    ]);

    $order = Order::create([
        'order_number' => 'ARW-'.rand(1000, 9999),
        'user_id' => $user->id,
        'customer_name' => 'عميل تجريبي',
        'customer_phone' => '0530697231',
        'order_status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'delivery_mode' => 'pickup',
        'subtotal_amount' => 50.00,
        'delivery_fee' => 0.00,
        'vat_amount' => 7.50,
        'total_final_amount' => 57.50,
    ]);

    // Record 1 usage for this customer phone
    CouponUsage::create([
        'coupon_config_id' => $config->id,
        'order_id' => $order->id,
        'customer_phone' => '0530697231',
        'discount_amount_applied' => 10.00,
        'created_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/customer/cart/apply-coupon', [
        'coupon_code' => 'ONCEONLY',
        'customer_phone' => '0530697231',
        'cart_subtotal' => '100.00',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'data' => ['is_valid' => false],
            'code' => 'COUPON_USAGE_LIMIT_EXCEEDED',
        ]);
});

test('customer can get menu discounted items with strikethrough pricing', function () {
    $category = Category::create([
        'name_ar' => 'ورق عنب ومقبلات',
        'name_en' => 'Appetizers',
        'slug' => 'appetizers-'.uniqid(),
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'ورق عنب بلدي بدبس الرمان',
        'base_price' => 40.00,
        'has_discount' => false,
        'discount_percentage' => 0.00,
        'discounted_price' => 40.00,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    $offer = Offer::create([
        'title' => 'خصم 20% على ورق العنب',
        'type' => 'auto_discount',
        'discount_scope' => 'specific_products',
        'discount_percentage' => 20.00,
        'max_discount_cap' => 15.00,
        'min_order_amount' => 0.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDay(),
    ]);

    OfferItem::create([
        'offer_id' => $offer->id,
        'product_id' => $product->id,
        'created_at' => now(),
    ]);

    $response = $this->getJson('/api/v1/customer/menu/discounted-items');

    $response->assertOk()
        ->assertJson([
            'data' => [
                [
                    'product_id' => $product->id,
                    'product_name' => 'ورق عنب بلدي بدبس الرمان',
                    'original_price' => '40.00',
                    'discounted_price' => '32.00',
                    'saved_amount' => '8.00',
                    'discount_badge' => 'خصم 20%',
                    'currency' => 'SAR',
                ],
            ],
        ]);
});
