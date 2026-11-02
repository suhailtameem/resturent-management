<?php

use App\Models\DeliveryCity;
use App\Models\DeliveryNeighborhood;
use App\Models\StaffUser;
use App\Models\StoreOperation;
use App\Models\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createStoreSettingsManager(array $attributes = []): StaffUser
{
    return StaffUser::create(array_merge([
        'username' => 'store-manager',
        'email' => 'store-manager@example.test',
        'password' => Hash::make('password123'),
        'role' => 'super_admin',
        'full_name' => 'Store Manager',
        'phone' => '+966500000000',
        'is_active' => true,
    ], $attributes));
}

function storeHoursWeeklySchedule(array $sundaySessions = []): array
{
    return collect(['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'])
        ->map(fn (string $day): array => [
            'day_of_week' => $day,
            'is_active' => $day === 'sunday' && $sundaySessions !== [],
            'sessions' => $day === 'sunday' ? $sundaySessions : [],
        ])
        ->all();
}

it('requires an authenticated super admin to access store settings', function () {
    $this->getJson('/api/v1/manager/settings')
        ->assertUnauthorized();

    $cashier = createStoreSettingsManager([
        'username' => 'store-cashier',
        'email' => 'store-cashier@example.test',
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier, 'staff_api')
        ->getJson('/api/v1/manager/settings')
        ->assertForbidden()
        ->assertJsonPath('code', 'INSUFFICIENT_ROLE');
});

it('creates and returns default settings when the manager reads settings for the first time', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/settings')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'store_name_ar' => 'اسم المتجر',
                'store_name_en' => 'Store Name',
                'address' => null,
                'support_phone' => null,
                'payment_methods' => [
                    'cash_on_delivery_enabled' => true,
                    'online_enabled' => true,
                ],
                'auto_accept_prepaid_orders' => false,
            ],
        ]);

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'store_name_ar' => 'اسم المتجر',
        'store_name_en' => 'Store Name',
        'cash_on_delivery_enabled' => true,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => false,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/settings')
        ->assertOk();

    $this->assertDatabaseCount('store_settings', 1);
});

it('preserves existing store settings when the manager reads them', function () {
    $manager = createStoreSettingsManager();
    StoreSettings::create([
        'id' => 1,
        'store_name_ar' => 'مطعم مخصص',
        'store_name_en' => 'Custom Restaurant',
        'address' => 'Tabuk, Saudi Arabia',
        'support_phone' => '+966511111111',
        'cash_on_delivery_enabled' => false,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => true,
        'currency_label_ar' => 'ريال',
        'currency_label_en' => 'Saudi Riyal',
    ]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/settings')
        ->assertOk()
        ->assertJsonPath('data.store_name_ar', 'مطعم مخصص')
        ->assertJsonPath('data.store_name_en', 'Custom Restaurant')
        ->assertJsonPath('data.address', 'Tabuk, Saudi Arabia')
        ->assertJsonPath('data.support_phone', '+966511111111')
        ->assertJsonPath('data.payment_methods.cash_on_delivery_enabled', false)
        ->assertJsonPath('data.payment_methods.online_enabled', true)
        ->assertJsonPath('data.auto_accept_prepaid_orders', true);

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'store_name_ar' => 'مطعم مخصص',
        'store_name_en' => 'Custom Restaurant',
        'address' => 'Tabuk, Saudi Arabia',
        'support_phone' => '+966511111111',
        'cash_on_delivery_enabled' => false,
        'online_enabled' => true,
        'auto_accept_prepaid_orders' => true,
        'currency_label_ar' => 'ريال',
        'currency_label_en' => 'Saudi Riyal',
    ]);

    $this->assertDatabaseCount('store_settings', 1);
});

