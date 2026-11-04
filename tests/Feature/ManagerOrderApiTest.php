<?php

use App\Events\OrderStatusUpdated;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CourierCashCollection;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemOption;
use App\Models\Product;
use App\Models\StaffUser;
use App\Models\StoreSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createManagerStaff(array $attributes = []): StaffUser
{
    return StaffUser::create(array_merge([
        'username' => 'manager',
        'email' => 'manager@example.test',
        'password' => Hash::make('password123'),
        'role' => 'super_admin',
        'full_name' => 'Manager User',
        'phone' => '+966500000000',
        'is_active' => true,
    ], $attributes));
}

function createCourierStaff(array $attributes = []): StaffUser
{
    return StaffUser::create(array_merge([
        'username' => 'courier_'.uniqid(),
        'email' => 'courier_'.uniqid().'@example.test',
        'password' => Hash::make('password123'),
        'role' => 'courier',
        'full_name' => 'طارق الزهراني',
        'phone' => '0509988112',
        'vehicle_type' => 'دباب / سكوتر',
        'is_active' => true,
        'is_available' => true,
    ], $attributes));
}

function createCustomerUser(array $attributes = []): User
{
    return User::create(array_merge([
        'name' => 'أبو ياسر',
        'phone' => '0530697231',
        'full_name' => 'أبو ياسر التميمي',
        'email' => 'customer_'.uniqid().'@example.test',
        'password' => Hash::make('password123'),
        'preferred_language' => 'ar',
        'is_vip' => true,
        'total_orders_count' => 12,
        'status' => 'active',
    ], $attributes));
}

function createTestOrder(array $attributes = []): Order
{
    $customer = $attributes['customer'] ?? createCustomerUser();
    unset($attributes['customer']);

    $address = CustomerAddress::create([
        'user_id' => $customer->id,
        'title_ar' => 'المنزل',
        'city_ar' => 'تبوك',
        'district_ar' => 'حي المروج',
        'street_details_ar' => 'حي المروج - فيلا رقم 14',
        'latitude' => 28.3998,
        'longitude' => 36.5715,
        'notes' => 'توصيل مباشر للباب • الدور الأرضي',
        'is_default' => true,
    ]);

    $category = Category::create([
        'name_ar' => 'محاشي',
        'slug' => 'stuffed-'.uniqid(),
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name_ar' => 'بوكس ميكس ورق عنب + ملفوف',
        'base_price' => 40.00,
        'is_available' => true,
    ]);

    $order = Order::create(array_merge([
        'order_number' => 'ARW-'.rand(1000, 9999),
        'user_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_phone' => $customer->phone,
        'delivery_address_id' => $address->id,
        'order_status' => 'pending',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'delivery_mode' => 'delivery',
        'subtotal_amount' => 57.00,
        'product_discount_amount' => 0.00,
        'coupon_discount_amount' => 0.00,
        'delivery_fee' => 12.00,
        'vat_tax_amount' => 9.00,
        'total_final_amount' => 69.00,
        'chef_special_notes' => 'دبس رمان زيادة',
        'estimated_prep_time_minutes' => 30,
    ], $attributes));

    $item = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name_ar_snapshot' => $product->name_ar,
        'size_name_ar_snapshot' => 'حجم كبير',
        'piece_count_snapshot' => 50,
        'unit_price' => 40.00,
        'quantity' => 1,
        'line_total' => 40.00,
        'special_instructions' => 'زيادة دبس الرمان',
    ]);

    OrderItemOption::create([
        'order_item_id' => $item->id,
        'option_name_ar_snapshot' => 'صوص دبس رمان غني إضافي',
        'option_price_snapshot' => 0.00,
    ]);

    return $order;
}

beforeEach(function () {
    StoreSettings::current()->update([
        'store_name_ar' => 'أرواق العنب',
        'store_name_en' => 'Arwaq Al-Eneab',
    ]);
});

it('requires manager authentication for orders endpoints', function () {
    $this->getJson('/api/v1/manager/orders')->assertUnauthorized();
    $this->getJson('/api/v1/manager/orders/stats')->assertUnauthorized();
    $this->getJson('/api/v1/manager/drivers')->assertUnauthorized();
});

