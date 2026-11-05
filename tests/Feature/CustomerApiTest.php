<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(TestCase::class, RefreshDatabase::class);

it('sends an OTP to a valid customer phone number', function () {
    $response = postJson('/api/v1/customer/auth/send-otp', [
        'phone' => '0530697231',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.message', 'Verification code sent successfully.')
        ->assertJsonMissingPath('data.debug_otp');

    expect(Cache::has('otp:+966530697231'))->toBeFalse();
});

it('bypasses OTP verification for a valid phone in local development and creates a customer account', function () {
    $response = postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '0530697231',
        'code' => '0000',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.customer.phone', '+966530697231')
        ->assertJsonPath('data.token_type', 'Bearer');

    $this->assertDatabaseHas('users', [
        'phone' => '+966530697231',
        'full_name' => 'Customer',
    ]);
});

it('rejects OTP bypass outside local and testing environments', function () {
    config(['auth.customer_otp_bypass' => false]);

    postJson('/api/v1/customer/auth/send-otp', ['phone' => '0530697231'])
        ->assertServiceUnavailable()
        ->assertJsonPath('code', 'OTP_DELIVERY_UNAVAILABLE');

    postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '0530697231',
        'code' => '0000',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_OTP');

    $this->assertDatabaseMissing('users', ['phone' => '+966530697231']);
});

it('returns active categories and products to the authenticated customer', function () {
    $user = User::create([
        'name' => 'Customer',
        'phone' => '0530000000',
        'full_name' => 'Customer',
        'email' => 'customer@example.com',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password123'),
    ]);

    $category = Category::create([
        'name_ar' => 'وجبات',
        'name_en' => 'Meals',
        'slug' => 'meals',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'بيتزا خضار',
        'name_en' => 'Vegetable Pizza',
        'description_ar' => 'بيتزا طازجة',
        'base_price' => 35.00,
        'discount_percentage' => 0.00,
        'discounted_price' => 35.00,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'size_key' => 'large',
        'name_ar' => 'كبير',
        'piece_count' => 2,
        'price' => 35.00,
        'is_default' => true,
    ]);

    actingAs($user, 'sanctum')
        ->getJson('/api/v1/customer/menu')
        ->assertOk()
        ->assertJsonPath('data.0.name_ar', 'وجبات')
        ->assertJsonPath('data.0.products.0.name_ar', 'بيتزا خضار');
});

it('orders menu categories and their products by display order', function () {
    $laterCategory = Category::create([
        'name_ar' => 'فئة لاحقة',
        'slug' => 'later-menu-category',
        'sort_order' => 20,
        'is_active' => true,
    ]);
    $earlierCategory = Category::create([
        'name_ar' => 'فئة مبكرة',
        'slug' => 'earlier-menu-category',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    Product::create([
        'category_id' => $laterCategory->id,
        'name_ar' => 'منتج الفئة اللاحقة',
        'base_price' => 10,
        'is_available' => true,
        'sort_order' => 1,
    ]);
    Product::create([
        'category_id' => $earlierCategory->id,
        'name_ar' => 'المنتج الثاني',
        'base_price' => 10,
        'is_available' => true,
        'sort_order' => 2,
    ]);
    Product::create([
        'category_id' => $earlierCategory->id,
        'name_ar' => 'المنتج الأول',
        'base_price' => 10,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/v1/customer/menu')
        ->assertOk()
        ->assertJsonPath('data.0.id', $earlierCategory->id)
        ->assertJsonPath('data.0.products.0.name_ar', 'المنتج الأول')
        ->assertJsonPath('data.0.products.1.name_ar', 'المنتج الثاني')
        ->assertJsonPath('data.1.id', $laterCategory->id);
});
