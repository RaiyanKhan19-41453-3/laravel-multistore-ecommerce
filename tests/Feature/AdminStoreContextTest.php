<?php

use App\Models\Category;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Support\AdminStoreContext;

function attachStoreMember($user, Store $store, string $role = 'owner'): void
{
    if (! $store->users()->whereKey($user->id)->exists()) {
        $store->users()->attach($user->id, ['role' => $role]);
    }
}

it('lets a super-admin select, view, and clear the store context', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'sctx-a']);
    $storeB = Store::factory()->create(['slug' => 'sctx-b']);

    $this->actingAs($admin)->getJson('/admin/store-context')
        ->assertOk()
        ->assertJsonPath('data.selected_id', null);

    $this->actingAs($admin)->postJson('/admin/store-context', ['store_id' => $storeB->id])
        ->assertOk()
        ->assertJsonPath('data.selected_id', $storeB->id);

    $this->actingAs($admin)->deleteJson('/admin/store-context')
        ->assertOk()
        ->assertJsonPath('data.selected_id', null);

    expect($storeA->id)->not->toBe($storeB->id);
});

it('restricts store selection to member stores for staff', function () {
    $staff = createStaffUser('catalog-manager');
    $storeA = Store::factory()->create(['slug' => 'mctx-a']);
    $storeB = Store::factory()->create(['slug' => 'mctx-b']);
    attachStoreMember($staff, $storeA);

    // Own store: allowed.
    $this->actingAs($staff)->postJson('/admin/store-context', ['store_id' => $storeA->id])
        ->assertOk()
        ->assertJsonPath('data.selected_id', $storeA->id);

    // Other store: forbidden.
    $this->actingAs($staff)->postJson('/admin/store-context', ['store_id' => $storeB->id])
        ->assertForbidden();

    // Clearing to the platform view: forbidden for non-super-admins.
    $this->actingAs($staff)->deleteJson('/admin/store-context')->assertForbidden();

    // Unknown store: validation error.
    $this->actingAs($staff)->postJson('/admin/store-context', ['store_id' => 999999])
        ->assertUnprocessable();
});

it('scopes admin product and order listings to the selected store', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'lctx-a']);
    $storeB = Store::factory()->create(['slug' => 'lctx-b']);

    $productA = Product::factory()->create(['name' => 'Alpha Widget', 'is_active' => true, 'store_id' => $storeA->id]);
    Product::factory()->create(['name' => 'Beta Widget', 'is_active' => true, 'store_id' => $storeB->id]);

    // No selection: platform view shows everything (legacy behavior).
    $this->actingAs($admin)->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/products/index')->has('products.data', 2));

    // Header selection: only the selected store's rows.
    $this->actingAs($admin)->getJson('/admin/store-context');
    $this->actingAs($admin)->get('/admin/products', ['X-Store-Slug' => 'lctx-a'])
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $productA->id));

    // Session selection persists across requests.
    $this->actingAs($admin)->postJson('/admin/store-context', ['store_id' => $storeB->id])->assertOk();
    $this->actingAs($admin)->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/products/index')->has('products.data', 1));
});

it('creates admin products in the selected store', function () {
    $admin = createAdmin();
    $storeB = Store::factory()->create(['slug' => 'cctx-b']);

    $this->actingAs($admin)->postJson('/admin/store-context', ['store_id' => $storeB->id])->assertOk();

    $this->actingAs($admin)->post('/admin/products', [
        'name' => 'Scoped Product',
        'slug' => 'scoped-product',
        'type' => 'simple',
        'sku' => 'SCOPED-1',
        'price' => 100,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', ['slug' => 'scoped-product', 'store_id' => $storeB->id]);
});

it('scopes admin customer listings to customers of the selected store', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'uctx-a']);
    $storeB = Store::factory()->create(['slug' => 'uctx-b']);

    $customerA = createUser();
    Order::factory()->create(['user_id' => $customerA->id, 'store_id' => $storeA->id]);
    $customerB = createUser();
    Order::factory()->create(['user_id' => $customerB->id, 'store_id' => $storeB->id]);

    // Platform view: both customers (among other order-less users).
    $this->actingAs($admin)->get('/admin/customers')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/customers/index')
            ->where('customers.total', fn ($total) => $total >= 2));

    // Store A view: only customer A, and B's page 404s.
    $this->actingAs($admin)->get('/admin/customers', ['X-Store-Slug' => 'uctx-a'])
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/customers/index')
            ->has('customers.data', 1)
            ->where('customers.data.0.email', $customerA->email));

    $this->actingAs($admin)->get("/admin/customers/{$customerB->id}", ['X-Store-Slug' => 'uctx-a'])->assertNotFound();
    $this->actingAs($admin)->get("/admin/customers/{$customerA->id}", ['X-Store-Slug' => 'uctx-a'])->assertOk();
});