it('returns orders KPI statistics to authorized manager', function () {
    $manager = createManagerStaff();
    $today = now();

    // Create 1 pending order created 10 minutes ago (urgent)
    $urgentOrder = createTestOrder([
        'order_status' => 'pending',
    ]);
    $urgentOrder->created_at = now()->subMinutes(10);
    $urgentOrder->save();

    // Create 1 preparing order
    createTestOrder(['order_status' => 'preparing']);

    // Create 1 out_for_delivery order
    createTestOrder(['order_status' => 'out_for_delivery']);

    // Create 1 delivered order today
    createTestOrder([
        'order_status' => 'delivered',
        'delivered_at' => $today,
        'total_final_amount' => 150.00,
    ]);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/orders/stats');

    $response->assertOk()
        ->assertJsonPath('data.needs_action_count', 1)
        ->assertJsonPath('data.preparing_count', 1)
        ->assertJsonPath('data.out_for_delivery_count', 1)
        ->assertJsonPath('data.completed_today_count', 1)
        ->assertJsonPath('data.has_urgent_orders', true)
        ->assertJsonPath('data.urgent_orders_count', 1)
        ->assertJsonPath('data.currency', 'SAR');
});

it('lists orders with filters and pagination matching manager app contract', function () {
    $manager = createManagerStaff();
    $order1 = createTestOrder(['order_status' => 'pending', 'payment_method' => 'cod', 'delivery_mode' => 'delivery']);
    $order2 = createTestOrder(['order_status' => 'preparing', 'payment_method' => 'online', 'delivery_mode' => 'pickup']);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/orders?status=pending');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $order1->id)
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.status_label', 'جديدة')
        ->assertJsonPath('data.0.delivery.type', 'delivery')
        ->assertJsonPath('data.0.currency', 'SAR')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'status',
                    'status_label',
                    'status_color_hex',
                    'is_urgent',
                    'created_at',
                    'time_ago',
                    'target_ready_time',
                    'remaining_minutes',
                    'customer' => ['id', 'name', 'phone', 'badge', 'badge_type'],
                    'delivery' => ['type', 'type_label', 'short_address', 'driver_name', 'driver_phone'],
                    'items_count',
                    'items_preview',
                    'notes',
                    'total_amount',
                    'currency',
                    'payment' => ['method', 'method_label', 'type', 'is_paid', 'status'],
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
});

it('filters orders by payment type and search term', function () {
    $manager = createManagerStaff();
    $cashOrder = createTestOrder(['order_number' => 'ARW-1111', 'payment_method' => 'cod', 'customer_phone' => '0533333333']);
    $onlineOrder = createTestOrder(['order_number' => 'ARW-2222', 'payment_method' => 'online', 'customer_phone' => '0544444444']);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/orders?payment_type=cash&search=1111');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_number', 'ARW-1111');
});

it('returns full order details matching manager app screen contract', function () {
    $manager = createManagerStaff();
    $courier = createCourierStaff(['full_name' => 'طارق الزهراني']);
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'preparing',
    ]);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson("/api/v1/manager/orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'preparing')
        ->assertJsonPath('data.status_label', 'قيد التجهيز')
        ->assertJsonPath('data.current_step_index', 1)
        ->assertJsonPath('data.delivery.driver.id', $courier->id)
        ->assertJsonPath('data.delivery.driver.name', 'طارق الزهراني')
        ->assertJsonStructure([
            'data' => [
                'id',
                'order_number',
                'status',
                'status_label',
                'current_step_index',
                'is_urgent',
                'created_at',
                'order_time_formatted',
                'target_delivery_time',
                'remaining_minutes',
                'customer' => [
                    'id',
                    'name',
                    'phone',
                    'badge',
                    'badge_type',
                    'total_orders_count',
                    'whatsapp_url',
                ],
                'delivery' => [
                    'type',
                    'type_label',
                    'city',
                    'neighborhood',
                    'street_address',
                    'building_details',
                    'latitude',
                    'longitude',
                    'google_maps_url',
                    'driver',
                ],
                'items' => [
                    '*' => [
                        'id',
                        'product_id',
                        'product_name',
                        'size_name',
                        'quantity',
                        'unit_price',
                        'line_total',
                        'selected_addons',
                        'notes',
                    ],
                ],
                'financials' => [
                    'items_subtotal',
                    'delivery_fee',
                    'discount_amount',
                    'coupon_code',
                    'tax_rate_percent',
                    'tax_amount',
                    'tax_included_in_subtotal',
                    'total_amount',
                    'currency',
                    'payment_method',
                    'payment_method_label',
                    'payment_type',
                    'payment_status',
                    'payment_status_label',
                ],
                'timeline' => [
                    '*' => [
                        'step_index',
                        'step_key',
                        'title',
                        'desc',
                        'completed',
                        'timestamp',
                    ],
                ],
            ],
        ]);
});

