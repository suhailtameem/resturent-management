<?php

use App\Models\Category;
use App\Models\OptionsGroup;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductOptionRelation;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createManagerForProductTests(): StaffUser
{
    return StaffUser::create([
        'username' => 'product-manager',
        'email' => 'product-manager@example.test',
        'password' => 'password123',
        'role' => 'super_admin',
        'full_name' => 'Product Manager',
        'phone' => '+966500000001',
        'is_active' => true,
    ]);
}

function createCategoryForProductTests(): Category
{
    return Category::create([
        'name_ar' => 'وجبات',
        'name_en' => 'Meals',
        'slug' => 'product-test-meals',
        'sort_order' => 1,
        'is_active' => true,
    ]);
}

function createProductPayloadForManager(Category $category): array
{
    return [
        'category_id' => $category->id,
        'name_ar' => 'بيتزا',
        'name_en' => 'Pizza',
        'description_ar' => 'بيتزا بالخضار',
        'description_en' => 'Vegetable pizza',
        'image_url' => 'https://example.test/pizza.png',
        'base_price' => 25,
        'has_discount' => true,
        'discount_percentage' => 10,
        'discounted_price' => 22.5,
        'is_available' => true,
        'is_featured' => true,
        'is_best_seller' => false,
        'badge_text_ar' => 'جديد',
        'sort_order' => 2,
        'sizes' => [
            [
                'size_key' => 'small',
                'name_ar' => 'صغير',
                'name_en' => 'Small',
                'piece_count' => 4,
                'portion_description_ar' => 'تكفي شخصًا',
                'portion_description_en' => 'Serves one',
                'price' => 25,
                'is_default' => true,
            ],
            [
                'size_key' => 'large',
                'name_ar' => 'كبير',
                'piece_count' => 8,
                'price' => 40,
            ],
        ],
        'option_groups' => [
            [
                'name_ar' => 'النكهة',
                'name_en' => 'Flavor',
                'type' => 'single',
                'option_category' => 'flavor',
                'is_required' => true,
                'options' => [
                    [
                        'name_ar' => 'عادي',
                        'name_en' => 'Regular',
                        'price' => 0,
                        'is_available' => true,
                    ],
                    [
                        'name_ar' => 'حار',
                        'price' => 1,
                    ],
                ],
            ],
            [
                'name_ar' => 'إضافات',
                'type' => 'multiple',
                'option_category' => 'add_on',
                'is_required' => false,
                'options' => [
                    [
                        'name_ar' => 'جبنة',
                        'price' => 5,
                    ],
                ],
            ],
        ],
    ];
}

it('requires manager authentication to access product management', function () {
    $this->getJson('/api/v1/manager/products')
        ->assertUnauthorized();
});

it('creates a product with sizes and option groups in one request', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();

    $response = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category));

    $response->assertCreated()
        ->assertJsonPath('data.name_ar', 'بيتزا')
        ->assertJsonCount(2, 'data.sizes')
        ->assertJsonCount(2, 'data.option_groups')
        ->assertJsonPath('data.option_groups.0.options.1.name_ar', 'حار');

    $product = Product::query()->where('name_ar', 'بيتزا')->firstOrFail();
    $this->assertDatabaseHas('product_variants', [
        'product_id' => $product->id,
        'size_key' => 'large',
        'price' => 40,
    ]);
    $this->assertDatabaseHas('options_groups', [
        'name_ar' => 'النكهة',
        'option_category' => 'flavor',
    ]);
    $this->assertDatabaseHas('product_options', [
        'name_ar' => 'حار',
        'price' => 1,
    ]);
});

it('creates products when sizes and option groups are omitted or empty', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $basePayload = [
        'category_id' => $category->id,
        'base_price' => 25,
    ];

    $withoutSelections = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            ...$basePayload,
            'name_ar' => 'منتج دون أحجام أو خيارات',
        ]);
    $withoutSelections->assertCreated()
        ->assertJsonCount(0, 'data.sizes')
        ->assertJsonCount(0, 'data.option_groups');

    $withEmptyOptions = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', [
            ...$basePayload,
            'name_ar' => 'منتج بخيارات فارغة',
            'option_groups' => [],
        ]);
    $withEmptyOptions->assertCreated()
        ->assertJsonCount(0, 'data.sizes')
        ->assertJsonCount(0, 'data.option_groups');

    $this->assertDatabaseCount('products', 2);
    $this->assertDatabaseCount('product_variants', 0);
    $this->assertDatabaseCount('options_groups', 0);
});