it('shares the admin store context on admin pages only', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'shctx-a']);

    $this->actingAs($admin)->postJson('/admin/store-context', ['store_id' => $storeA->id])->assertOk();

    $this->actingAs($admin)->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->has('adminStore'));

    expect(app(AdminStoreContext::class)->selectedId($admin))->toBe($storeA->id);
});

it('grants the store-owner role on merchant signup', function () {
    ensureStaffPermissions();

    $user = createUser();

    $this->postJson('/api/stores', ['name' => 'Role Shop'], authHeaders($user))->assertCreated();

    expect($user->fresh()->hasRole('store-owner'))->toBeTrue();
});

it('scopes store-owner permissions to store domains only', function () {
    $owner = createStaffUser('store-owner');

    $this->actingAs($owner)->get('/admin/products')->assertOk();
    $this->actingAs($owner)->get('/admin/audit-logs')->assertForbidden();
});

it('locks member staff to their stores without any selection', function () {
    $staff = createStaffUser('catalog-manager');
    $storeA = Store::factory()->create(['slug' => 'lock-a']);
    $storeB = Store::factory()->create(['slug' => 'lock-b']);
    attachStoreMember($staff, $storeA);

    Product::factory()->create(['name' => 'Lock A', 'is_active' => true, 'store_id' => $storeA->id]);
    Product::factory()->create(['name' => 'Lock B', 'is_active' => true, 'store_id' => $storeB->id]);

    // No selection made, yet only their store shows.
    $this->actingAs($staff)->get('/admin/products')
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('admin/products/index')->has('products.data', 1));

    // Cannot escape to the platform view.
    $this->actingAs($staff)->deleteJson('/admin/store-context')->assertForbidden();

    // Context reports the locked store.
    $this->actingAs($staff)->getJson('/admin/store-context')
        ->assertOk()
        ->assertJsonPath('data.selected_id', $storeA->id);
});

it('manages store members as owner', function () {
    $admin = createAdmin();
    $owner = createUser();
    $staff = createUser();
    $storeA = Store::factory()->create(['slug' => 'mem-a']);
    attachStoreMember($owner, $storeA, 'owner');

    // Owner lists and adds members in their store.
    $this->actingAs($owner)->getJson('/admin/store-members', ['X-Store-Slug' => 'mem-a'])
        ->assertOk()
        ->assertJsonFragment(['email' => $owner->email]);

    $this->actingAs($owner)->postJson('/admin/store-members', [
        'email' => $staff->email,
        'role' => 'staff',
    ], ['X-Store-Slug' => 'mem-a'])->assertOk();

    expect($storeA->users()->whereKey($staff->id)->first()->pivot->role)->toBe('staff');

    // Non-owner member cannot manage members.
    $this->actingAs($staff)->postJson('/admin/store-members', [
        'email' => $admin->email,
        'role' => 'staff',
    ], ['X-Store-Slug' => 'mem-a'])->assertForbidden();

    // The last owner cannot be removed.
    $this->actingAs($owner)->deleteJson("/admin/store-members/{$owner->id}", ['X-Store-Slug' => 'mem-a'])
        ->assertStatus(422);

    // Removing a staff member works.
    $this->actingAs($owner)->deleteJson("/admin/store-members/{$staff->id}", ['X-Store-Slug' => 'mem-a'])
        ->assertOk();

    expect($storeA->users()->whereKey($staff->id)->exists())->toBeFalse();

    // Super-admin manages any store's members.
    $this->actingAs($admin)->getJson('/admin/store-members', ['X-Store-Slug' => 'mem-a'])->assertOk();
});

it('blocks records from other stores once a store is selected', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'rec-a']);
    $storeB = Store::factory()->create(['slug' => 'rec-b']);

    $productB = Product::factory()->create(['name' => 'B Product', 'store_id' => $storeB->id]);
    $orderB = Order::factory()->create(['store_id' => $storeB->id]);

    // Platform view: everything reachable (legacy behavior).
    $this->actingAs($admin)->get("/admin/products/{$productB->id}")->assertOk();
    $this->actingAs($admin)->get("/admin/orders/{$orderB->id}")->assertOk();

    $headers = ['X-Store-Slug' => 'rec-a'];

    // Selected store: foreign records 404.
    $this->actingAs($admin)->get("/admin/products/{$productB->id}", $headers)->assertNotFound();
    $this->actingAs($admin)->get("/admin/products/{$productB->id}/edit", $headers)->assertNotFound();
    $this->actingAs($admin)->get("/admin/orders/{$orderB->id}", $headers)->assertNotFound();
    $this->actingAs($admin)->put("/admin/products/{$productB->id}", [
        'name' => 'Hijacked',
        'type' => 'simple',
        'sku' => $productB->sku,
        'price' => 10,
    ], $headers)->assertNotFound();

    expect(Product::find($productB->id)->name)->toBe('B Product');
});

