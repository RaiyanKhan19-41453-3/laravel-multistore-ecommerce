<?php

use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogCache;
use App\Services\ImageService;
use App\Support\CurrentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('lets an authenticated merchant open a store and become its owner', function () {
    $user = createUser();

    $response = $this->postJson('/api/stores', [
        'name' => 'Nike Dhaka',
        'country' => 'BD',
    ], authHeaders($user));

    $response->assertCreated()
        ->assertJsonFragment(['name' => 'Nike Dhaka']);

    $store = Store::where('slug', 'nike-dhaka')->first();
    expect($store)->not->toBeNull()
        ->and($store->owner_user_id)->toBe($user->id)
        ->and($store->currency)->toBe('BDT');
    expect($store->users()->whereKey($user->id)->exists())->toBeTrue();
    expect($store->users()->whereKey($user->id)->first()->pivot->role)->toBe('owner');
});

it('applies the SA preset for new stores', function () {
    $user = createUser();

    $response = $this->postJson('/api/stores', [
        'name' => 'Abaya House',
        'country' => 'SA',
    ], authHeaders($user));

    $response->assertCreated();

    $store = Store::where('slug', 'abaya-house')->first();
    expect($store->currency)->toBe('SAR')
        ->and($store->locale)->toBe('ar')
        ->and($store->timezone)->toBe('Asia/Riyadh');
});

it('rejects duplicate store slugs', function () {
    $user = createUser();
    Store::factory()->create(['slug' => 'taken']);

    $this->postJson('/api/stores', [
        'name' => 'Anything',
        'slug' => 'taken',
    ], authHeaders($user))->assertUnprocessable();
});

it('requires authentication to create a store', function () {
    $this->postJson('/api/stores', ['name' => 'Nope'])->assertUnauthorized();
});

it('exposes a public store directory and resolves the current store', function () {
    $store = Store::factory()->create(['name' => 'Public Store', 'slug' => 'public-store', 'is_active' => true]);

    $this->getJson('/api/stores')->assertOk()
        ->assertJsonFragment(['slug' => 'public-store']);

    $this->getJson('/api/stores/public-store')->assertOk()
        ->assertJsonFragment(['name' => 'Public Store']);

    // No header: falls back to the default store, never 404s.
    $this->getJson('/api/stores/current')->assertOk();

    // Explicit header resolves the right store.
    $this->getJson('/api/stores/current', ['X-Store-Slug' => 'public-store'])
        ->assertOk()
        ->assertJsonFragment(['slug' => 'public-store']);

    // Unknown slug falls back to default instead of breaking.
    $this->getJson('/api/stores/current', ['X-Store-Slug' => 'nope'])
        ->assertOk();
});

it('auto-fills store_id on new tenant rows from the current store', function () {
    $storeA = Store::factory()->create(['slug' => 'store-a']);
    $storeB = Store::factory()->create(['slug' => 'store-b']);

    app(CurrentStore::class)->set($storeA);
    $productA = Product::factory()->create();

    app(CurrentStore::class)->set($storeB);
    $productB = Product::factory()->create();

    expect($productA->store_id)->toBe($storeA->id)
        ->and($productB->store_id)->toBe($storeB->id)
        ->and($productA->store_id)->not->toBe($productB->store_id);

    app(CurrentStore::class)->forget();
});

it('keeps legacy rows working and scopes cache keys per store', function () {
    $storeA = Store::factory()->create(['slug' => 'cache-a']);
    $storeB = Store::factory()->create(['slug' => 'cache-b']);

    app(CurrentStore::class)->set($storeA);
    $keyA = CatalogCache::featuredKey('en');

    app(CurrentStore::class)->set($storeB);
    $keyB = CatalogCache::featuredKey('en');

    expect($keyA)->toContain("store:{$storeA->id}:")
        ->and($keyB)->toContain("store:{$storeB->id}:")
        ->and($keyA)->not->toBe($keyB);

    app(CurrentStore::class)->forget();
});

it('prefers an active store as the default', function () {
    $inactive = Store::factory()->create(['slug' => 'inactive-first', 'is_active' => false]);
    $active = Store::factory()->create(['slug' => 'active-second', 'is_active' => true]);

    // Suspend everything older so the oldest row overall is inactive.
    // (Fresh test DBs already contain the migration-seeded default store.)
    DB::table('stores')->where('id', '!=', $active->id)->update(['is_active' => false]);
    DB::table('stores')->where('id', $inactive->id)->update(['created_at' => now()->subDay()]);

    expect(Store::default()?->id)->toBe($active->id);
});

it('never reuses the slug of a deleted store', function () {
    $user = createUser();

    $first = $this->postJson('/api/stores', ['name' => 'Ghost Shop'], authHeaders($user));
    $first->assertCreated()->assertJsonFragment(['slug' => 'ghost-shop']);

    Store::where('slug', 'ghost-shop')->firstOrFail()->delete();

    // Must get a suffixed slug with 201, not a 500 unique violation.
    $second = $this->postJson('/api/stores', ['name' => 'Ghost Shop'], authHeaders($user));
    $second->assertCreated()->assertJsonFragment(['slug' => 'ghost-shop-2']);
});

it('does not link unrelated users to stores on registration', function () {
    $storeA = Store::factory()->create(['slug' => 'link-a']);
    app(CurrentStore::class)->set($storeA);

    $customer = createUser();

    expect(DB::table('store_user')->where('user_id', $customer->id)->count())->toBe(0);

    $storeB = Store::factory()->create(['slug' => 'link-b']);

    // The factory owner of B must not leak into A as a customer.
    expect(DB::table('store_user')->where('store_id', $storeA->id)->where('role', 'customer')->count())->toBe(0);
    expect($storeB->users()->whereKey($storeB->owner_user_id)->exists())->toBeFalse();

    app(CurrentStore::class)->forget();
});

it('respects an explicit store_id instead of overwriting it', function () {
    $storeA = Store::factory()->create(['slug' => 'explicit-a']);
    $storeB = Store::factory()->create(['slug' => 'explicit-b']);

    app(CurrentStore::class)->set($storeA);

    $product = Product::factory()->create(['store_id' => $storeB->id]);

    expect($product->store_id)->toBe($storeB->id);

    app(CurrentStore::class)->forget();
});

it('stores uploaded images on the product store even when resolved elsewhere', function () {
    Storage::fake('public');

    $storeA = Store::factory()->create(['slug' => 'img-a']);
    $storeB = Store::factory()->create(['slug' => 'img-b']);

    app(CurrentStore::class)->set($storeA);
    $product = Product::factory()->create(['store_id' => $storeB->id]);

    // Still resolved to A while the product belongs to B.
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);
    $image = app(ImageService::class)->upload($file, $product->id);

    expect($image->store_id)->toBe($storeB->id)
        ->and($image->path)->toStartWith("products/{$storeB->id}/{$product->id}/");

    app(CurrentStore::class)->forget();
});
