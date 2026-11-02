<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\OptionsGroup;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionRelation;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createCustomer(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'Customer',
        'phone' => '+966500000000',
        'full_name' => 'Customer',
        'email' => uniqid('customer_', true).'@example.test',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password'),
    ], $attributes));
}

function createCategory(array $attributes = []): Category
{
    return Category::create(array_merge([
        'name_ar' => 'وجبات',
        'name_en' => 'Meals',
        'slug' => uniqid('category_'),
        'is_active' => true,
        'sort_order' => 1,
    ], $attributes));
}

function createProduct(Category $category, array $attributes = []): Product
{
    return Product::create(array_merge([
        'category_id' => $category->id,
        'name_ar' => 'بيتزا',
        'name_en' => 'Pizza',
        'base_price' => 20,
        'discounted_price' => 20,
        'is_available' => true,
        'sort_order' => 1,
    ], $attributes));
}

it('lists public categories and supports available product filters', function () {
    $activeCategory = createCategory();
    $inactiveCategory = createCategory(['is_active' => false, 'slug' => uniqid('inactive_')]);
    $product = createProduct($activeCategory, [
        'is_featured' => true,
        'is_best_seller' => true,
    ]);
    createProduct($inactiveCategory, ['name_ar' => 'مخفي']);
    createProduct($activeCategory, ['name_ar' => 'غير متوفر', 'is_available' => false]);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $activeCategory->id)
        ->assertJsonMissing(['name_ar' => 'غير متوفر']);

    $this->getJson('/api/v1/products?featured=1&best_seller=1&search=بيتزا')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $product->id)
        ->assertJsonPath('data.0.price', 20);
});

it('returns selectable product sizes, flavors, and add-ons', function () {
    $product = createProduct(createCategory());
    ProductVariant::create([
        'product_id' => $product->id,
        'size_key' => 'large',
        'name_ar' => 'كبير',
        'piece_count' => 4,
        'price' => 24,
        'is_default' => true,
    ]);
    $flavorGroup = OptionsGroup::create([
        'name_ar' => 'النكهة',
        'type' => 'single',
        'option_category' => 'flavor',
    ]);
    $addOnGroup = OptionsGroup::create([
        'name_ar' => 'إضافات',
        'type' => 'multiple',
        'option_category' => 'add_on',
    ]);
    ProductOptionRelation::create(['product_id' => $product->id, 'option_group_id' => $flavorGroup->id]);
    ProductOptionRelation::create(['product_id' => $product->id, 'option_group_id' => $addOnGroup->id]);
    $flavor = ProductOption::create([
        'group_id' => $flavorGroup->id,
        'name_ar' => 'حار',
        'price' => 0,
        'is_available' => true,
    ]);
    $addon = ProductOption::create([
        'group_id' => $addOnGroup->id,
        'name_ar' => 'جبنة',
        'price' => 3,
        'is_available' => true,
    ]);

    $this->getJson('/api/v1/products/'.$product->id)
        ->assertOk()
        ->assertJsonPath('data.sizes.0.id', (string) $product->variants()->first()->id)
        ->assertJsonPath('data.flavor_options.0.id', (string) $flavor->id)
        ->assertJsonPath('data.add_ons.0.id', (string) $addon->id);
});

