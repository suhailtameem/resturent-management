<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createStaffUser(array $attributes = []): StaffUser
{
    return StaffUser::create(array_merge([
        'username' => 'manager',
        'email' => 'manager@example.test',
        'password' => Hash::make('password123'),
        'role' => 'super_admin',
        'full_name' => 'Manager',
        'phone' => '+966500000000',
        'is_active' => true,
    ], $attributes));
}

function createManagerCategory(array $attributes = []): Category
{
    return Category::create(array_merge([
        'name_ar' => 'وجبات',
        'name_en' => 'Meals',
        'slug' => 'meals',
        'sort_order' => 1,
        'is_active' => true,
    ], $attributes));
}

it('logs in a manager and returns a staff access token', function () {
    createStaffUser();

    $response = $this->postJson('/api/v1/manager/auth/login', [
        'identifier' => 'manager',
        'password' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.staff.role', 'super_admin')
        ->assertJsonMissingPath('data.staff.password');

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('allows a manager to log in with an email address', function () {
    createStaffUser();

    $this->postJson('/api/v1/manager/auth/login', [
        'identifier' => 'manager@example.test',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonPath('data.staff.email', 'manager@example.test');
});

it('returns 401 when manager credentials are invalid', function () {
    createStaffUser();

    $this->postJson('/api/v1/manager/auth/login', [
        'identifier' => 'manager',
        'password' => 'incorrect-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

it('returns 403 when an inactive staff account attempts to log in', function () {
    createStaffUser(['is_active' => false]);

    $this->postJson('/api/v1/manager/auth/login', [
        'identifier' => 'manager',
        'password' => 'password123',
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'STAFF_ACCOUNT_INACTIVE');
});

it('rejects manager category requests without staff authentication', function () {
    $this->getJson('/api/v1/manager/categories')
        ->assertUnauthorized();
});

it('forbids customer tokens from manager category endpoints', function () {
    $customer = User::create([
        'name' => 'Customer',
        'phone' => '+966511111111',
        'full_name' => 'Customer',
        'email' => 'customer@example.test',
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
        'password' => Hash::make('password123'),
    ]);

    $this->withToken($customer->createToken('customer-test')->plainTextToken)
        ->getJson('/api/v1/manager/categories')
        ->assertUnauthorized();
});

it('forbids non-super-admin staff from managing categories', function () {
    $staffUser = createStaffUser([
        'username' => 'cashier',
        'email' => 'cashier@example.test',
        'role' => 'cashier',
    ]);

    $this->actingAs($staffUser, 'staff_api')
        ->getJson('/api/v1/manager/categories')
        ->assertForbidden()
        ->assertJsonPath('code', 'INSUFFICIENT_ROLE');
});

it('forbids inactive staff from managing categories with an existing token', function () {
    $staffUser = createStaffUser(['is_active' => false]);

    $this->actingAs($staffUser, 'staff_api')
        ->getJson('/api/v1/manager/categories')
        ->assertForbidden()
        ->assertJsonPath('code', 'INSUFFICIENT_ROLE');
});

it('lists active and inactive categories to an authorized manager', function () {
    $manager = createStaffUser();
    createManagerCategory();
    createManagerCategory([
        'name_ar' => 'مخفية',
        'slug' => 'hidden',
        'is_active' => false,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/categories?per_page=10')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.slug', 'hidden');
});

it('creates a category and returns the persisted category', function () {
    $manager = createStaffUser();

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/categories', [
            'name_ar' => 'مشروبات',
            'name_en' => 'Drinks',
            'slug' => 'drinks',
            'icon_code' => 'cup',
            'sort_order' => 3,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'drinks')
        ->assertJsonPath('data.icon_code', 'cup');

    $this->assertDatabaseHas('categories', [
        'name_ar' => 'مشروبات',
        'slug' => 'drinks',
    ]);
});

it('returns validation errors for duplicate category slugs', function () {
    $manager = createStaffUser();
    createManagerCategory();

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/categories', [
            'name_ar' => 'وجبات جديدة',
            'slug' => 'meals',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

it('shows and updates a category', function () {
    $manager = createStaffUser();
    $category = createManagerCategory();

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/categories/'.$category->id)
        ->assertOk()
        ->assertJsonPath('data.slug', 'meals');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/categories/'.$category->id, [
            'name_ar' => 'الوجبات',
            'slug' => 'meals',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name_ar', 'الوجبات')
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name_ar' => 'الوجبات',
        'is_active' => false,
    ]);
});

it('returns not found when a category does not exist', function () {
    $manager = createStaffUser();

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/categories/99999')
        ->assertNotFound();
});

it('deletes a category with no products', function () {
    $manager = createStaffUser();
    $category = createManagerCategory();

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/categories/'.$category->id)
        ->assertNoContent();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

it('prevents deleting a category that contains products', function () {
    $manager = createStaffUser();
    $category = createManagerCategory();
    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'بيتزا',
        'name_en' => 'Pizza',
        'base_price' => 20,
        'discounted_price' => 20,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/categories/'.$category->id)
        ->assertConflict()
        ->assertJsonPath('code', 'CATEGORY_HAS_PRODUCTS');

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
    $this->assertModelExists($product);
});

it('revokes the current manager token on logout', function () {
    $manager = createStaffUser();
    $token = $manager->createToken('manager-test');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/manager/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
});
