<?php

use App\Jobs\SendNotificationRecipient;
use App\Models\DeviceToken;
use App\Models\StaffUser;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function configureFirebaseCredentials(): void
{
    $privateKey = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDnTP1QUuYMsxZG
/yCl8KUwfJyYYGjVxxiT9QvoI39tGe89aLu4ywMcosND8O/EV39S7WM/x4kaMZTJ
bFnqajItN/03pakR3t69xxlIWFj1eYQXmKXbHKw0uN2FQU4r4vCKzYXH6Vby3SOi
uMIHhFs9Vk9257mhcaftDJIAcbK98k3p8me92EMhewZTnwH9ncaa4al9IoTqPvlZ
PmAWXQVG9VP69fOjFaun1wsiFdNpY6kJc85jIMcDfyTvGNJMwslwli/66/inpr5U
Yiqm7obhV0ubxSNbi2n/F+cHt5PMv3EXnwmFOFLar0jDKb63sY5vyuQrnhqLYfh+
tpxyfg0bAgMBAAECggEAAdctftmiBgDY0QjPsDJZhii/sEC4JNdYPTkqdpbtuScH
LdRrBf3rkOtEpYJBziyqETVdYFEMqL4zQmUP1BszOgPjinFRhphg6l6ut1Iopw9n
+y4WR8eTITpFXP2XiybbpicbhOs462bSFUXmGVw2w6avrDEM25mFbg/3C3TcKMHE
eCEHnAWtmhzfgSU62K5P1RxzkUVz4s48XvC6sBxNbne6YXdrADDuw0Nh7K9n1Kud
Umnvpc+bVvj7bdbcuU1F4Y8Isi+7zKM+OvkgiHQix7Z0OuJghpGF7txC9aaoxvAw
Qpra+zgCcAUddKzcoxZus23HyubnF+hMRbODGNIi9QKBgQD6d+rlSU1ErkuC+SUw
N8YnQy9UyaVccZ71ZFD/f274RPKXXwmnknHUBJFTb9TZyRI2KGDjnDeuSs8eD4fN
f56gwzy/kiYQF3nCybDM9TVBzJf6ZRIrCP8kp6Jq0PuyRFlHVFird950osOB4X0q
tP9S3eccFzPaTqF3axzJzJu+hQKBgQDsaLPw4WE1CqNOU/qut8YwcFjmoc6RpAJa
RGcOMsrucKDg1u3JMFXQLaHpu+jaLADyQGz3LGZyBlX/G0/x0kwTpsOXkoiNz7mw
FXCQtRO5fyfnwFd2giP0pakeAWnv91uC9gaCV3rtLRh1ve22pKgHHNI/CAwAg705
pvlmfQZ/HwKBgFL5jRCeYwGguBVQklhR8cwp/rEZCDYC0gU7UXYC8nE+7hrleh5Q
bnMtfDNm9d2cNfNJFyfwJaYQ1GKCBJw4bgWWCDbypLdp/4VfKboMdbMxoxtphkZJ
ebTb7/tw9iENcYIm2CUd1c1P6nLJj5ypT3hVWO2+ZggpSmZ0TpovtVy9AoGBAOdy
8ogn/2VQjuVGrqyTW0NdGoxhab2Ilo0NXt+1tpuORNMbWR2ZZsozgss0nxnJ7vs9
O/EZ1QpZTaQ+dxxHSRgMMn2YhnxgHGlZ9CSYQ0FeThFEa9yoOM9xyIqJ08+Nmlgy
ydieEMlXVxUe5zXjKmYcP3JaTHXMhFuHL7C5Qrz9AoGBANJrKMqCEHSdMVFCD3+3
yrPtOPL1QNYNAaD2QidwyZFv/GVU4xmxYlp2TerFsqojAAzSRKr5Fbf9q2rE7x9z
fsq0PD5iq4X09AATXj56zlbT8UyMXIEiFYQ8XxEpf39R1E7w9lBTS3C7WoloCVts
31aESJJzIL8tFRO97o/DawnP
-----END PRIVATE KEY-----
PEM;
    config(['services.firebase.project_id' => 'project-test', 'services.firebase.client_email' => 'sender@example.test', 'services.firebase.private_key' => $privateKey]);
}

it('sends an FCM HTTP v1 message with the notification payload', function () {
    configureFirebaseCredentials();
    Http::fakeSequence()
        ->push(['access_token' => 'oauth-token', 'expires_in' => 3600], 200)
        ->push(['name' => 'projects/project-test/messages/123'], 200);
    $device = DeviceToken::query()->create(['token' => 'customer-device-token', 'platform' => 'ios']);

    $result = app(FirebaseNotificationService::class)->send($device, ['notification_id' => '4', 'destination' => '/orders'], 'Title', 'Body');

    expect($result)->toBe(['message_id' => 'projects/project-test/messages/123', 'invalid' => false, 'error' => null]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/messages:send')
        && $request['message']['fid'] === 'customer-device-token'
        && $request['message']['notification']['title'] === 'Title'
        && $request['message']['data']['notification_id'] === '4');
});