it('updates store profile and returns the persisted settings', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/settings', [
            'store_name_ar' => 'مطبخ أرواق العنب',
            'store_name_en' => 'Arwaq Al-Eneab',
            'address' => 'Tabuk, Saudi Arabia',
            'support_phone' => '+966500000000',
            'payment_methods' => [
                'cash_on_delivery_enabled' => false,
                'online_enabled' => true,
            ],
            'auto_accept_prepaid_orders' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.store_name_ar', 'مطبخ أرواق العنب')
        ->assertJsonPath('data.payment_methods.cash_on_delivery_enabled', false)
        ->assertJsonPath('data.payment_methods.online_enabled', true)
        ->assertJsonPath('data.auto_accept_prepaid_orders', true);

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'store_name_ar' => 'مطبخ أرواق العنب',
        'support_phone' => '+966500000000',
        'cash_on_delivery_enabled' => false,
        'online_enabled' => true,
    ]);
});

it('rejects a profile update that disables every payment method', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/settings', [
            'payment_methods' => [
                'cash_on_delivery_enabled' => false,
                'online_enabled' => false,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_methods']);

    expect(StoreSettings::current()->cash_on_delivery_enabled)->toBeTrue()
        ->and(StoreSettings::current()->online_enabled)->toBeTrue();
});

it('reads and updates fixed SAR display labels', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/store/settings')
        ->assertOk()
        ->assertJsonPath('data.currency_code', 'SAR')
        ->assertJsonPath('data.currency_label_ar', 'ر.س')
        ->assertJsonPath('data.currency_label_en', 'SAR');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/store/settings', [
            'currency_label_ar' => 'ريال',
            'currency_label_en' => 'Saudi Riyal',
        ])
        ->assertOk()
        ->assertJsonPath('data.currency_code', 'SAR')
        ->assertJsonPath('data.currency_label_ar', 'ريال')
        ->assertJsonPath('data.currency_label_en', 'Saudi Riyal');

    $this->assertDatabaseHas('store_settings', [
        'id' => 1,
        'currency_label_ar' => 'ريال',
        'currency_label_en' => 'Saudi Riyal',
    ]);
});

it('does not allow changing the store currency code', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/store/settings', [
            'currency_code' => 'USD',
            'currency_label_ar' => 'ر.س',
            'currency_label_en' => 'SAR',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency_code']);
});

it('returns seven stable operating days and saves the weekly schedule', function () {
    $manager = createStoreSettingsManager();
    $schedule = storeHoursWeeklySchedule([
        [
            'name' => 'dinner',
            'opens_at' => '18:30',
            'closes_at' => '00:30',
            'closes_next_day' => true,
        ],
    ]);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/hours')
        ->assertOk()
        ->assertJsonCount(7, 'data.weekly_schedule')
        ->assertJsonPath('data.timezone', 'Asia/Riyadh');

    $this->actingAs($manager, 'staff_api')
        ->putJson('/api/v1/manager/hours', [
            'orders_per_hour_limit' => 25,
            'estimated_prep_time_minutes' => 35,
            'rush_extra_time_enabled' => true,
            'weekly_schedule' => $schedule,
        ])
        ->assertOk()
        ->assertJsonPath('data.orders_per_hour_limit', 25)
        ->assertJsonPath('data.estimated_prep_time_minutes', 35)
        ->assertJsonPath('data.rush_extra_time_enabled', true)
        ->assertJsonPath('data.weekly_schedule.0.day_of_week', 'sunday')
        ->assertJsonPath('data.weekly_schedule.0.sessions.0.closes_next_day', true);

    $this->assertDatabaseHas('store_operations', [
        'id' => 1,
        'orders_per_hour_limit' => 25,
        'estimated_prep_time_minutes' => 35,
        'rush_extra_time_enabled' => true,
    ]);
});