it('uploads and returns a public image url when creating a product with multipart form data', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);
    unset($payload['image_url']);
    $payload['image'] = UploadedFile::fake()->image('pizza.jpg');

    $response = $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name_ar', 'بيتزا');

    $product = Product::query()->where('name_ar', 'بيتزا')->firstOrFail();
    expect($product->getRawOriginal('image_url'))->toStartWith('products/')
        ->and($response->json('data.image_url'))->toContain('/storage/products/');
    Storage::disk('public')->assertExists($product->getRawOriginal('image_url'));

    $this->getJson('/api/v1/products/'.$product->id)
        ->assertOk()
        ->assertJsonPath('data.image_url', $response->json('data.image_url'));
});

it('replaces and deletes a product image through the image endpoints', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category))
        ->assertCreated();
    $productId = $created->json('data.id');

    $firstUpload = $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$productId.'/image', [
            'image' => UploadedFile::fake()->image('first.png'),
        ])
        ->assertOk();
    $product = Product::findOrFail($productId);
    $firstPath = $product->getRawOriginal('image_url');
    Storage::disk('public')->assertExists($firstPath);
    expect($firstUpload->json('data.image_url'))->toContain('/storage/'.$firstPath);

    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$productId.'/image', [
            'image' => UploadedFile::fake()->image('second.webp'),
        ])
        ->assertOk();
    $secondPath = $product->fresh()->getRawOriginal('image_url');
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($secondPath);

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/products/'.$productId.'/image')
        ->assertOk()
        ->assertJsonPath('data.image_url', null);

    expect($product->fresh()->getRawOriginal('image_url'))->toBeNull();
    Storage::disk('public')->assertMissing($secondPath);
});

it('rejects non-image uploads without storing them', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);
    unset($payload['image_url']);
    $payload['image'] = UploadedFile::fake()->create('malware.txt', 10, 'text/plain');

    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    $this->assertDatabaseCount('products', 0);
    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});

it('rejects an image whose filename extension is not allowed', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);
    unset($payload['image_url']);
    $payload['image'] = UploadedFile::fake()->image('pizza.gif');

    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    $this->assertDatabaseCount('products', 0);
    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});

it('lists and shows manager products with their nested sizes and options', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);

    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', $payload)
        ->assertCreated();
    $productId = $created->json('data.id');

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/products?category_id='.$category->id.'&search=Pizza')
        ->assertOk()
        ->assertJsonPath('data.0.id', $productId)
        ->assertJsonCount(2, 'data.0.sizes');

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/products/'.$productId)
        ->assertOk()
        ->assertJsonPath('data.option_groups.0.options.0.name_ar', 'عادي');
});

it('updates product fields and replaces only nested lists supplied in the request', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);
    $productResponse = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', $payload)
        ->assertCreated();
    $productId = $productResponse->json('data.id');
    $product = Product::findOrFail($productId);
    $previousGroupId = $product->optionGroups()->value('option_group_id');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/products/'.$productId, [
            'name_ar' => 'بيتزا محدثة',
            'sizes' => [],
        ])
        ->assertOk()
        ->assertJsonPath('data.name_ar', 'بيتزا محدثة')
        ->assertJsonCount(0, 'data.sizes')
        ->assertJsonCount(2, 'data.option_groups');

    $this->assertDatabaseMissing('product_variants', ['product_id' => $productId]);
    $this->assertDatabaseHas('product_option_relations', [
        'product_id' => $productId,
        'option_group_id' => $previousGroupId,
    ]);
});

it('keeps the product image when an update omits image data', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category))
        ->assertCreated();
    $productId = $created->json('data.id');

    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$productId.'/image', [
            'image' => UploadedFile::fake()->image('pizza.png'),
        ])
        ->assertOk();
    $imagePath = Product::findOrFail($productId)->getRawOriginal('image_url');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/products/'.$productId, [
            'name_ar' => 'بيتزا محدثة',
        ])
        ->assertOk()
        ->assertJsonPath('data.name_ar', 'بيتزا محدثة')
        ->assertJsonPath('data.image_url', Storage::disk('public')->url($imagePath));

    expect(Product::findOrFail($productId)->getRawOriginal('image_url'))->toBe($imagePath);
    Storage::disk('public')->assertExists($imagePath);
});

