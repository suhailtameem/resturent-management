<?php

use App\Models\StaffUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createDriverManager(): StaffUser
{
    return StaffUser::create([
        'username' => 'driver_manager',
        'email' => 'driver_manager@example.test',
        'password' => Hash::make('password123'),
        'role' => 'super_admin',
        'full_name' => 'Manager',
        'phone' => '0500000000',
        'is_active' => true,
    ]);
}

it('creates a courier and exposes manager-editable profile fields', function () {
    $manager = createDriverManager();

    $response = $this->actingAs($manager, 'staff_api')->postJson('/api/v1/manager/drivers', [
        'username' => 'courier_one',
        'email' => 'courier_one@example.test',
        'password' => 'secret-pass-123',
        'password_confirmation' => 'secret-pass-123',
        'full_name' => 'Courier One',
        'phone' => '0501111222',
        'vehicle_type' => 'Scooter',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.username', 'courier_one')
        ->assertJsonPath('data.email', 'courier_one@example.test')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonMissingPath('data.password');

    $this->assertDatabaseHas('staff_users', [
        'username' => 'courier_one',
        'role' => 'courier',
        'vehicle_type' => 'Scooter',
    ]);
});

it('updates a courier and allows an inactive courier to be reactivated', function () {
    $manager = createDriverManager();
    $courier = StaffUser::create([
        'username' => 'courier_two',
        'email' => 'courier_two@example.test',
        'password' => Hash::make('password123'),
        'role' => 'courier',
        'full_name' => 'Courier Two',
        'phone' => '0502222333',
        'is_active' => false,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/drivers/'.$courier->id, [
            'full_name' => 'Updated Courier',
            'is_active' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Courier')
        ->assertJsonPath('data.is_active', true);
});

it('deactivates couriers and revokes their tokens without deleting their history', function () {
    $manager = createDriverManager();
    $courier = StaffUser::create([
        'username' => 'courier_three',
        'email' => 'courier_three@example.test',
        'password' => Hash::make('password123'),
        'role' => 'courier',
        'full_name' => 'Courier Three',
        'phone' => '0503333444',
        'is_active' => true,
    ]);
    $courier->createToken('mobile');

    $this->actingAs($manager, 'staff_api')
        ->deleteJson('/api/v1/manager/drivers/'.$courier->id)
        ->assertOk();

    $this->assertDatabaseHas('staff_users', ['id' => $courier->id, 'is_active' => false]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('includes inactive couriers only when requested', function () {
    $manager = createDriverManager();
    StaffUser::create([
        'username' => 'inactive_courier',
        'email' => 'inactive_courier@example.test',
        'password' => Hash::make('password123'),
        'role' => 'courier',
        'full_name' => 'Inactive Courier',
        'phone' => '0504444555',
        'is_active' => false,
    ]);

    $this->actingAs($manager, 'staff_api')->getJson('/api/v1/manager/drivers')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/manager/drivers?include_inactive=true')
        ->assertOk()
        ->assertJsonPath('data.0.is_active', false);
});
