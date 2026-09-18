<?php

use App\Models\AuditLog;
use App\Models\Courier;
use App\Models\Order;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;

it('seeds staff roles with their permissions', function () {
    (new PermissionSeeder)->run();

    expect(Role::findByName('order-manager', 'web')->hasPermissionTo('orders.manage'))->toBeTrue();
    expect(Role::findByName('order-manager', 'web')->hasPermissionTo('settings'))->toBeFalse();
    expect(Role::findByName('catalog-manager', 'web')->hasPermissionTo('catalog.manage'))->toBeTrue();
    expect(Role::findByName('support', 'web')->hasPermissionTo('orders.manage'))->toBeFalse();
    expect(Role::findByName('accountant', 'web')->hasPermissionTo('reports'))->toBeTrue();
    expect(Role::findByName('super-admin', 'web')->hasPermissionTo('audit'))->toBeTrue();
});

it('lets order managers view orders but not manage the catalog or settings', function () {
    $manager = createStaffUser('order-manager');

    $this->actingAs($manager)->get('/admin/orders')->assertOk();
    $this->actingAs($manager)->post('/admin/products', [])->assertForbidden();
    $this->actingAs($manager)->get('/admin/settings')->assertForbidden();
});

it('forbids support staff from cancelling orders and viewing reports', function () {
    $support = createStaffUser('support');
    $order = Order::factory()->pending()->create();

    $this->actingAs($support)->get('/admin/orders')->assertOk();
    $this->actingAs($support)->post("/admin/orders/{$order->id}/cancel")->assertForbidden();
    $this->actingAs($support)->get('/admin/reports')->assertForbidden();
});

it('lets accountants view reports but not the catalog', function () {
    $accountant = createStaffUser('accountant');

    $this->actingAs($accountant)->get('/admin/reports')->assertOk();
    $this->actingAs($accountant)->get('/admin/products')->assertForbidden();
    $this->actingAs($accountant)->get('/admin/dashboard')->assertOk();
});

it('lets catalog managers write the catalog but not touch settings', function () {
    $manager = createStaffUser('catalog-manager');

    $this->actingAs($manager)->post('/admin/categories', ['name' => 'Staff Category'])->assertRedirect();
    $this->assertDatabaseHas('categories', ['name' => 'Staff Category']);

    $this->actingAs($manager)->get('/admin/settings')->assertForbidden();
    $this->actingAs($manager)->get('/admin/orders')->assertForbidden();
});

it('shares the staff permissions with admin pages', function () {
    $manager = createStaffUser('order-manager');

    $this->actingAs($manager)->get('/admin/orders')->assertOk()->assertInertia(
        fn ($p) => $p->where('auth.permissions', fn ($permissions) => $permissions->contains('orders.manage')
            && ! $permissions->contains('settings'))
    );
});

it('records admin writes in the audit log', function () {
    $admin = createAdmin();

    $before = AuditLog::count();

    $this->actingAs($admin)->post('/admin/categories', ['name' => 'Audited Category'])->assertRedirect();

    expect(AuditLog::count())->toBe($before + 1);
    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $admin->id,
        'action' => 'admin.categories.store',
        'method' => 'POST',
        'path' => 'admin/categories',
    ]);
});

it('does not record admin reads in the audit log', function () {
    $admin = createAdmin();

    $before = AuditLog::count();

    $this->actingAs($admin)->get('/admin/categories')->assertOk();

    expect(AuditLog::count())->toBe($before);
});

it('redacts nested secrets from the audit log', function () {
    $admin = createAdmin();
    $courier = Courier::factory()->create(['code' => 'redx', 'settings' => []]);

    $this->actingAs($admin)->put("/admin/couriers/{$courier->id}/settings", [
        'settings' => [
            'client_id' => 'public-id',
            'client_secret' => 'super-secret-value',
            'password' => 'hunter2',
        ],
    ])->assertRedirect();

    $log = AuditLog::where('action', 'admin.couriers.settings.update')->latest()->first();

    expect($log)->not->toBeNull();

    $dump = (string) json_encode($log->changes);
    expect($dump)->toContain('public-id')
        ->not->toContain('super-secret-value')
        ->not->toContain('hunter2');
});

it('restricts the audit log viewer to super admins', function () {
    $manager = createStaffUser('order-manager');

    $this->actingAs($manager)->get('/admin/audit-logs')->assertForbidden();
    $this->actingAs(createAdmin())->get('/admin/audit-logs')->assertOk()->assertInertia(
        fn ($p) => $p->component('admin/audit-logs/index')
    );
});

it('prunes audit logs older than the retention window', function () {
    createAdmin();

    AuditLog::factory()->create(['created_at' => now()->subDays(400)]);
    AuditLog::factory()->create(['created_at' => now()->subDays(10)]);

    $this->artisan('audit:prune')->assertSuccessful();

    expect(AuditLog::count())->toBe(1);
});
