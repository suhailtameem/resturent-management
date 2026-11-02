<?php

use App\Events\OrderCreated;
use App\Models\Category;
use App\Models\DeliveryCity;
use App\Models\DeliveryNeighborhood;
use App\Models\Order;
use App\Models\Product;
use App\Models\StaffUser;
use App\Models\StoreSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createOrderCustomer(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'سارة محمد',
        'phone' => '+966551234567',
        'full_name' => 'سارة محمد',
        'email' => uniqid('customer_', true).'@example.test',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
}

function createOrderProduct(array $attributes = []): Product
{
    $category = Category::create([
        'name_ar' => 'أطباق رئيسية',
        'name_en' => 'Main Dishes',
        'slug' => uniqid('cat_'),
        'is_active' => true,
        'sort_order' => 1,
    ]);

    return Product::create(array_merge([
        'category_id' => $category->id,
        'name_ar' => 'ورق عنب محشي',
        'name_en' => 'Stuffed Grape Leaves',
        'base_price' => 35.0,
        'discounted_price' => 35.0,
        'is_available' => true,
        'sort_order' => 1,
    ], $attributes));
}

function createManagerUser(): StaffUser
{
    return StaffUser::create([
        'username' => 'admin_'.uniqid(),
        'email' => 'admin_'.uniqid().'@example.test',
        'phone' => '+966500000001',
        'full_name' => 'مدير النظام',
        'role' => 'super_admin',
        'is_active' => true,
        'password' => Hash::make('secret123'),
    ]);
}

it('places a delivery order with address snapshot and calculated neighborhood fee', function () {
    Event::fake([OrderCreated::class]);

    $customer = createOrderCustomer();
    $product = createOrderProduct();

    $city = DeliveryCity::create([
        'name' => 'تبوك',
        'service_status' => 'active',
    ]);

    $neighborhood = DeliveryNeighborhood::create([
        'delivery_city_id' => $city->id,
        'name' => 'حي المروج',
        'delivery_fee' => 15.0,
        'minimum_order_amount' => 30.0,
        'is_active' => true,
    ]);

    $address = $customer->addresses()->create([
        'title_ar' => 'المنزل',
        'city_ar' => 'تبوك',
        'district_ar' => 'حي المروج',
        'street_details_ar' => 'شارع الأمير فهد',
        'latitude' => 28.3835,
        'longitude' => 36.5662,
        'notes' => 'الشقة 4 الدور الثاني',
        'is_default' => true,
    ]);

    // Request delivery quote
    $quoteResponse = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'delivery',
            'address_id' => $address->id,
            'neighborhood_id' => $neighborhood->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'special_instructions' => 'زيادة ليمون ودبس رمان',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.delivery_mode', 'delivery')
        ->assertJsonPath('data.subtotal', 70)
        ->assertJsonPath('data.delivery_fee', 15)
        ->assertJsonPath('data.total', 85)
        ->assertJsonPath('data.delivery_address.district_ar', 'حي المروج')
        ->assertJsonPath('data.delivery_neighborhood.name', 'حي المروج');

    $quoteId = $quoteResponse->json('data.quote_id');

    // Place the order
    $orderResponse = $this->actingAs($customer, 'sanctum')
        ->withHeader('Idempotency-Key', 'order-del-test-001')
        ->postJson('/api/v1/orders', [
            'quote_id' => $quoteId,
            'payment_method' => 'cod',
            'special_notes' => 'يرجى عدم الاتصال بالهاتف عند الوصول والرنين على الجرس',
            'contact_phone' => '+966551234567',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.status_label_ar', 'تم استلام الطلب')
        ->assertJsonPath('data.can_cancel', true)
        ->assertJsonPath('data.delivery_mode', 'delivery')
        ->assertJsonPath('data.delivery_mode_label_ar', 'توصيل')
        ->assertJsonPath('data.delivery_fee', 15)
        ->assertJsonPath('data.total', 85)
        ->assertJsonPath('data.delivery_address.district_ar', 'حي المروج')
        ->assertJsonPath('data.special_notes', 'يرجى عدم الاتصال بالهاتف عند الوصول والرنين على الجرس')
        ->assertJsonPath('data.status_timeline.0.status', 'received')
        ->assertJsonPath('data.status_timeline.0.status_label_ar', 'تم استلام الطلب');

    $orderId = $orderResponse->json('data.id');

    Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event): bool => $event->order['order_number'] === $orderResponse->json('data.order_number'));

    // Verify order in database
    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'user_id' => $customer->id,
        'delivery_mode' => 'delivery',
        'delivery_address_id' => $address->id,
        'delivery_fee' => 15.0,
        'order_status' => 'received',
    ]);
});

