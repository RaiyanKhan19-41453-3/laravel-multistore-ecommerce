<?php

use App\Models\Setting;
use App\Models\Store;
use App\Services\SettingsService;
use App\Support\CurrentStore;

it('keeps settings overrides isolated per store with global fallback', function () {
    $settings = app(SettingsService::class);

    $storeA = Store::factory()->create(['slug' => 'set-a']);
    $storeB = Store::factory()->create(['slug' => 'set-b']);

    // Global default (seeded with NULL store_id, like SettingsSeeder)
    // is visible to every store.
    Setting::create(['store_id' => null, 'key' => 'store.currency', 'value' => 'USD', 'group' => 'store']);

    expect($settings->get('store.currency', null, $storeA->id))->toBe('USD')
        ->and($settings->get('store.currency', null, $storeB->id))->toBe('USD');

    // Store A overrides; B still sees the global.
    $settings->set('store.currency', 'SAR', 'store', $storeA->id);

    expect($settings->get('store.currency', null, $storeA->id))->toBe('SAR')
        ->and($settings->get('store.currency', null, $storeB->id))->toBe('USD');

    // Same key coexists across scopes at the database level.
    expect(Setting::where('key', 'store.currency')->count())->toBe(2);
});

it('resolves settings from the current store context', function () {
    $settings = app(SettingsService::class);

    $storeA = Store::factory()->create(['slug' => 'ctx-a']);
    $storeB = Store::factory()->create(['slug' => 'ctx-b']);

    $settings->set('tax.mode', 'vat', 'tax', $storeA->id);

    app(CurrentStore::class)->set($storeA);
    expect($settings->get('tax.mode'))->toBe('vat');

    app(CurrentStore::class)->set($storeB);
    expect($settings->get('tax.mode'))->toBeNull();

    // Writes follow the current store too.
    $settings->set('tax.mode', 'gst', 'tax');
    expect(Setting::where('key', 'tax.mode')->where('store_id', $storeB->id)->exists())->toBeTrue();

    app(CurrentStore::class)->forget();
});

it('applies presets per store without touching other stores', function () {
    $settings = app(SettingsService::class);

    $storeA = Store::factory()->create(['slug' => 'pre-a']);
    $storeB = Store::factory()->create(['slug' => 'pre-b']);

    $settings->applyPreset('SA', $storeA->id);

    expect($settings->get('store.currency', null, $storeA->id))->toBe('SAR')
        ->and($settings->get('tax.mode', null, $storeA->id))->toBe('vat')
        ->and($settings->get('store.currency', null, $storeB->id))->toBeNull();

    expect(fn () => $settings->applyPreset('XX', $storeA->id))
        ->toThrow(InvalidArgumentException::class);
});

it('writes admin settings to the resolved store only', function () {
    $admin = createAdmin();
    $storeB = Store::factory()->create(['slug' => 'adm-set-b']);

    $this->actingAs($admin)->putJson('/admin/settings', [
        'store' => ['currency' => 'SAR'],
    ], ['X-Store-Slug' => 'adm-set-b'])->assertRedirect('/admin/settings');

    expect(Setting::where('key', 'store.currency')->where('store_id', $storeB->id)->exists())->toBeTrue();

    // The default store context does not see the other store's value.
    // (forget() clears the request-resolved store; each real HTTP
    // request boots with a fresh singleton.)
    app(CurrentStore::class)->forget();
    expect(app(SettingsService::class)->get('store.currency'))->toBeNull();
});
