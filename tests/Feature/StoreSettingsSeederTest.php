<?php

use App\Models\StoreSettings;
use Database\Seeders\StoreSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates generic store settings and operation defaults', function () {
    Artisan::call('db:seed', [
        '--class' => StoreSettingsSeeder::class,
        '--no-interaction' => true,
    ]);

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'store_name_ar' => 'اسم المتجر',
        'store_name_en' => 'Store Name',
        'address' => null,
        'support_phone' => null,
        'cash_on_delivery_enabled' => true,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => false,
        'currency_label_ar' => 'ر.س',
        'currency_label_en' => 'SAR',
    ]);
    $this->assertDatabaseHas('store_operations', [
        'id' => 1,
        'is_open' => true,
        'timezone' => 'Asia/Riyadh',
    ]);

    Artisan::call('db:seed', [
        '--class' => StoreSettingsSeeder::class,
        '--no-interaction' => true,
    ]);

    $this->assertDatabaseCount('store_settings', 1);
    $this->assertDatabaseCount('store_operations', 1);
});

it('does not overwrite existing store settings', function () {
    StoreSettings::create([
        'id' => 1,
        'store_name_ar' => 'متجر مخصص',
        'store_name_en' => 'Custom Store',
        'address' => 'Custom address',
        'support_phone' => '+966511111111',
        'cash_on_delivery_enabled' => false,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => true,
        'currency_label_ar' => 'ريال',
        'currency_label_en' => 'Saudi Riyal',
    ]);

    Artisan::call('db:seed', [
        '--class' => StoreSettingsSeeder::class,
        '--no-interaction' => true,
    ]);

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'store_name_ar' => 'متجر مخصص',
        'store_name_en' => 'Custom Store',
        'address' => 'Custom address',
        'support_phone' => '+966511111111',
        'cash_on_delivery_enabled' => false,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => true,
        'currency_label_ar' => 'ريال',
        'currency_label_en' => 'Saudi Riyal',
    ]);
    $this->assertDatabaseCount('store_settings', 1);
});
