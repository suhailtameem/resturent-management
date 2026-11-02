<?php

namespace Database\Seeders;

use App\Models\StaffUser;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminExists = StaffUser::query()
            ->where('username', 'admin')
            ->orWhere('email', 'admin@domain.com')
            ->exists();

        if ($adminExists) {
            $this->command?->info('Admin account already exists; no changes were made.');

            return;
        }

        $password = config('services.manager_admin_seed_password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Set MANAGER_ADMIN_SEED_PASSWORD before running the admin seeder.');
        }

        StaffUser::create([
            'username' => 'admin',
            'email' => 'admin@domain.com',
            'password' => $password,
            'role' => 'super_admin',
            'full_name' => 'Administrator',
            'phone' => '0000000000',
            'is_active' => true,
        ]);

        $this->command?->info('Admin account created successfully.');
    }
}