it('creates and updates only the authenticated customer addresses', function () {
    $customer = createCustomer();
    $otherCustomer = createCustomer(['phone' => '+966511111111', 'email' => 'other@example.test']);
    $otherAddress = $otherCustomer->addresses()->create([
        'title_ar' => 'المنزل',
        'city_ar' => 'تبوك',
        'district_ar' => 'الورود',
        'is_default' => true,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/addresses', [
            'title_ar' => 'المنزل',
            'city_ar' => 'تبوك',
            'district_ar' => 'الورود',
            'street_details_ar' => 'شارع 1',
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    $this->actingAs($customer, 'sanctum')
        ->patchJson('/api/v1/addresses/'.$otherAddress->id, ['title_ar' => 'تعديل'])
        ->assertNotFound();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('calculates a pickup quote from current product, size, and add-on prices', function () {
    $customer = createCustomer();
    $category = createCategory();
    $product = createProduct($category, [
        'has_discount' => true,
        'discount_percentage' => 10,
        'discounted_price' => 18,
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'size_key' => 'large',
        'name_ar' => 'كبير',
        'piece_count' => 2,
        'price' => 20,
    ]);
    $group = OptionsGroup::create([
        'name_ar' => 'إضافات',
        'type' => 'multiple',
        'option_category' => 'add_on',
    ]);
    ProductOptionRelation::create(['product_id' => $product->id, 'option_group_id' => $group->id]);
    $addon = ProductOption::create([
        'group_id' => $group->id,
        'name_ar' => 'جبنة',
        'price' => 3,
        'is_available' => true,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [[
                'product_id' => $product->id,
                'size_id' => $variant->id,
                'addon_ids' => [$addon->id],
                'quantity' => 2,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.subtotal', 46)
        ->assertJsonPath('data.product_discount', 4)
        ->assertJsonPath('data.delivery_fee', 0)
        ->assertJsonPath('data.tax_amount', 0)
        ->assertJsonPath('data.total', 42)
        ->assertJsonPath('data.items.0.unit_price', 21)
        ->assertJsonPath('data.items.0.line_total', 42);
});

it('applies coupon maximum discount and enforces per-customer usage limits', function () {
    $customer = createCustomer();
    $product = createProduct(createCategory());
    $coupon = Coupon::create([
        'code' => 'MAX5',
        'discount_type' => 'percentage',
        'discount_value' => 50,
        'maximum_discount_amount' => 5,
        'min_order_value' => 0,
        'allow_combine_with_product_discount' => true,
        'start_date' => today()->subDay(),
        'end_date' => today()->addDay(),
        'usage_limit_per_user' => 1,
        'is_active' => true,
    ]);

    $quote = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'coupon_code' => $coupon->code,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertOk()
        ->assertJsonPath('data.coupon_discount', 5)
        ->assertJsonPath('data.total', 15)
        ->assertJsonMissingPath('data.coupon.id')
        ->json('data.quote_id');

    $this->actingAs($customer, 'sanctum')
        ->withHeader('Idempotency-Key', 'coupon-order-1')
        ->postJson('/api/v1/orders', ['quote_id' => $quote, 'payment_method' => 'cod'])
        ->assertCreated();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'coupon_code' => $coupon->code,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'COUPON_INVALID');
});

it('places an order from a quote and makes retries idempotent', function () {
    $customer = createCustomer();
    $category = createCategory();
    $product = createProduct($category);
    $quote = $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/checkout/quote', [
            'delivery_mode' => 'pickup',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])
        ->assertOk()
        ->json('data.quote_id');

    $request = fn () => $this->actingAs($customer, 'sanctum')
        ->withHeader('Idempotency-Key', 'customer-order-1')
        ->postJson('/api/v1/orders', ['quote_id' => $quote, 'payment_method' => 'cod']);

    $request()
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.total', 20);

    $request()
        ->assertOk()
        ->assertJsonPath('data.order_number', fn ($orderNumber) => is_string($orderNumber));

    expect(Order::whereBelongsTo($customer)->count())->toBe(1);
});

it('hides another customer order and permits cancellation only before preparation', function () {
    $customer = createCustomer();
    $otherCustomer = createCustomer(['phone' => '+966522222222', 'email' => 'second@example.test']);
    $category = createCategory();
    $product = createProduct($category);
    $order = Order::create([
        'order_number' => 'ORD-'.uniqid(),
        'user_id' => $otherCustomer->id,
        'customer_name' => 'Customer',
        'customer_phone' => $otherCustomer->phone,
        'order_status' => 'received',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal_amount' => 20,
        'product_discount_amount' => 0,
        'coupon_discount_amount' => 0,
        'delivery_fee' => 0,
        'vat_tax_amount' => 0,
        'total_final_amount' => 20,
        'delivery_mode' => 'pickup',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/v1/orders/'.$order->id)
        ->assertNotFound();

    $ownedOrder = Order::create([
        'order_number' => 'ORD-'.uniqid(),
        'user_id' => $customer->id,
        'customer_name' => 'Customer',
        'customer_phone' => $customer->phone,
        'order_status' => 'received',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal_amount' => 20,
        'product_discount_amount' => 0,
        'coupon_discount_amount' => 0,
        'delivery_fee' => 0,
        'vat_tax_amount' => 0,
        'total_final_amount' => 20,
        'delivery_mode' => 'pickup',
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders/'.$ownedOrder->id.'/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $ownedOrder->update(['order_status' => 'preparing']);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/orders/'.$ownedOrder->id.'/cancel')
        ->assertConflict()
        ->assertJsonPath('code', 'ORDER_CANNOT_BE_CANCELLED');
});

it('requires authentication for checkout and order history', function () {
    $this->postJson('/api/v1/checkout/quote', [])
        ->assertUnauthorized();

    $this->getJson('/api/v1/orders')
        ->assertUnauthorized();
});

it('revokes only the current Sanctum access token on logout', function () {
    $customer = createCustomer();
    $plainTextToken = $customer->createToken('customer-api-test')->plainTextToken;

    $this->withToken($plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $customer->id]);
    Auth::forgetGuards();

    $this->withToken($plainTextToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('logs in an existing customer instead of creating another account', function () {
    $existingCustomer = createCustomer([
        'phone' => '0530697231',
    ]);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '+966530697231',
        'code' => '9999',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.customer.id', (string) $existingCustomer->id)
        ->assertJsonPath('data.customer.phone', '+966530697231');

    expect(User::query()->count())->toBe(1);
});