it('updates order workflow status and records audit log', function () {
    Event::fake([OrderStatusUpdated::class]);

    $manager = createManagerStaff();
    $order = createTestOrder(['order_status' => 'pending']);

    $response = $this->actingAs($manager, 'staff_api')
        ->patchJson("/api/v1/manager/orders/{$order->id}/status", [
            'status' => 'preparing',
            'note' => 'تم استلام الطلب وبدء التحضير بالمطبخ',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'preparing')
        ->assertJsonPath('data.status_label', 'قيد التجهيز')
        ->assertJsonPath('data.current_step_index', 1);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => 'preparing',
    ]);

    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => 'pending',
        'to_status' => 'preparing',
        'notes' => 'تم استلام الطلب وبدء التحضير بالمطبخ',
    ]);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->order['order_number'] === $order->order_number);
});

it('assigns a courier without dispatching the order before pickup', function () {
    Event::fake([OrderStatusUpdated::class]);

    $manager = createManagerStaff();
    $courier = createCourierStaff(['full_name' => 'سعد المطيري']);
    $order = createTestOrder(['order_status' => 'ready']);

    $response = $this->actingAs($manager, 'staff_api')
        ->postJson("/api/v1/manager/orders/{$order->id}/assign-driver", [
            'driver_id' => $courier->id,
            'notify_driver' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.status', 'ready')
        ->assertJsonPath('data.driver.id', $courier->id)
        ->assertJsonPath('data.driver.name', 'سعد المطيري');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'courier_id' => $courier->id,
        'order_status' => 'ready',
        'dispatched_at' => null,
    ]);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->order['delivery']['driver_name'] === 'سعد المطيري');
});

it('returns only the signed-in courier orders and dashboard totals', function () {
    $courier = createCourierStaff();
    $anotherCourier = createCourierStaff();
    $assignedOrder = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'ready',
    ]);
    createTestOrder([
        'courier_id' => $anotherCourier->id,
        'order_status' => 'out_for_delivery',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->getJson('/api/v1/manager/courier/dashboard')
        ->assertOk()
        ->assertJsonPath('data.is_available', true)
        ->assertJsonPath('data.stats.active_orders_count', 1)
        ->assertJsonPath('data.stats.pending_cash_amount', '0.00')
        ->assertJsonPath('data.orders.0.id', $assignedOrder->id)
        ->assertJsonPath('data.orders.0.status', 'ready')
        ->assertJsonMissingPath('data.orders.0.delivery.google_maps_url')
        ->assertJsonMissingPath('data.orders.0.delivery.latitude');
});

it('allows couriers to update availability but refuses other staff roles', function () {
    $courier = createCourierStaff();
    $manager = createManagerStaff();

    $this->actingAs($manager, 'staff_api')
        ->patchJson('/api/v1/manager/courier/availability', ['is_available' => false])
        ->assertForbidden();

    $this->actingAs($courier, 'staff_api')
        ->patchJson('/api/v1/manager/courier/availability', ['is_available' => false])
        ->assertOk()
        ->assertJsonPath('data.is_available', false);

    $this->assertDatabaseHas('staff_users', [
        'id' => $courier->id,
        'is_available' => false,
    ]);
});

