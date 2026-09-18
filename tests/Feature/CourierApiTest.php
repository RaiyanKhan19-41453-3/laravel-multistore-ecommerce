<?php

use App\Models\Courier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Couriers\CourierGatewayFactory;
use App\Services\Couriers\Gateways\ECourierGateway;
use App\Services\Couriers\Gateways\PaperflyGateway;
use App\Services\Couriers\Gateways\RedXGateway;
use App\Services\Couriers\Gateways\SAParibahanGateway;
use App\Services\Couriers\Gateways\SteadfastGateway;
use App\Services\Couriers\Gateways\SundarbanGateway;

function createCourierAdmin(): User
{
    ensureStaffPermissions();

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    return $user;
}

function createCourierOrder(string $status = 'confirmed'): Order
{
    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 1000,
        'total' => 1000,
        'shipping_name' => 'Test Customer',
        'shipping_phone' => '01712345678',
        'shipping_address' => '123 Test St',
        'shipping_city' => 'Dhaka',
    ]);
    OrderItem::factory()->count(2)->for($order)->create();

    return $order;
}

it('can list couriers', function () {
    $admin = createCourierAdmin();
    Courier::factory()->count(3)->create();

    $response = $this->actingAs($admin)->get('/admin/couriers');

    $response->assertOk();
});

it('can create a courier', function () {
    $admin = createCourierAdmin();

    $response = $this->actingAs($admin)->post('/admin/couriers', [
        'name' => 'Test Courier',
        'code' => 'test_courier',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('couriers', ['code' => 'test_courier']);
});

it('can update courier settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'pathao']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'client_id' => 'test-id',
            'client_secret' => 'test-secret',
            'sandbox' => true,
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-id', $courier->settings['client_id']);
    $this->assertTrue($courier->settings['sandbox']);
});

it('can delete a courier', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/couriers/{$courier->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('couriers', ['id' => $courier->id]);
});

it('requires admin role to manage couriers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/admin/couriers');

    $response->assertForbidden();
});

it('test connection returns failure for unconfigured courier', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'pathao']);

    $response = $this->actingAs($admin)->post("/admin/couriers/{$courier->id}/test-connection");

    $response->assertJson(['success' => false]);
});

it('courier code must be unique', function () {
    $admin = createCourierAdmin();
    Courier::factory()->create(['code' => 'pathao']);

    $response = $this->actingAs($admin)->post('/admin/couriers', [
        'name' => 'Another Pathao',
        'code' => 'pathao',
    ]);

    $response->assertSessionHasErrors(['code']);
});

it('can send order to courier via API', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create([
        'code' => 'pathao',
        'settings' => null,
    ]);
    $order = createCourierOrder('confirmed');

    $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/send-to-courier", [
        'courier_id' => $courier->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['courier_id']);
});

it('webhook endpoint accepts pathao status updates', function () {
    $courier = Courier::factory()->create(['code' => 'pathao']);
    $order = createCourierOrder('shipped');
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'courier_id' => $courier->id,
        'courier_order_id' => 'PATHAO-123',
        'status' => 'in_transit',
    ]);

    $response = $this->postJson('/api/webhooks/pathao', [
        'event' => 'order.delivered',
        'data' => [
            'consignment_id' => 'PATHAO-123',
            'store_id' => 12345,
        ],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('shipments', [
        'id' => $shipment->id,
        'status' => 'delivered',
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'status' => 'delivered',
    ]);
});

it('webhook ignores unknown consignment', function () {
    $response = $this->postJson('/api/webhooks/pathao', [
        'event' => 'order.delivered',
        'data' => [
            'consignment_id' => 'UNKNOWN-999',
        ],
    ]);

    $response->assertOk()->assertJson(['status' => 'not_found']);
});

it('can update redx courier settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'redx']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'api_token' => 'test-jwt-token',
            'sandbox' => true,
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-jwt-token', $courier->settings['api_token']);
    $this->assertTrue($courier->settings['sandbox']);
});

it('redx gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'redx']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('redx'));
});

it('redx gateway fails test connection without settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'redx']);

    $response = $this->actingAs($admin)->post("/admin/couriers/{$courier->id}/test-connection");

    $response->assertJson(['success' => false]);
});

it('redx gateway cannot create shipment without settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'redx', 'settings' => null]);
    $order = createCourierOrder('confirmed');

    $response = $this->actingAs($admin)->post("/admin/orders/{$order->id}/send-to-courier", [
        'courier_id' => $courier->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors(['courier_id']);
});

it('redx gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'redx', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('redx gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'redx',
        'settings' => ['api_token' => 'test-token', 'sandbox' => true],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(RedXGateway::class, $gateway);
});