it('returns Firebase rejection details for permanent send failures', function () {
    configureFirebaseCredentials();
    Http::fakeSequence()
        ->push(['access_token' => 'oauth-token'], 200)
        ->push(['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Invalid FID']], 400);
    $device = DeviceToken::query()->create(['token' => 'invalid-fid', 'platform' => 'android']);

    $result = app(FirebaseNotificationService::class)->send($device, [], 'Title', 'Body');

    expect($result['invalid'])->toBeFalse()
        ->and($result['error'])->toContain('HTTP 400, INVALID_ARGUMENT')
        ->and($result['error'])->toContain('Invalid FID');
    $this->assertDatabaseHas('device_tokens', ['id' => $device->id]);
});

it('removes an unregistered token but retains it for transient errors', function () {
    configureFirebaseCredentials();
    Http::fakeSequence()
        ->push(['access_token' => 'oauth-token'], 200)
        ->push(['error' => ['status' => 'UNREGISTERED']], 404);
    $device = DeviceToken::query()->create(['token' => 'expired-device-token', 'platform' => 'android']);

    $result = app(FirebaseNotificationService::class)->send($device, [], 'Title', 'Body');
    expect($result['invalid'])->toBeTrue()
        ->and($result['error'])->toContain('unregistered');
    $this->assertDatabaseMissing('device_tokens', ['id' => $device->id]);

    Http::fakeSequence()->push(['access_token' => 'oauth-token'], 200)->push(['error' => ['status' => 'UNAVAILABLE']], 503);
    $temporary = DeviceToken::query()->create(['token' => 'temporary-failure-token', 'platform' => 'android']);
    expect(fn () => app(FirebaseNotificationService::class)->send($temporary, [], 'Title', 'Body'))->toThrow(RuntimeException::class);
    $this->assertDatabaseHas('device_tokens', ['id' => $temporary->id]);
});

it('protects manager notification endpoints and customer open tracking', function () {
    $this->getJson('/api/v1/manager/notifications/reach')->assertUnauthorized();
    $this->getJson('/api/v1/manager/notifications')->assertUnauthorized();
    $this->postJson('/api/v1/manager/notifications', [])->assertUnauthorized();
    $this->postJson('/api/v1/notifications/1/opened')->assertUnauthorized();
});

it('queues notifications only for customer-owned devices and returns send statistics', function () {
    Bus::fake();
    $manager = StaffUser::query()->create([
        'username' => 'notification_manager', 'email' => 'notify-manager@example.test',
        'password' => Hash::make('password123'), 'role' => 'super_admin',
        'full_name' => 'Notification Manager', 'phone' => '0500000000', 'is_active' => true,
    ]);
    $customer = User::query()->create([
        'name' => 'Customer', 'full_name' => 'Customer', 'email' => 'notify-customer@example.test',
        'phone' => '0500000001', 'password' => Hash::make('password123'),
        'preferred_language' => 'ar', 'status' => 'active',
    ]);
    DeviceToken::query()->create(['user_id' => $customer->id, 'token' => 'customer-token', 'platform' => 'android']);
    DeviceToken::query()->create(['staff_user_id' => $manager->id, 'token' => 'manager-token', 'platform' => 'android']);

    $response = $this->actingAs($manager, 'staff_api')->postJson('/api/v1/manager/notifications', [
        'title' => 'A title', 'body' => 'A message', 'customer_ids' => [$customer->id],
        'idempotency_key' => 'request-key-1', 'destination' => '/orders', 'coupon_code' => 'TRY10',
    ])->assertAccepted()->assertJsonPath('data.total_recipients', 1)
        ->assertJsonPath('data.targeted_devices', 1)
        ->assertJsonPath('data.customers_without_devices', 0);

    $this->actingAs($manager, 'staff_api')->postJson('/api/v1/manager/notifications', [
        'title' => 'A title', 'body' => 'A message', 'customer_ids' => [$customer->id],
        'idempotency_key' => 'request-key-1', 'destination' => '/orders', 'coupon_code' => 'TRY10',
    ])->assertAccepted()->assertJsonPath('data.id', $response->json('data.id'));

    $this->assertDatabaseCount('notification_recipients', 1);
    $this->assertDatabaseCount('notifications', 1);
    $this->assertDatabaseHas('device_tokens', ['token' => 'manager-token']);
    $this->assertDatabaseHas('notifications', ['idempotency_key' => 'request-key-1', 'created_by' => $manager->id]);
    Bus::assertDispatched(SendNotificationRecipient::class, 1);
});
