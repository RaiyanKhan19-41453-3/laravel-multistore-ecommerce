<?php

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;

it('dashboard requires super-admin', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create())
        ->get('/admin/dashboard')->assertForbidden();
});

it('super-admin sees dashboard with stats', function () {
    $admin = createAdmin();
    $product = Product::factory()->create(['price' => 100]);
    Inventory::factory()->forProduct($product)->withQuantity(2)->create();
    Order::factory()->count(2)->confirmed()->create(['total' => 500, 'tax_amount' => 75]);
    Order::factory()->pending()->create();

    $response = $this->actingAs($admin)->get('/admin/dashboard');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('stats')
        ->has('chart')
        ->has('topProducts')
        ->has('lowStock')
        ->has('recentOrders')
    );
});

it('dashboard stats reflect revenue and low stock', function () {
    $admin = createAdmin();
    $product = Product::factory()->create(['price' => 50]);
    Inventory::factory()->forProduct($product)->withQuantity(1)->create();
    Order::factory()->confirmed()->create(['total' => 1000, 'tax_amount' => 150, 'created_at' => now()]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');
    $stats = $response->viewData('page')['props']['stats'];
    expect($stats['totalRevenue'])->toBeGreaterThanOrEqual(1000);
    expect($stats['lowStockCount'])->toBeGreaterThanOrEqual(1);
});

it('customers index requires super-admin', function () {
    $this->get('/admin/customers')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create())->get('/admin/customers')->assertForbidden();
});

it('super-admin can list and view customers', function () {
    $admin = createAdmin();
    $customer = User::factory()->create(['name' => 'Alice']);
    Order::factory()->for($customer)->confirmed()->create(['total' => 200]);

    $this->actingAs($admin)->get('/admin/customers')->assertOk()->assertInertia(fn ($p) => $p->component('admin/customers/index'));
    $this->actingAs($admin)->get("/admin/customers/{$customer->id}")->assertOk()->assertInertia(fn ($p) => $p->component('admin/customers/show'));
});

it('customers can be searched', function () {
    $admin = createAdmin();
    User::factory()->create(['name' => 'Bob Unique']);
    User::factory()->create(['name' => 'Charlie']);

    $response = $this->actingAs($admin)->get('/admin/customers?search=Bob+Unique');
    $response->assertOk();
});

it('reports index shows totals', function () {
    $admin = createAdmin();
    Order::factory()->confirmed()->create(['total' => 300, 'tax_amount' => 45, 'created_at' => now()]);

    $response = $this->actingAs($admin)->get('/admin/reports');
    $response->assertOk()->assertInertia(fn ($p) => $p->component('admin/reports/index')->has('totals')->has('salesDaily'));
});

it('reports export sales csv', function () {
    $admin = createAdmin();
    Order::factory()->confirmed()->create(['total' => 100, 'tax_amount' => 15]);

    $response = $this->actingAs($admin)->get('/admin/reports/export?type=sales&from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString());
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Date');
});

it('reports export vat csv filters tax orders', function () {
    $admin = createAdmin();
    Order::factory()->confirmed()->create(['total' => 115, 'tax_amount' => 15]);
    Order::factory()->confirmed()->create(['total' => 100, 'tax_amount' => 0]);

    $response = $this->actingAs($admin)->get('/admin/reports/export?type=vat');
    $response->assertOk();
    $content = $response->streamedContent();
    expect($content)->toContain('VAT');
});

it('invoice view renders with qr when vat present', function () {
    $admin = createAdmin();
    $order = Order::factory()->confirmed()->create(['total' => 115, 'tax_amount' => 15, 'subtotal' => 100]);
    OrderItem::factory()->for($order)->create(['quantity' => 1, 'unit_price' => 100, 'total' => 100]);

    config(['zatca.seller.name_ar' => 'متجر', 'zatca.seller.vat_number' => '300000000000003', 'store.name' => 'Test Store']);

    $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/invoice");
    $response->assertOk()->assertInertia(fn ($p) => $p->component('admin/orders/invoice')->has('qrSvg'));
});

it('invoice printable html contains order number', function () {
    $admin = createAdmin();
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->for($order)->create();

    $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/invoice/print");
    $response->assertOk();
    expect($response->getContent())->toContain($order->order_number);
});

it('invoice pdf returns html fallback when dompdf missing', function () {
    $admin = createAdmin();
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->for($order)->create();

    $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/invoice/pdf");
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toMatch('/html|pdf/');
});

it('packing slip renders', function () {
    $admin = createAdmin();
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->for($order)->create();

    $response = $this->actingAs($admin)->get("/admin/orders/{$order->id}/packing-slip");
    $response->assertOk();
    expect($response->getContent())->toContain('Packing Slip');
});

it('non-admin cannot access reports or invoices', function () {
    $user = User::factory()->create();
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->for($order)->create();

    $this->actingAs($user)->get('/admin/reports')->assertForbidden();
    $this->actingAs($user)->get("/admin/orders/{$order->id}/invoice")->assertForbidden();
    $this->actingAs($user)->get('/admin/customers')->assertForbidden();
});

it('falls back to defaults on garbage report dates', function () {
    $admin = createAdmin();

    $this->actingAs($admin)->get('/admin/reports?from=garbage&to=%%zz')->assertOk();
    $this->actingAs($admin)->get('/admin/reports/export?type=sales&from=garbage')->assertOk();
});