it('records courier pickup and delivery with a COD collection and audit trail', function () {
    Event::fake([OrderStatusUpdated::class]);
    $courier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'ready',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->postJson("/api/v1/manager/courier/orders/{$order->id}/pickup")
        ->assertOk()
        ->assertJsonPath('data.status', 'out_for_delivery');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => 'out_for_delivery',
    ]);
    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => 'ready',
        'to_status' => 'out_for_delivery',
        'action_by_staff_id' => $courier->id,
    ]);

    $this->postJson("/api/v1/manager/courier/orders/{$order->id}/deliver")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => 'delivered',
        'payment_status' => 'paid',
    ]);
    $this->assertDatabaseHas('courier_cash_collections', [
        'order_id' => $order->id,
        'courier_id' => $courier->id,
        'amount' => '69.00',
    ]);
    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => 'out_for_delivery',
        'to_status' => 'delivered',
        'action_by_staff_id' => $courier->id,
    ]);

    $this->getJson('/api/v1/manager/courier/dashboard')
        ->assertOk()
        ->assertJsonPath('data.stats.active_orders_count', 0)
        ->assertJsonPath('data.stats.completed_today_count', 1)
        ->assertJsonPath('data.stats.pending_cash_amount', '69.00')
        ->assertJsonPath('data.completed_orders.0.id', $order->id)
        ->assertJsonPath('data.pending_cash_collections.0.order_number', $order->order_number);

    Event::assertDispatched(OrderStatusUpdated::class);
});

it('prevents couriers from changing an order assigned to another courier', function () {
    $courier = createCourierStaff();
    $anotherCourier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $anotherCourier->id,
        'order_status' => 'ready',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->postJson("/api/v1/manager/courier/orders/{$order->id}/pickup")
        ->assertNotFound();

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'courier_id' => $anotherCourier->id,
        'order_status' => 'ready',
    ]);
});

it('records a courier issue without cancelling the delivery', function () {
    $courier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'out_for_delivery',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->postJson("/api/v1/manager/courier/orders/{$order->id}/issues", [
            'reason' => 'العميل لا يرد على الاتصال',
        ])
        ->assertCreated()
        ->assertJsonPath('data.order_id', $order->id);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => 'out_for_delivery',
    ]);
    $this->assertDatabaseHas('order_status_logs', [
        'order_id' => $order->id,
        'from_status' => 'out_for_delivery',
        'to_status' => 'out_for_delivery',
        'notes' => 'بلاغ مندوب: العميل لا يرد على الاتصال',
    ]);

    $this->getJson('/api/v1/manager/courier/dashboard')
        ->assertOk()
        ->assertJsonPath('data.orders.0.delivery_issues.0.reason', 'العميل لا يرد على الاتصال');
});

it('rejects courier issue reports longer than the audit log can store', function () {
    $courier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'out_for_delivery',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->postJson("/api/v1/manager/courier/orders/{$order->id}/issues", [
            'reason' => str_repeat('x', 256),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});

it('does not record a cash collection when a courier delivers an online-paid order', function () {
    $courier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'out_for_delivery',
        'payment_method' => 'apple_pay',
        'payment_status' => 'paid',
    ]);

    $this->actingAs($courier, 'staff_api')
        ->postJson("/api/v1/manager/courier/orders/{$order->id}/deliver")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseCount('courier_cash_collections', 0);
});

it('reconciles pending courier cash against an auditable manager settlement', function () {
    $manager = createManagerStaff();
    $courier = createCourierStaff();
    $order = createTestOrder([
        'courier_id' => $courier->id,
        'order_status' => 'delivered',
        'payment_status' => 'paid',
        'delivered_at' => now(),
    ]);
    CourierCashCollection::create([
        'order_id' => $order->id,
        'courier_id' => $courier->id,
        'amount' => $order->total_final_amount,
        'collected_at' => now(),
    ]);

    $this->actingAs($courier, 'staff_api')
        ->getJson('/api/v1/manager/courier/dashboard')
        ->assertOk()
        ->assertJsonPath('data.stats.pending_cash_amount', '69.00');

    $settlementResponse = $this->actingAs($manager, 'staff_api')
        ->postJson("/api/v1/manager/drivers/{$courier->id}/cash-settlements")
        ->assertCreated()
        ->assertJsonPath('data.orders_count', 1)
        ->assertJsonPath('data.amount', '69.00');
    $settlementId = $settlementResponse->json('data.id');

    $this->assertDatabaseHas('courier_cash_collections', [
        'order_id' => $order->id,
        'settlement_id' => $settlementId,
    ]);
    $this->assertDatabaseHas('courier_cash_settlements', [
        'courier_id' => $courier->id,
        'reconciled_by_staff_user_id' => $manager->id,
        'amount' => '69.00',
    ]);
});