it('can update paperfly courier settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'paperfly']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'merchant_id' => 'test-merchant-id',
            'username' => 'test-user',
            'password' => 'test-pass',
            'sandbox' => false,
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-merchant-id', $courier->settings['merchant_id']);
    $this->assertEquals('test-user', $courier->settings['username']);
});

it('paperfly gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'paperfly']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('paperfly'));
});

it('paperfly gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'paperfly', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('paperfly gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'paperfly',
        'settings' => ['merchant_id' => 'test-id', 'username' => 'user', 'password' => 'pass'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(PaperflyGateway::class, $gateway);
});

it('paperfly gateway throws on cancel', function () {
    $courier = Courier::factory()->create([
        'code' => 'paperfly',
        'settings' => ['merchant_id' => 'test-id', 'username' => 'user', 'password' => 'pass'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('can update steadfast courier settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'steadfast']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'sandbox' => false,
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-api-key', $courier->settings['api_key']);
    $this->assertEquals('test-secret-key', $courier->settings['secret_key']);
});

it('steadfast gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'steadfast']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('steadfast'));
});

it('steadfast gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'steadfast', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('steadfast gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'steadfast',
        'settings' => ['api_key' => 'test-key', 'secret_key' => 'test-secret'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(SteadfastGateway::class, $gateway);
});

it('steadfast gateway throws on cancel', function () {
    $courier = Courier::factory()->create([
        'code' => 'steadfast',
        'settings' => ['api_key' => 'test-key', 'secret_key' => 'test-secret'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('can update ecourier settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'ecourier']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'user_id' => 'test-user-id',
            'api_key' => 'test-api-key',
            'sandbox' => false,
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-user-id', $courier->settings['user_id']);
    $this->assertEquals('test-api-key', $courier->settings['api_key']);
});

it('ecourier gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'ecourier']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('ecourier'));
});

it('ecourier gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'ecourier', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('ecourier gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'ecourier',
        'settings' => ['user_id' => 'test-user', 'api_key' => 'test-key'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(ECourierGateway::class, $gateway);
});

it('ecourier gateway throws on cancel', function () {
    $courier = Courier::factory()->create([
        'code' => 'ecourier',
        'settings' => ['user_id' => 'test-user', 'api_key' => 'test-key'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('can update sa_paribahan settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'sa_paribahan']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'api_key' => 'test-api-token',
            'booking_branch' => 'Dhaka',
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-api-token', $courier->settings['api_key']);
    $this->assertEquals('Dhaka', $courier->settings['booking_branch']);
});

it('sa_paribahan gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'sa_paribahan']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('sa_paribahan'));
});

it('sa_paribahan gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'sa_paribahan', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('sa_paribahan gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'sa_paribahan',
        'settings' => ['api_key' => 'test-token', 'booking_branch' => 'Dhaka'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(SAParibahanGateway::class, $gateway);
});

it('sa_paribahan gateway throws on cancel', function () {
    $courier = Courier::factory()->create([
        'code' => 'sa_paribahan',
        'settings' => ['api_key' => 'test-token', 'booking_branch' => 'Dhaka'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});

it('can update sundarban settings', function () {
    $admin = createCourierAdmin();
    $courier = Courier::factory()->create(['code' => 'sundarban']);

    $response = $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'api_key' => 'test-api-token',
            'booking_user_id' => 'test-user-id',
        ],
    ]);

    $response->assertRedirect();
    $courier->refresh();
    $this->assertEquals('test-api-token', $courier->settings['api_key']);
    $this->assertEquals('test-user-id', $courier->settings['booking_user_id']);
});

it('sundarban gateway shows as supports_api', function () {
    Courier::factory()->create(['code' => 'sundarban']);

    $this->assertTrue(CourierGatewayFactory::supportsApi('sundarban'));
});

it('sundarban gateway factory returns null for empty settings', function () {
    $courier = Courier::factory()->create(['code' => 'sundarban', 'settings' => null]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertNull($gateway);
});

it('sundarban gateway factory returns instance with valid settings', function () {
    $courier = Courier::factory()->create([
        'code' => 'sundarban',
        'settings' => ['api_key' => 'test-token', 'booking_user_id' => 'test-user'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->assertInstanceOf(SundarbanGateway::class, $gateway);
});

it('sundarban gateway throws on cancel', function () {
    $courier = Courier::factory()->create([
        'code' => 'sundarban',
        'settings' => ['api_key' => 'test-token', 'booking_user_id' => 'test-user'],
    ]);

    $gateway = CourierGatewayFactory::make($courier);

    $this->expectException(RuntimeException::class);
    $gateway->cancelShipment('TEST-123');
});
