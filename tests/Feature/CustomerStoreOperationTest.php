<?php

use App\Models\Category;
use App\Models\DeliveryCity;
use App\Models\DeliveryNeighborhood;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreOperation;
use App\Models\StoreSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createStoreOperationCustomer(): User
{
    return User::create([
        'name' => 'Customer',
        'phone' => '+966500000000',
        'full_name' => 'Customer',
        'email' => 'customer-store-operation@example.test',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password'),
    ]);
}

function createStoreOperationProduct(): Product
{
    $category = Category::create([
        'name_ar' => 'وجبات',
        'name_en' => 'Meals',
        'slug' => 'store-operation-meals',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    return Product::create([
        'category_id' => $category->id,
        'name_ar' => 'بيتزا',
        'name_en' => 'Pizza',
        'base_price' => 20,
        'discounted_price' => 20,
        'is_available' => true,
        'sort_order' => 1,
    ]);
}

it('provides customer-facing currency, order status, payment methods, and delivery coverage', function () {
    $city = DeliveryCity::create([
        'name' => 'تبوك',
        'service_status' => 'active',
    ]);
    DeliveryNeighborhood::create([
        'delivery_city_id' => $city->id,
        'name' => 'حي المروج',
        'delivery_fee' => 12,
        'minimum_order_amount' => 35,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/customer/store/settings')
        ->assertOk()
        ->assertJsonPath('data.currency_code', 'SAR')
        ->assertJsonPath('data.currency_label_ar', 'ر.س')
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.payment_methods.cash_on_delivery_enabled', true)
        ->assertJsonPath('data.delivery_cities.0.service_status', 'active')
        ->assertJsonPath('data.delivery_cities.0.neighborhoods.0.delivery_fee', 12);

    $this->getJson('/api/v1/startup-lookups')
        ->assertOk()
        ->assertJsonPath('data.currency_code', 'SAR')
        ->assertJsonPath('data.currency_label_ar', 'ر.س')
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.delivery_cities.0.neighborhoods.0.delivery_fee', 12);
});

it('rejects customer checkout when the store is closed', function () {
    $customer = createStoreOperationCustomer();
    $product = createStoreOperationProduct();
    StoreOperation::current()->update(['is_open' => false]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'STORE_CLOSED');
});

it('rejects COD checkout when cash on delivery is disabled', function () {
    $customer = createStoreOperationCustomer();
    $product = createStoreOperationProduct();
    StoreSettings::current()->update(['cash_on_delivery_enabled' => false]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'PAYMENT_METHOD_UNAVAILABLE')
        ->assertJsonValidationErrors(['payment_method']);
});

it('uses the configured rush preparation time for quotes and persisted orders', function () {
    $customer = createStoreOperationCustomer();
    $product = createStoreOperationProduct();
    StoreOperation::current()->update([
        'estimated_prep_time_minutes' => 30,
        'rush_extra_time_enabled' => true,
        'rush_extra_time_minutes' => 15,
    ]);

    $quote = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertOk()
        ->assertJsonPath('data.estimated_prep_time_minutes', 45)
        ->assertJsonPath('data.currency_label_en', 'SAR')
        ->json('data.quote_id');

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders', [
            'quote_id' => $quote,
            'payment_method' => 'cod',
            'idempotency_key' => 'store-operation-order-1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.estimated_prep_time_minutes', 45);

    $this->assertDatabaseHas('orders', [
        'user_id' => $customer->id,
        'estimated_prep_time_minutes' => 45,
    ]);
});

it('rejects quotes after the configured hourly order capacity is reached', function () {
    $customer = createStoreOperationCustomer();
    $product = createStoreOperationProduct();
    StoreOperation::current()->update(['orders_per_hour_limit' => 5]);

    foreach (range(1, 5) as $orderNumber) {
        Order::create([
            'order_number' => 'CAPACITY-'.$orderNumber,
            'user_id' => $customer->id,
            'customer_name' => 'Customer',
            'customer_phone' => '+966500000000',
            'subtotal_amount' => 20,
            'total_final_amount' => 20,
        ]);
    }

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'ORDER_CAPACITY_REACHED');
});