it('lists available drivers with active order counts', function () {
    $manager = createManagerStaff();
    $courier1 = createCourierStaff(['full_name' => 'طارق الزهراني']);
    $courier2 = createCourierStaff(['full_name' => 'سعد المطيري']);

    // Assign an active order to courier1
    createTestOrder(['courier_id' => $courier1->id, 'order_status' => 'out_for_delivery']);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson('/api/v1/manager/drivers');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_available', true);
});

it('cancels an order with reason and releases coupon redemption', function () {
    Event::fake([OrderStatusUpdated::class]);

    $manager = createManagerStaff();
    $customer = createCustomerUser();
    $coupon = Coupon::create([
        'code' => 'SAVE10',
        'discount_type' => 'fixed_amount',
        'discount_value' => 10.00,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
        'is_active' => true,
    ]);

    $order = createTestOrder([
        'customer' => $customer,
        'coupon_id' => $coupon->id,
        'order_status' => 'preparing',
    ]);

    CouponRedemption::create([
        'coupon_id' => $coupon->id,
        'user_id' => $customer->id,
        'order_id' => $order->id,
        'discount_amount' => 10.00,
    ]);

    $response = $this->actingAs($manager, 'staff_api')
        ->postJson("/api/v1/manager/orders/{$order->id}/cancel", [
            'reason' => 'نفاد كمية المكونات لليوم',
            'notify_customer' => true,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id)
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'نفاد كمية المكونات لليوم')
        ->assertJsonPath('message', 'تم إلغاء الطلب بنجاح');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'order_status' => 'cancelled',
        'cancellation_reason' => 'نفاد كمية المكونات لليوم',
    ]);

    $this->assertDatabaseMissing('coupon_redemptions', [
        'order_id' => $order->id,
    ]);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->order['status'] === 'cancelled');
});

it('generates thermal printer receipt payload for kitchen and invoice', function () {
    $manager = createManagerStaff();
    $order = createTestOrder(['order_status' => 'preparing']);

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson("/api/v1/manager/orders/{$order->id}/print-ticket?ticket_type=kitchen");

    $response->assertOk()
        ->assertJsonPath('data.order_number', $order->order_number)
        ->assertJsonPath('data.ticket_type', 'kitchen')
        ->assertJsonPath('data.store_name', 'أرواق العنب')
        ->assertJsonStructure([
            'data' => [
                'order_number',
                'printed_at',
                'ticket_type',
                'store_name',
                'kitchen_station',
                'customer_name',
                'delivery_type',
                'items',
                'order_notes',
                'raw_esc_pos_base64',
            ],
        ]);

    $this->actingAs($manager, 'staff_api')
        ->getJson("/api/v1/manager/orders/{$order->id}/print-ticket?ticket_type=unsupported")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ticket_type');
});

it('returns live sync polling results for new and updated orders', function () {
    $manager = createManagerStaff();
    $order = createTestOrder(['order_status' => 'pending']);

    $since = now()->subMinutes(5)->toIso8601String();

    $response = $this->actingAs($manager, 'staff_api')
        ->getJson("/api/v1/manager/orders/poll?since={$since}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'new_orders',
                'updated_orders',
                'stats' => [
                    'needs_action_count',
                    'preparing_count',
                    'out_for_delivery_count',
                    'completed_today_count',
                    'total_today_count',
                    'has_urgent_orders',
                    'urgent_orders_count',
                    'today_revenue',
                    'currency',
                ],
            ],
        ]);
});