it('ignores a null image url in a product update', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category))
        ->assertCreated();
    $productId = $created->json('data.id');

    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$productId.'/image', [
            'image' => UploadedFile::fake()->image('pizza.png'),
        ])
        ->assertOk();
    $imagePath = Product::findOrFail($productId)->getRawOriginal('image_url');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/products/'.$productId, [
            'name_en' => 'Updated pizza',
            'image_url' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Updated pizza')
        ->assertJsonPath('data.image_url', Storage::disk('public')->url($imagePath));

    expect(Product::findOrFail($productId)->getRawOriginal('image_url'))->toBe($imagePath);
    Storage::disk('public')->assertExists($imagePath);
});

it('replaces the product image when an update includes an uploaded image', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category))
        ->assertCreated();
    $productId = $created->json('data.id');

    $response = $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$productId, [
            '_method' => 'PATCH',
            'name_en' => 'Updated pizza',
            'image' => UploadedFile::fake()->image('updated-pizza.webp'),
        ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Updated pizza');

    $imagePath = Product::findOrFail($productId)->getRawOriginal('image_url');
    expect($imagePath)->toStartWith('products/')
        ->and($response->json('data.image_url'))->toBe(Storage::disk('public')->url($imagePath));
    Storage::disk('public')->assertExists($imagePath);
});

it('rejects duplicate sizes and multiple default sizes', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $payload = createProductPayloadForManager($category);
    $payload['sizes'][1]['size_key'] = 'small';
    $payload['sizes'][1]['is_default'] = true;

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sizes.1.size_key']);

    $payload['sizes'][1]['size_key'] = 'large';

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sizes']);

    $this->assertDatabaseCount('products', 0);
});

it('deletes a product without order history and its private options', function () {
    Storage::fake('public');
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $created = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/products', createProductPayloadForManager($category))
        ->assertCreated();
    $product = Product::findOrFail($created->json('data.id'));
    $groupId = $product->optionGroups()->value('option_group_id');
    $this->actingAs($manager, 'staff_api')
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/v1/manager/products/'.$product->id.'/image', [
            'image' => UploadedFile::fake()->image('pizza.png'),
        ])
        ->assertOk();
    $imagePath = $product->fresh()->getRawOriginal('image_url');

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/products/'.$product->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
    $this->assertDatabaseMissing('options_groups', ['id' => $groupId]);
    Storage::disk('public')->assertMissing($imagePath);
});

it('keeps an option group that is still associated with another product', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'منتج أول',
        'base_price' => 20,
    ]);
    $otherProduct = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'منتج ثان',
        'base_price' => 30,
    ]);
    $sharedGroup = OptionsGroup::create([
        'name_ar' => 'إضافات مشتركة',
        'type' => 'multiple',
        'option_category' => 'add_on',
    ]);
    ProductOptionRelation::create([
        'product_id' => $product->id,
        'option_group_id' => $sharedGroup->id,
    ]);
    ProductOptionRelation::create([
        'product_id' => $otherProduct->id,
        'option_group_id' => $sharedGroup->id,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/products/'.$product->id)
        ->assertNoContent();

    $this->assertModelExists($sharedGroup);
    $this->assertDatabaseHas('product_option_relations', [
        'product_id' => $otherProduct->id,
        'option_group_id' => $sharedGroup->id,
    ]);
});

it('prevents deleting a product referenced by order history', function () {
    $manager = createManagerForProductTests();
    $category = createCategoryForProductTests();
    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'طبق تاريخي',
        'base_price' => 20,
        'discounted_price' => 20,
        'is_available' => true,
    ]);
    $customer = User::create([
        'name' => 'Customer',
        'phone' => '+966500000002',
        'full_name' => 'Customer',
        'email' => 'product-customer@example.test',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password123'),
    ]);
    $order = Order::create([
        'order_number' => 'ORD-PRODUCT-TEST',
        'user_id' => $customer->id,
        'customer_name' => 'Customer',
        'customer_phone' => '+966500000002',
        'order_status' => 'received',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'subtotal_amount' => 20,
        'total_final_amount' => 20,
        'delivery_mode' => 'pickup',
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name_ar_snapshot' => 'طبق تاريخي',
        'unit_price' => 20,
        'quantity' => 1,
        'line_total' => 20,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/products/'.$product->id)
        ->assertConflict()
        ->assertJsonPath('code', 'PRODUCT_HAS_ORDERS');

    $this->assertModelExists($product);
    $this->assertModelExists($orderItem);
});
