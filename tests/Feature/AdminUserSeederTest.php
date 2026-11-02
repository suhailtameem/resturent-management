<?php

use App\Models\StaffUser;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the requested admin account with a hashed password', function () {
    config(['services.manager_admin_seed_password' => '505050']);

    Artisan::call('db:seed', [
        '--class' => AdminUserSeeder::class,
        '--no-interaction' => true,
    ]);

    $admin = StaffUser::query()->where('username', 'admin')->firstOrFail();

    expect($admin->email)->toBe('admin@domain.com')
        ->and($admin->role)->toBe('super_admin')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('505050', $admin->password))->toBeTrue();
});

it('does not change the password when the admin account already exists', function () {
    $admin = StaffUser::create([
        'username' => 'admin',
        'email' => 'admin@domain.com',
        'password' => 'previous-password',
        'role' => 'super_admin',
        'full_name' => 'Administrator',
        'phone' => '0000000000',
        'is_active' => true,
    ]);

    Artisan::call('db:seed', [
        '--class' => AdminUserSeeder::class,
        '--no-interaction' => true,
    ]);

    expect(StaffUser::query()->count())->toBe(1)
        ->and(Hash::check('previous-password', $admin->refresh()->password))->toBeTrue()
        ->and(Artisan::output())->toContain('Admin account already exists; no changes were made.');
});
