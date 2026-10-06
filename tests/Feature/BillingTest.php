<?php

use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\Subscription;

it('trials new stores from creation and expires them after the window', function () {
    $fresh = Store::factory()->create();
    $old = Store::factory()->create(['created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30)]);

    expect($fresh->billingStatus())->toBe('trialing')
        ->and($fresh->isBillingEntitled())->toBeTrue()
        ->and($old->billingStatus())->toBe('expired')
        ->and($old->isBillingEntitled())->toBeFalse();
});

it('resolves subscription rows with period checks', function () {
    $store = Store::factory()->create();
    $plan = Plan::factory()->create();

    Subscription::create([
        'store_id' => $store->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_started_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    expect($store->fresh()->billingStatus())->toBe('active');

    $store->subscription->update(['status' => 'past_due']);
    expect($store->fresh()->billingStatus())->toBe('past_due');

    $store->subscription->update(['status' => 'active', 'current_period_ends_at' => now()->subDay()]);
    expect($store->fresh()->billingStatus())->toBe('expired');
});

it('manages plans as super-admin only', function () {
    $admin = createAdmin();
    $staff = createStaffUser('catalog-manager');

    $this->actingAs($staff)->getJson('/admin/plans')->assertForbidden();

    $response = $this->actingAs($admin)->postJson('/admin/plans', [
        'name' => 'Growth',
        'price' => 29,
        'currency' => 'usd',
        'interval' => 'monthly',
        'trial_days' => 14,
    ])->assertCreated();

    $planId = $response->json('data.id');
    expect(Plan::find($planId)->slug)->toBe('growth');

    // Plan with subscriptions cannot be deleted.
    $store = Store::factory()->create();
    Subscription::create(['store_id' => $store->id, 'plan_id' => $planId, 'status' => 'active']);

    $this->actingAs($admin)->deleteJson("/admin/plans/{$planId}")->assertStatus(422);
});

it('assigns subscriptions and suspends stores as platform', function () {
    $admin = createAdmin();
    $store = Store::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($admin)->postJson("/admin/stores/{$store->id}/subscription", [
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_started_at' => now()->toDateTimeString(),
        'current_period_ends_at' => now()->addMonth()->toDateTimeString(),
    ])->assertOk()->assertJsonPath('data.status', 'active');

    expect($store->fresh()->billingStatus())->toBe('active');

    $this->actingAs($admin)->getJson("/admin/stores/{$store->id}")
        ->assertOk()
        ->assertJsonPath('data.billing_status', 'active');

    $this->actingAs($admin)->postJson("/admin/stores/{$store->id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('lets merchants view billing and request a plan', function () {
    $owner = createUser();
    $store = Store::factory()->create(['slug' => 'bill-a']);
    $store->users()->attach($owner->id, ['role' => 'owner']);
    $plan = Plan::factory()->create(['is_active' => true]);

    $this->actingAs($owner)->getJson('/admin/billing', ['X-Store-Slug' => 'bill-a'])
        ->assertOk()
        ->assertJsonPath('data.store.billing_status', 'trialing');

    $this->actingAs($owner)->postJson('/admin/billing/subscribe', [
        'plan_id' => $plan->id,
    ], ['X-Store-Slug' => 'bill-a'])->assertCreated()->assertJsonPath('data.status', 'pending');

    // Entitled stores cannot double-subscribe.
    $store->subscription->update(['status' => 'active', 'current_period_ends_at' => now()->addMonth()]);
    $this->actingAs($owner)->postJson('/admin/billing/subscribe', [
        'plan_id' => $plan->id,
    ], ['X-Store-Slug' => 'bill-a'])->assertStatus(422);
});

it('blocks suspended stores when enforcement is on, and nobody when off', function () {
    $store = Store::factory()->create([
        'slug' => 'enf-a',
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);
    $product = Product::factory()->create(['slug' => 'enf-prod', 'is_active' => true, 'store_id' => $store->id]);

    // Off by default: expired trial still serves.
    $this->getJson('/api/products/enf-prod', ['X-Store-Slug' => 'enf-a'])->assertOk();

    config(['platform.billing.enforced' => true]);

    // Storefront blocked.
    $this->getJson('/api/products/enf-prod', ['X-Store-Slug' => 'enf-a'])->assertStatus(402);

    // Signup and auth stay open.
    $this->getJson('/api/stores/current', ['X-Store-Slug' => 'enf-a'])->assertOk();

    // Super-admin bypasses everywhere.
    $admin = createAdmin();
    $this->actingAs($admin)->get('/admin/products', ['X-Store-Slug' => 'enf-a'])->assertOk();

    // Merchant admin blocked, except billing itself.
    $owner = createUser();
    $store->users()->attach($owner->id, ['role' => 'owner']);
    $this->actingAs($owner)->get('/admin/products', ['X-Store-Slug' => 'enf-a'])->assertStatus(402);
    $this->actingAs($owner)->getJson('/admin/billing', ['X-Store-Slug' => 'enf-a'])->assertOk();
});

it('lets courier webhooks through when enforcement is on, whatever the default store owes', function () {
    $storeA = Store::factory()->create([
        'slug' => 'wh-a',
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);
    $storeB = Store::factory()->create(['slug' => 'wh-b']);

    // Expire the migration-seeded default store too: with enforcement on,
    // resolution falls back to it, and webhooks must still get through.
    Store::default()?->update(['created_at' => now()->subDays(60), 'updated_at' => now()->subDays(60)]);

    $order = Order::factory()->create(['status' => 'shipped', 'store_id' => $storeB->id]);
    $shipment = Shipment::factory()->create([
        'order_id' => $order->id,
        'courier_code' => 'pathao',
        'courier_order_id' => 'PATHAO-WH',
        'status' => 'in_transit',
        'store_id' => $storeB->id,
    ]);

    config(['platform.billing.enforced' => true]);

    // No store header: resolution falls back to the expired default store.
    // Webhooks carry their own identity and must never be gated on it, and
    // their lookups must see rows from every store.
    $this->postJson('/api/webhooks/pathao', [
        'event' => 'order.delivered',
        'data' => ['consignment_id' => 'PATHAO-WH', 'store_id' => 12345],
    ])->assertOk()->assertJson(['status' => 'processed']);

    expect($order->fresh()->status)->toBe('delivered');
    expect($storeA->billingStatus())->toBe('expired');
});
