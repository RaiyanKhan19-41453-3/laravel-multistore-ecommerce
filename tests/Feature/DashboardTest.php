<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\DashboardService;
use Database\Seeders\PermissionSeeder;

test('guests are redirected to the login page', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

test('non-admin users are forbidden from the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin/dashboard')->assertForbidden();
});

test('super-admin users can visit the dashboard', function () {
    $this->seed(PermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('super-admin');

    $this->actingAs($user);

    $this->get('/admin/dashboard')->assertOk();
});

test('dashboard renders with only pending orders and zero aov', function () {
    $admin = createAdmin();
    Order::factory()->pending()->create();

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertOk();
});

test('dashboard top products match paid orders only', function () {
    $admin = createAdmin();
    $service = app(DashboardService::class);

    $paid = Order::factory()->confirmed()->create();
    $pending = Order::factory()->pending()->create();
    $product = createProduct();

    OrderItem::factory()->for($paid)->create(['product_id' => $product->id, 'quantity' => 2]);
    OrderItem::factory()->for($pending)->create(['product_id' => $product->id, 'quantity' => 9]);

    $top = $service->topProducts();

    expect($top)->toHaveCount(1);
    expect($top[0]['total_qty'])->toBe(2);
});
