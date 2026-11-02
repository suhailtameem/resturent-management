<?php

use App\Models\Order;
use App\Models\StaffUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createManagerForCustomerTests(): StaffUser
{
    return StaffUser::query()->create([
        'username' => 'customer_manager',
        'email' => 'customer_manager@example.test',
        'password' => Hash::make('password123'),
        'role' => 'super_admin',
        'full_name' => 'Customer Manager',
        'phone' => '0500000000',
        'is_active' => true,
    ]);
}

function createManagedCustomer(array $attributes = []): User
{
    static $customerNumber = 0;
    $customerNumber++;

    return User::query()->create(array_merge([
        'name' => 'Customer '.$customerNumber,
        'full_name' => 'Customer '.$customerNumber,
        'email' => 'customer'.$customerNumber.'@example.test',
        'phone' => '+9665'.str_pad((string) $customerNumber, 8, '0', STR_PAD_LEFT),
        'password' => Hash::make('password123'),
        'preferred_language' => 'ar',
        'is_vip' => false,
        'status' => 'active',
    ], $attributes));
}

function createCustomerOrder(User $customer, array $attributes = []): Order
{
    static $orderNumber = 0;
    $orderNumber++;

    return Order::query()->create(array_merge([
        'order_number' => 'CUS-ORDER-'.$orderNumber,
        'user_id' => $customer->id,
        'customer_name' => $customer->full_name,
        'customer_phone' => $customer->phone,
        'order_status' => 'delivered',
        'payment_status' => 'paid',
        'subtotal_amount' => 40,
        'total_final_amount' => 40,
        'delivery_mode' => 'pickup',
        'delivered_at' => now(),
    ], $attributes));
}

it('requires the super admin manager guard for customer management', function () {
    $this->getJson('/api/v1/manager/customers')->assertUnauthorized();

    $cashier = StaffUser::query()->create([
        'username' => 'customer_cashier',
        'email' => 'customer_cashier@example.test',
        'password' => Hash::make('password123'),
        'role' => 'cashier',
        'full_name' => 'Cashier',
        'phone' => '0501111111',
        'is_active' => true,
    ]);

    $this->actingAs($cashier, 'staff_api')
        ->getJson('/api/v1/manager/customers')
        ->assertForbidden();
});

it('creates customer profiles with phone verification pending and rejects equivalent duplicate numbers', function () {
    $manager = createManagerForCustomerTests();

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/customers', [
            'full_name' => 'نورة السبيعي',
            'phone' => '0559981240',
        ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'نورة السبيعي')
        ->assertJsonPath('data.phone', '+966559981240')
        ->assertJsonPath('data.email', null)
        ->assertJsonPath('data.is_phone_verified', false)
        ->assertJsonPath('data.is_manager_verified', false);

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/customers', [
            'full_name' => 'Duplicate',
            'phone' => '+966559981240',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');

    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '0559981240',
        'code' => '0000',
    ])->assertOk();
    expect(User::query()->where('phone', '+966559981240')->value('phone_verified_at'))->not->toBeNull();
});

it('persists phone verification when a customer completes OTP login', function () {
    $this->postJson('/api/v1/customer/auth/verify-otp', [
        'phone' => '0530697231',
        'code' => '0000',
    ])->assertOk();

    $this->assertDatabaseHas('users', [
        'phone' => '+966530697231',
    ]);
    expect(User::query()->where('phone', '+966530697231')->value('phone_verified_at'))->not->toBeNull();
});

it("updates a customer's phone and clears verification while revoking their tokens", function () {
    $manager = createManagerForCustomerTests();
    $customer = createManagedCustomer([
        'manager_verified_at' => now(),
        'manager_verified_by_staff_user_id' => $manager->id,
        'phone_verified_at' => now(),
    ]);
    $customer->createToken('customer-mobile');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/customers/'.$customer->id, [
            'full_name' => 'Updated customer',
            'phone' => '0551234567',
            'is_vip' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Updated customer')
        ->assertJsonPath('data.is_vip', true)
        ->assertJsonPath('data.is_phone_verified', false)
        ->assertJsonPath('data.is_manager_verified', false);

    $this->assertDatabaseHas('users', ['id' => $customer->id, 'phone' => '+966551234567']);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('records manager verification separately from phone verification and supports clearing it', function () {
    $manager = createManagerForCustomerTests();
    $customer = createManagedCustomer();

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/customers/'.$customer->id.'/verification', ['is_verified' => true])
        ->assertOk()
        ->assertJsonPath('data.is_manager_verified', true)
        ->assertJsonPath('data.phone_verified_at', null)
        ->assertJsonPath('data.manager_verified_by_staff_user_id', $manager->id);

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/customers/'.$customer->id.'/verification', ['is_verified' => false])
        ->assertOk()
        ->assertJsonPath('data.is_manager_verified', false);
});

it('blocks accounts and revokes active customer tokens', function () {
    $manager = createManagerForCustomerTests();
    $customer = createManagedCustomer();
    $customer->createToken('customer-mobile');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/customers/'.$customer->id.'/status', ['status' => 'blocked'])
        ->assertOk()
        ->assertJsonPath('data.status', 'blocked');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('returns computed customer metrics, segments, and only that customer order history', function () {
    $manager = createManagerForCustomerTests();
    $customer = createManagedCustomer(['is_vip' => true]);
    $otherCustomer = createManagedCustomer();
    createCustomerOrder($customer, ['total_final_amount' => 30]);
    createCustomerOrder($customer, ['total_final_amount' => 50]);
    createCustomerOrder($customer, ['order_status' => 'cancelled', 'delivered_at' => null]);
    createCustomerOrder($otherCustomer, ['total_final_amount' => 999]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/customers?segment=vip&sort_by=total_spent_amount')
        ->assertOk()
        ->assertJsonPath('data.0.total_orders_count', 2)
        ->assertJsonPath('data.0.total_spent_amount', '80.00')
        ->assertJsonPath('data.0.segments.0', 'vip');

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/customers/'.$customer->id)
        ->assertOk()
        ->assertJsonPath('data.total_orders_count', 2)
        ->assertJsonPath('data.total_spent_amount', '80.00')
        ->assertJsonPath('data.addresses', []);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/customers/'.$customer->id.'/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.customer.id', $customer->id);
});

it('returns customer KPIs and exports customer records as UTF-8 CSV', function () {
    $manager = createManagerForCustomerTests();
    $customer = createManagedCustomer(['is_vip' => true]);
    createCustomerOrder($customer, ['total_final_amount' => 84]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/customers/stats')
        ->assertOk()
        ->assertJsonPath('data.total_customers', 1)
        ->assertJsonPath('data.active_customers', 1)
        ->assertJsonPath('data.vip_customers', 1)
        ->assertJsonPath('data.average_order_value', '84.00');

    $response = $this->actingAs($manager, 'staff_api')
        ->get('/api/v1/manager/customers/export?status=active');
    $response->assertOk();
    expect(str_starts_with($response->streamedContent(), "\xEF\xBB\xBF"))->toBeTrue();
    expect($response->streamedContent())->toContain('Customer ');
});
