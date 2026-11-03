<?php

use App\Models\Category;
use App\Models\Offer;
use App\Models\OfferCouponConfig;
use App\Models\OfferItem;
use App\Models\Product;
use App\Models\StaffUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('createOfferManagerStaff')) {
    function createOfferManagerStaff(array $attributes = []): StaffUser
    {
        return StaffUser::create(array_merge([
            'username' => 'manager_'.uniqid(),
            'email' => 'manager_'.uniqid().'@example.test',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'full_name' => 'Manager User',
            'phone' => '+966500000000',
            'is_active' => true,
        ], $attributes));
    }
}

if (! function_exists('createOfferCategory')) {
    function createOfferCategory(array $attributes = []): Category
    {
        return Category::create(array_merge([
            'name_ar' => 'قسم الفتات والمقبلات',
            'name_en' => 'Appetizers',
            'slug' => 'appetizers-'.uniqid(),
            'sort_order' => 1,
            'is_active' => true,
        ], $attributes));
    }
}

if (! function_exists('createOfferProduct')) {
    function createOfferProduct(Category $category, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name_ar' => 'ورق عنب بلدي بدبس الرمان',
            'name_en' => 'Stuffed Vine Leaves',
            'base_price' => 40.00,
            'has_discount' => false,
            'discount_percentage' => 0.00,
            'discounted_price' => 40.00,
            'is_available' => true,
            'sort_order' => 1,
        ], $attributes));
    }
}