it('rejects overlapping operating sessions', function () {
    $manager = createStoreSettingsManager();
    $schedule = storeHoursWeeklySchedule([
        [
            'name' => 'lunch',
            'opens_at' => '12:00',
            'closes_at' => '16:00',
            'closes_next_day' => false,
        ],
        [
            'name' => 'dinner',
            'opens_at' => '15:30',
            'closes_at' => '18:00',
            'closes_next_day' => false,
        ],
    ]);

    $this->actingAs($manager, 'staff_api')
        ->putJson('/api/v1/manager/hours', [
            'orders_per_hour_limit' => 18,
            'estimated_prep_time_minutes' => 30,
            'rush_extra_time_enabled' => false,
            'weekly_schedule' => $schedule,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekly_schedule.0.sessions.1.closes_at']);
});

it('pauses until midnight in the store timezone and resumes after expiry', function () {
    $manager = createStoreSettingsManager();
    $this->travelTo(Carbon::parse('2026-10-29 12:00:00', 'UTC'));

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/hours/pause', ['until_tomorrow' => true])
        ->assertOk()
        ->assertJsonPath('data.is_open', false)
        ->assertJsonPath('data.paused_until', '2026-10-29T21:00:00+00:00');

    $this->travelTo(Carbon::parse('2026-10-30 00:01:00', 'UTC'));

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/hours')
        ->assertOk()
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.paused_until', null);

    expect(StoreOperation::current()->paused_until)->toBeNull();
});

it('closes order intake indefinitely and resumes it on request', function () {
    $manager = createStoreSettingsManager();

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/hours/pause', ['duration_minutes' => 30])
        ->assertOk()
        ->assertJsonPath('data.is_open', false);

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/hours/close')
        ->assertOk()
        ->assertJsonPath('data.is_open', false)
        ->assertJsonPath('data.paused_until', null);

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/hours/resume')
        ->assertOk()
        ->assertJsonPath('data.is_open', true)
        ->assertJsonPath('data.paused_until', null);
});

it('creates and updates delivery cities and neighborhoods', function () {
    $manager = createStoreSettingsManager();

    $cityResponse = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/delivery-cities', [
            'name' => ' تبوك ',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'تبوك')
        ->assertJsonPath('data.service_status', 'active')
        ->assertJsonCount(0, 'data.neighborhoods');
    $cityId = $cityResponse->json('data.id');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/delivery-cities/'.$cityId, [
            'service_status' => 'preorder_only',
        ])
        ->assertOk()
        ->assertJsonPath('data.service_status', 'preorder_only');

    $neighborhoodResponse = $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/delivery-cities/'.$cityId.'/neighborhoods', [
            'name' => 'حي المروج',
            'delivery_fee' => 12,
            'minimum_order_amount' => 35,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.delivery_fee', 12)
        ->assertJsonPath('data.minimum_order_amount', 35);
    $neighborhoodId = $neighborhoodResponse->json('data.id');

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/delivery-neighborhoods/'.$neighborhoodId, [
            'delivery_fee' => 18,
            'minimum_order_amount' => 50,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.delivery_fee', 18)
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/delivery-cities')
        ->assertOk()
        ->assertJsonPath('data.0.neighborhoods.0.id', (string) $neighborhoodId)
        ->assertJsonPath('data.0.neighborhoods.0.delivery_fee', 18);

    $this->assertDatabaseHas('delivery_neighborhoods', [
        'id' => $neighborhoodId,
        'delivery_city_id' => $cityId,
        'delivery_fee' => 18,
        'is_active' => false,
    ]);
});

it('rejects duplicate delivery city names after trimming', function () {
    $manager = createStoreSettingsManager();
    DeliveryCity::create(['name' => 'تبوك', 'service_status' => 'active']);

    $this->actingAs($manager, 'staff_api')
        ->postJson('/api/v1/manager/delivery-cities', ['name' => ' تبوك '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects a neighborhood update with no editable fields', function () {
    $manager = createStoreSettingsManager();
    $city = DeliveryCity::create(['name' => 'تبوك', 'service_status' => 'active']);
    $neighborhood = DeliveryNeighborhood::create([
        'delivery_city_id' => $city->id,
        'name' => 'حي المروج',
        'delivery_fee' => 12,
        'minimum_order_amount' => 35,
        'is_active' => true,
    ]);

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/delivery-neighborhoods/'.$neighborhood->id, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['neighborhood']);
});