it('rejects cross-store links in admin forms', function () {
    $admin = createAdmin();
    $storeA = Store::factory()->create(['slug' => 'lnk-a']);
    $storeB = Store::factory()->create(['slug' => 'lnk-b']);

    $productA = Product::factory()->create(['type' => 'simple', 'store_id' => $storeA->id]);
    $categoryB = Category::create(['name' => 'Foreign Cat', 'slug' => 'foreign-cat', 'is_active' => true]);
    $categoryB->store_id = $storeB->id;
    $categoryB->save();
    $categoryA = Category::create(['name' => 'Own Cat', 'slug' => 'own-cat', 'is_active' => true]);
    $categoryA->store_id = $storeA->id;
    $categoryA->save();

    $headers = ['X-Store-Slug' => 'lnk-a'];

    // Foreign category: rejected.
    $this->actingAs($admin)->put("/admin/products/{$productA->id}", [
        'name' => $productA->name,
        'type' => 'simple',
        'sku' => $productA->sku,
        'price' => 10,
        'category_ids' => [$categoryB->id],
    ], $headers)->assertSessionHasErrors('category_ids.0');

    // Own category: accepted.
    $this->actingAs($admin)->put("/admin/products/{$productA->id}", [
        'name' => $productA->name,
        'type' => 'simple',
        'sku' => $productA->sku,
        'price' => 10,
        'category_ids' => [$categoryA->id],
    ], $headers)->assertSessionHasNoErrors();

    expect($productA->fresh()->categories->pluck('id'))->toContain($categoryA->id);
});

it('keeps coupons on their discount store', function () {
    $admin = createAdmin();
    $storeB = Store::factory()->create(['slug' => 'cpn-b']);

    $discountB = Discount::factory()->create(['store_id' => $storeB->id]);

    // Platform view (resolved store is the default, not B).
    $this->actingAs($admin)->post("/admin/discounts/{$discountB->id}/coupons", [
        'code' => 'BONLY1',
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('coupons', ['code' => 'BONLY1', 'store_id' => $storeB->id]);
});

it('gives added members a baseline role so they can open the admin', function () {
    ensureStaffPermissions();

    $owner = createUser();
    $newcomer = createUser();
    $storeA = Store::factory()->create(['slug' => 'base-a']);
    attachStoreMember($owner, $storeA, 'owner');

    expect($newcomer->roles()->count())->toBe(0);

    $this->actingAs($owner)->postJson('/admin/store-members', [
        'email' => $newcomer->email,
        'role' => 'staff',
    ], ['X-Store-Slug' => 'base-a'])->assertOk();

    // Baseline support role: orders viewable, platform admin not.
    expect($newcomer->fresh()->hasRole('support'))->toBeTrue();
    $this->actingAs($newcomer)->get('/admin/orders')->assertOk();
    $this->actingAs($newcomer)->get('/admin/audit-logs')->assertForbidden();
});

it('strips the merchant role when the last membership is removed', function () {
    ensureStaffPermissions();

    $owner = createUser();
    $storeA = Store::factory()->create(['slug' => 'strip-a']);
    attachStoreMember($owner, $storeA, 'owner');
    $owner->assignRole('store-owner');

    // Second owner so removal is allowed.
    $owner2 = createUser();
    attachStoreMember($owner2, $storeA, 'owner');

    $this->actingAs($owner2)->deleteJson("/admin/store-members/{$owner->id}", ['X-Store-Slug' => 'strip-a'])
        ->assertOk();

    $owner = $owner->fresh();

    expect($owner->hasRole('store-owner'))->toBeFalse();

    // No roles, no memberships: admin stays closed (no platform fallback).
    $this->actingAs($owner)->get('/admin/products')->assertForbidden();
});

it('strips an auto-granted baseline role but keeps real staff roles', function () {
    ensureStaffPermissions();

    $owner = createUser();
    $storeA = Store::factory()->create(['slug' => 'strip-b']);
    attachStoreMember($owner, $storeA, 'owner');

    // Pure baseline user: support goes with the membership.
    $newcomer = createUser();
    $this->actingAs($owner)->postJson('/admin/store-members', [
        'email' => $newcomer->email,
        'role' => 'staff',
    ], ['X-Store-Slug' => 'strip-b'])->assertOk();
    expect($newcomer->fresh()->hasRole('support'))->toBeTrue();

    $this->actingAs($owner)->deleteJson("/admin/store-members/{$newcomer->id}", ['X-Store-Slug' => 'strip-b'])
        ->assertOk();
    expect($newcomer->fresh()->roles()->count())->toBe(0);

    // Legacy staff keeps their own role.
    $veteran = createStaffUser('catalog-manager');
    $this->actingAs($owner)->postJson('/admin/store-members', [
        'email' => $veteran->email,
        'role' => 'staff',
    ], ['X-Store-Slug' => 'strip-b'])->assertOk();

    $this->actingAs($owner)->deleteJson("/admin/store-members/{$veteran->id}", ['X-Store-Slug' => 'strip-b'])
        ->assertOk();
    expect($veteran->fresh()->hasRole('catalog-manager'))->toBeTrue();
});