it('enforces neighborhood minimum order amount for delivery', function () {
    $customer = createOrderCustomer();
    $product = createOrderProduct(['base_price' => 20.0, 'discounted_price' => 20.0]);

    $city = DeliveryCity::create(['name' => 'تبوك', 'service_status' => 'active']);
    $neighborhood = DeliveryNeighborhood::create([
        'delivery_city_id' => $city->id,
        'name' => 'حي الورود',
        'delivery_fee' => 12.0,
        'minimum_order_amount' => 50.0,
        'is_active' => true,
    ]);

    $address = $customer->addresses()->create([
        'title_ar' => 'العمل',
        'city_ar' => 'تبوك',
        'district_ar' => 'حي الورود',
        'street_details_ar' => 'طريق الملك عبد العزيز',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'delivery',
            'address_id' => $address->id,
            'neighborhood_id' => $neighborhood->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'MINIMUM_ORDER_NOT_MET');
});

it('rejects delivery when address does not belong to customer or is outside coverage', function () {
    $customer = createOrderCustomer();
    $otherCustomer = createOrderCustomer(['phone' => '+966599999999', 'email' => 'other2@example.test']);
    $product = createOrderProduct();

    $city = DeliveryCity::create(['name' => 'تبوك', 'service_status' => 'active']);
    $neighborhood = DeliveryNeighborhood::create([
        'delivery_city_id' => $city->id,
        'name' => 'حي المروج',
        'delivery_fee' => 10.0,
        'minimum_order_amount' => 20.0,
        'is_active' => true,
    ]);

    $otherAddress = $otherCustomer->addresses()->create([
        'title_ar' => 'عنوان آخر',
        'city_ar' => 'تبوك',
        'district_ar' => 'حي المروج',
    ]);

    // Attempt quote with unowned address
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'delivery',
            'address_id' => $otherAddress->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ADDRESS_NOT_FOUND');

    // Customer address in unknown area
    $uncoveredAddress = $customer->addresses()->create([
        'title_ar' => 'مزرعة',
        'city_ar' => 'العلا',
        'district_ar' => 'منطقة غير مغطاة',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'delivery',
            'address_id' => $uncoveredAddress->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'DELIVERY_ZONE_UNAVAILABLE');
});

it('returns full order details for the order details screen and allows status tracking', function () {
    $customer = createOrderCustomer();
    $product = createOrderProduct();

    $quoteResponse = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertOk();

    $quoteId = $quoteResponse->json('data.quote_id');

    $orderResponse = $this->actingAs($customer, 'sanctum')
        ->withHeader('Idempotency-Key', 'order-pickup-scr-1')
        ->postJson('/api/v1/orders', [
            'quote_id' => $quoteId,
            'payment_method' => 'cod',
            'special_notes' => 'تجهيز الطلب ساخن',
        ])
        ->assertCreated();

    $orderId = $orderResponse->json('data.id');

    // Fetch Order Details Screen API
    $detailsResponse = $this->actingAs($customer, 'sanctum')
        ->getJson("/api/v1/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $orderId)
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.status_label_ar', 'تم استلام الطلب')
        ->assertJsonPath('data.status_label_en', 'Order Received')
        ->assertJsonPath('data.can_cancel', true)
        ->assertJsonPath('data.delivery_mode', 'pickup')
        ->assertJsonPath('data.delivery_mode_label_ar', 'استلام من الفرع')
        ->assertJsonPath('data.payment_method', 'cod')
        ->assertJsonPath('data.payment_method_label_ar', 'الدفع عند الاستلام')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.currency', 'SAR')
        ->assertJsonPath('data.currency_label_ar', 'ر.س')
        ->assertJsonPath('data.special_notes', 'تجهيز الطلب ساخن')
        ->assertJsonPath('data.pickup_store_details.store_name_ar', StoreSettings::current()->store_name_ar)
        ->assertJsonPath('data.items.0.product_name_ar', 'ورق عنب محشي')
        ->assertJsonPath('data.status_timeline.0.status', 'received')
        ->assertJsonPath('data.status_timeline.0.status_label_ar', 'تم استلام الطلب');

    // Dedicated Tracking API
    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/v1/orders/{$orderId}/track")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $orderId)
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.status_label_ar', 'تم استلام الطلب')
        ->assertJsonPath('data.can_cancel', true)
        ->assertJsonPath('data.status_timeline.0.status', 'received');
});

it('allows manager to transition order status through lifecycle and updates customer tracking', function () {
    $customer = createOrderCustomer();
    $manager = createManagerUser();
    $product = createOrderProduct();

    $quoteResponse = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertOk();

    $orderResponse = $this->actingAs($customer, 'sanctum')
        ->withHeader('Idempotency-Key', 'order-status-life-1')
        ->postJson('/api/v1/orders', [
            'quote_id' => $quoteResponse->json('data.quote_id'),
            'payment_method' => 'cod',
        ])
        ->assertCreated();

    $orderId = $orderResponse->json('data.id');

    // Manager updates to preparing
    $this->actingAs($manager, 'staff_api')
        ->patchJson("/api/v1/manager/orders/{$orderId}/status", [
            'to_status' => 'preparing',
            'notes' => 'جاري طهي الطلب في المطبخ',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing')
        ->assertJsonPath('data.status_label_ar', 'قيد التحضير');

    // Customer tracking shows preparing, can_cancel is now false
    $this->actingAs($customer, 'sanctum')
        ->getJson("/api/v1/orders/{$orderId}/track")
        ->assertOk()
        ->assertJsonPath('data.status', 'preparing')
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonCount(2, 'data.status_timeline');

    // Customer cancellation is rejected after preparation
    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/v1/orders/{$orderId}/cancel")
        ->assertConflict()
        ->assertJsonPath('code', 'ORDER_CANNOT_BE_CANCELLED');

    // Manager updates to ready_for_pickup
    $this->actingAs($manager, 'staff_api')
        ->patchJson("/api/v1/manager/orders/{$orderId}/status", [
            'to_status' => 'ready_for_pickup',
            'notes' => 'الطلب جاهز للاستلام من الكاونتر',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_pickup')
        ->assertJsonPath('data.status_label_ar', 'جاهز للاستلام');

    // Manager updates to delivered
    $this->actingAs($manager, 'staff_api')
        ->patchJson("/api/v1/manager/orders/{$orderId}/status", [
            'to_status' => 'delivered',
            'notes' => 'تم تسليم الطلب للعميل واستلام المبلغ',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.payment_status', 'paid');

    // Customer tracking shows full timeline of 4 events
    $track = $this->actingAs($customer, 'sanctum')
        ->getJson("/api/v1/orders/{$orderId}/track")
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonCount(4, 'data.status_timeline');
});