test('manager can get offers stats and KPI counters', function () {
    $manager = createOfferManagerStaff();

    // 1 Active offer
    Offer::create([
        'title' => 'عرض نشط',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 20.00,
        'max_discount_cap' => 20.00,
        'min_order_amount' => 50.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(2),
    ]);

    // 1 Scheduled offer
    Offer::create([
        'title' => 'عرض مجدول',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 15.00,
        'max_discount_cap' => 30.00,
        'min_order_amount' => 60.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->addDays(5),
    ]);

    // 1 Expired offer (is_active = false)
    Offer::create([
        'title' => 'عرض منتهي',
        'type' => 'auto_discount',
        'discount_scope' => 'cart',
        'discount_percentage' => 10.00,
        'max_discount_cap' => 15.00,
        'min_order_amount' => 0.00,
        'is_active' => false,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(10),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->getJson('/api/v1/manager/offers/stats');

    $response->assertOk()
        ->assertJson([
            'data' => [
                'active_offers_count' => 1,
                'scheduled_offers_count' => 1,
                'expired_offers_count' => 1,
                'total_offers_count' => 3,
                'currency' => 'SAR',
                'unified_rules_enabled' => true,
            ],
        ]);
});

test('manager can list offers with filters and search', function () {
    $manager = createOfferManagerStaff();
    $category = createOfferCategory();
    $product = createOfferProduct($category);

    $offer1 = Offer::create([
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
        'offer_id' => $offer1->id,
        'code' => 'WELCOME20',
        'usage_limit_per_user' => 'once',
        'total_used_count' => 5,
    ]);

    $offer2 = Offer::create([
        'title' => 'خصم فتة ورق عنب',
        'type' => 'auto_discount',
        'discount_scope' => 'specific_products',
        'discount_percentage' => 15.00,
        'max_discount_cap' => 15.00,
        'min_order_amount' => 0.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDays(1),
    ]);
    OfferItem::create([
        'offer_id' => $offer2->id,
        'product_id' => $product->id,
    ]);

    $token = $manager->createToken('manager')->plainTextToken;

    // List all
    $resAll = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/manager/offers');
    $resAll->assertOk()
        ->assertJsonCount(2, 'data');

    // Filter by type=coupon
    $resCoupon = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/manager/offers?type=coupon');
    $resCoupon->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.coupon.code', 'WELCOME20');

    // Filter by search=WELCOME
    $resSearch = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/manager/offers?search=WELCOME');
    $resSearch->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $offer1->id);
});

test('manager can show single offer details', function () {
    $manager = createOfferManagerStaff();
    $category = createOfferCategory();
    $product = createOfferProduct($category);

    $offer = Offer::create([
        'title' => 'خصم بوكسات',
        'type' => 'auto_discount',
        'discount_scope' => 'specific_products',
        'discount_percentage' => 25.00,
        'max_discount_cap' => 30.00,
        'min_order_amount' => 0.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDay(),
    ]);
    OfferItem::create([
        'offer_id' => $offer->id,
        'product_id' => $product->id,
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->getJson("/api/v1/manager/offers/{$offer->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $offer->id)
        ->assertJsonPath('data.type', 'auto_discount')
        ->assertJsonPath('data.selected_product_ids', [$product->id]);
});

test('manager can create a new coupon offer', function () {
    $manager = createOfferManagerStaff();

    $payload = [
        'title' => 'خصم الجمعة المباركة',
        'description' => 'خصم خاص على جميع الطلبات أيام الجمعة',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 20.0,
        'max_discount_cap' => 25.00,
        'min_order_amount' => 90.00,
        'is_active' => true,
        'is_ongoing' => false,
        'starts_at' => Carbon::now()->addDay()->toIso8601String(),
        'ends_at' => Carbon::now()->addDays(2)->toIso8601String(),
        'coupon' => [
            'code' => 'jumah20',
            'usage_limit_per_user' => 'once',
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->postJson('/api/v1/manager/offers', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.code', 'JUMAH20')
        ->assertJsonPath('data.discount_percentage', 20)
        ->assertJsonPath('data.max_discount_cap', '25.00')
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('message', 'تم إنشاء ونشر العرض الترويجي بنجاح');

    $this->assertDatabaseHas('offer_coupon_configs', [
        'code' => 'JUMAH20',
        'usage_limit_per_user' => 'once',
    ]);
});

test('manager can create auto_discount offer with specific products', function () {
    $manager = createOfferManagerStaff();
    $category = createOfferCategory();
    $product1 = createOfferProduct($category);
    $product2 = createOfferProduct($category);

    $payload = [
        'title' => 'خصم الأصناف المختارة',
        'type' => 'auto_discount',
        'discount_scope' => 'specific_products',
        'discount_percentage' => 15.0,
        'max_discount_cap' => 15.00,
        'min_order_amount' => 0.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subHour()->toIso8601String(),
        'product_ids' => [$product1->id, $product2->id],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->postJson('/api/v1/manager/offers', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', 'active');

    $offerId = $response->json('data.id');
    $this->assertDatabaseHas('offer_items', [
        'offer_id' => $offerId,
        'product_id' => $product1->id,
    ]);
    $this->assertDatabaseHas('offer_items', [
        'offer_id' => $offerId,
        'product_id' => $product2->id,
    ]);
});

test('manager can update an existing offer', function () {
    $manager = createOfferManagerStaff();

    $offer = Offer::create([
        'title' => 'عرض قديم',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 10.00,
        'max_discount_cap' => 10.00,
        'min_order_amount' => 50.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDay(),
    ]);
    $config = OfferCouponConfig::create([
        'offer_id' => $offer->id,
        'code' => 'OLD10',
        'usage_limit_per_user' => 'once',
    ]);

    $updatePayload = [
        'title' => 'عرض محدث بالكامل',
        'discount_percentage' => 25.0,
        'max_discount_cap' => 30.00,
        'coupon' => [
            'code' => 'NEW25',
        ],
    ];

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->putJson("/api/v1/manager/offers/{$offer->id}", $updatePayload);

    $response->assertOk()
        ->assertJsonPath('data.title', 'عرض محدث بالكامل')
        ->assertJsonPath('data.discount_percentage', 25)
        ->assertJsonPath('data.coupon.code', 'NEW25');

    $this->assertDatabaseHas('offer_coupon_configs', [
        'id' => $config->id,
        'code' => 'NEW25',
    ]);
});

test('manager can toggle offer active status', function () {
    $manager = createOfferManagerStaff();

    $offer = Offer::create([
        'title' => 'عرض للتعطيل',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 10.00,
        'max_discount_cap' => 10.00,
        'min_order_amount' => 50.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->patchJson("/api/v1/manager/offers/{$offer->id}/toggle-status", [
            'is_active' => false,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('message', 'تم تعطيل العرض الترويجي بنجاح');

    $this->assertDatabaseHas('offers', [
        'id' => $offer->id,
        'is_active' => false,
    ]);
});

test('manager can delete an unused offer', function () {
    $manager = createOfferManagerStaff();

    $offer = Offer::create([
        'title' => 'عرض غير مستخدم للحذف',
        'type' => 'coupon',
        'discount_scope' => 'cart',
        'discount_percentage' => 10.00,
        'max_discount_cap' => 10.00,
        'min_order_amount' => 50.00,
        'is_active' => true,
        'is_ongoing' => true,
        'starts_at' => Carbon::now()->subDay(),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$manager->createToken('manager')->plainTextToken)
        ->deleteJson("/api/v1/manager/offers/{$offer->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'تم حذف العرض الترويجي بنجاح');

    $this->assertDatabaseMissing('offers', ['id' => $offer->id]);
});
