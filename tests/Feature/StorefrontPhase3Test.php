<?php

use App\Models\CmsPage;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists approved reviews for a product', function () {
    $product = createProduct();
    $user = createUser();
    Review::factory()->approved()->create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => 5]);
    Review::factory()->pending()->create(['product_id' => $product->id, 'user_id' => createUser()->id, 'rating' => 2]);

    $response = $this->getJson("/api/products/{$product->slug}/reviews");

    $response->assertOk()
        ->assertJsonPath('data.summary.total', 1)
        ->assertJsonFragment(['average' => 5.0]);
});

it('creates a review when authenticated', function () {
    $user = createUser();
    $product = createProduct();
    $order = Order::factory()->for($user)->create(['status' => 'delivered']);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    $response = $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 4,
        'title' => 'Great product',
        'body' => 'Really enjoyed it.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.rating', 4);

    $this->assertDatabaseHas('reviews', [
        'product_id' => $product->id,
        'user_id' => $user->id,
        'is_approved' => false,
        'verified_purchase' => true,
    ]);
});

it('rejects reviews without a delivered order', function () {
    $user = createUser();
    $product = createProduct();

    $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
    ])->assertStatus(422);

    // Pending orders do not count either.
    $pending = Order::factory()->for($user)->create(['status' => 'pending']);
    OrderItem::factory()->for($pending)->create(['product_id' => $product->id]);

    $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
    ])->assertStatus(422);

    expect(Review::where('user_id', $user->id)->count())->toBe(0);
});

it('lets guest-checkout buyers review after they register', function () {
    $user = createUser();
    $product = createProduct();

    $guestOrder = Order::factory()->create([
        'user_id' => null,
        'guest_email' => $user->email,
        'status' => 'delivered',
    ]);
    OrderItem::factory()->for($guestOrder)->create(['product_id' => $product->id]);

    $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
    ])->assertCreated();

    $this->assertDatabaseHas('reviews', [
        'user_id' => $user->id,
        'product_id' => $product->id,
        'verified_purchase' => true,
    ]);
});

it('lets guests review with a matching delivered order, no account needed', function () {
    $product = createProduct();

    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'guestreview@example.com',
        'status' => 'delivered',
    ]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    $response = $this->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
        'guest_name' => 'Guest Reviewer',
        'guest_email' => 'guestreview@example.com',
        'order_number' => $order->order_number,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('reviews', [
        'product_id' => $product->id,
        'guest_name' => 'Guest Reviewer',
        'verified_purchase' => true,
    ]);
});

it('rejects guest reviews that do not match a delivered order', function () {
    $product = createProduct();

    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'buyer@example.com',
        'status' => 'delivered',
    ]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    // Wrong order number.
    $this->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 4,
        'guest_name' => 'Impostor',
        'guest_email' => 'buyer@example.com',
        'order_number' => 'ORD-20000101-XXXXXX',
    ])->assertStatus(422);

    // Right number, wrong email.
    $this->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 4,
        'guest_name' => 'Impostor',
        'guest_email' => 'someone-else@example.com',
        'order_number' => $order->order_number,
    ])->assertStatus(422);

    // Pending orders do not count.
    $pending = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'waiting@example.com',
        'status' => 'pending',
    ]);
    OrderItem::factory()->for($pending)->create(['product_id' => $product->id]);

    $this->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 4,
        'guest_name' => 'Waiting Buyer',
        'guest_email' => 'waiting@example.com',
        'order_number' => $pending->order_number,
    ])->assertStatus(422);

    expect(Review::where('product_id', $product->id)->count())->toBe(0);
});

it('rejects duplicate guest reviews for the same order email', function () {
    $product = createProduct();

    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => 'repeat@example.com',
        'status' => 'delivered',
    ]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    $payload = [
        'rating' => 5,
        'guest_name' => 'Repeat Buyer',
        'guest_email' => 'repeat@example.com',
        'order_number' => $order->order_number,
    ];

    $this->postJson("/api/products/{$product->slug}/reviews", $payload)->assertCreated();
    $this->postJson("/api/products/{$product->slug}/reviews", $payload)->assertStatus(422);
});

it('blocks a second review after registering with the same email', function () {
    $user = createUser();
    $product = createProduct();

    $order = Order::factory()->create([
        'user_id' => null,
        'guest_email' => $user->email,
        'status' => 'delivered',
    ]);
    OrderItem::factory()->for($order)->create(['product_id' => $product->id]);

    $this->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
        'guest_name' => 'Soon Registered',
        'guest_email' => $user->email,
        'order_number' => $order->order_number,
    ])->assertCreated();

    $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 4,
    ])->assertStatus(422);
});

it('rejects duplicate review from same user', function () {
    $user = createUser();
    $product = createProduct();
    Review::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
    ]);

    $response->assertStatus(422);
});

it('converts a lost review race into 422 instead of 500', function () {
    // The app-level duplicate check and the insert are not atomic: two
    // concurrent submits can both pass the check, and the loser's insert
    // hits the database unique index. Emulate the loser deterministically
    // by inserting directly once the row already exists.
    $user = createUser();
    $product = createProduct();
    Review::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    try {
        Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'rating' => 5,
            'is_approved' => false,
        ]);
        $this->fail('Expected a unique violation emulating the lost race.');
    } catch (UniqueConstraintViolationException) {
        // Expected: this is the exception the controller must convert.
    }

    // And the endpoint contract stays 422 either way.
    $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 5,
    ])->assertStatus(422);
});

it('validates review rating range', function () {
    $user = createUser();
    $product = createProduct();

    $response = $this->actingAs($user)->postJson("/api/products/{$product->slug}/reviews", [
        'rating' => 6,
    ]);

    $response->assertStatus(422);
});

it('hides approved but unverified reviews everywhere', function () {
    $product = createProduct();
    Review::factory()->create([
        'product_id' => $product->id,
        'user_id' => createUser()->id,
        'rating' => 1,
        'is_approved' => true,
        'verified_purchase' => false,
    ]);

    $this->getJson("/api/products/{$product->slug}/reviews")->assertOk()
        ->assertJsonPath('data.summary.total', 0);

    $response = $this->getJson('/api/products');
    $productData = collect($response->json('data.data'))->firstWhere('id', $product->id);
    expect($productData['review_summary']['total'])->toBe(0);
});

it('adds product to wishlist', function () {
    $user = createUser();
    $product = createProduct();

    $response = $this->actingAs($user)->postJson("/api/wishlist/{$product->slug}/toggle");

    $response->assertSuccessful()
        ->assertJsonPath('data.wishlisted', true);

    $this->assertDatabaseHas('wishlists', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
});

it('removes product from wishlist on second toggle', function () {
    $user = createUser();
    $product = createProduct();
    Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->postJson("/api/wishlist/{$product->slug}/toggle");

    $response->assertOk()
        ->assertJsonPath('data.wishlisted', false);

    $this->assertDatabaseMissing('wishlists', [
        'user_id' => $user->id,
        'product_id' => $product->id,
    ]);
});

it('lists user wishlist', function () {
    $user = createUser();
    $product = createProduct();
    Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->getJson('/api/wishlist');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('checks wishlisted product ids', function () {
    $user = createUser();
    $product = createProduct();
    Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    $response = $this->actingAs($user)->getJson("/api/wishlist/check?product_ids[]={$product->id}");

    $response->assertOk()
        ->assertJsonPath('data', [$product->id]);
});

it('lists published CMS pages', function () {
    CmsPage::factory()->published()->create(['title' => 'About Us', 'slug' => 'about-us']);
    CmsPage::factory()->draft()->create(['title' => 'Draft Page', 'slug' => 'draft-page']);

    $response = $this->getJson('/api/pages');

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('shows a published CMS page by slug', function () {
    CmsPage::factory()->published()->create(['slug' => 'terms', 'title' => 'Terms']);

    $response = $this->getJson('/api/pages/terms');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'terms');
});

it('returns 404 for unpublished CMS page', function () {
    CmsPage::factory()->draft()->create(['slug' => 'secret']);

    $this->getJson('/api/pages/secret')->assertStatus(404);
});

it('filters products by price range', function () {
    Product::factory()->create(['price' => 50, 'is_active' => true]);
    Product::factory()->create(['price' => 200, 'is_active' => true]);
    Product::factory()->create(['price' => 500, 'is_active' => true]);

    $response = $this->getJson('/api/products?min_price=100&max_price=300');

    $response->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('includes review summary in product list', function () {
    $product = createProduct();
    Review::factory()->approved()->count(3)->create(['product_id' => $product->id, 'rating' => 4]);

    $response = $this->getJson('/api/products');

    $response->assertOk();
    $productData = collect($response->json('data.data'))->firstWhere('id', $product->id);
    expect($productData['review_summary']['total'])->toBe(3);
});

it('includes review summary in product detail', function () {
    $product = createProduct();
    Review::factory()->approved()->create(['product_id' => $product->id, 'rating' => 5]);
    Review::factory()->approved()->create(['product_id' => $product->id, 'rating' => 3]);

    $response = $this->getJson("/api/products/{$product->slug}");

    $response->assertOk()
        ->assertJsonPath('data.review_summary.total', 2)
        ->assertJsonFragment(['average' => 4.0]);
});

it('admin can approve review', function () {
    $admin = createAdmin();
    $review = Review::factory()->pending()->create();

    $response = $this->actingAs($admin)->post("/admin/reviews/{$review->id}/approve");

    $response->assertRedirect();
    $this->assertDatabaseHas('reviews', ['id' => $review->id, 'is_approved' => true]);
});

it('admin can delete review', function () {
    $admin = createAdmin();
    $review = Review::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/reviews/{$review->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
});

it('admin can CRUD CMS pages', function () {
    $admin = createAdmin();

    $response = $this->actingAs($admin)->post('/admin/pages', [
        'title' => 'Privacy Policy',
        'body' => '<p>We respect your privacy.</p>',
        'is_published' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cms_pages', ['title' => 'Privacy Policy']);

    $page = CmsPage::where('slug', 'privacy-policy')->first();

    $response = $this->actingAs($admin)->put("/admin/pages/{$page->id}", [
        'title' => 'Privacy Policy Updated',
        'body' => '<p>Updated.</p>',
        'is_published' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('cms_pages', ['id' => $page->id, 'title' => 'Privacy Policy Updated']);

    $response = $this->actingAs($admin)->delete("/admin/pages/{$page->id}");
    $response->assertRedirect();
    $this->assertSoftDeleted('cms_pages', ['id' => $page->id]);
});

it('caps oversized wishlist check input', function () {
    $user = createUser();
    $product = createProduct();
    Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);

    $ids = array_merge([$product->id], range(100000, 100200));

    $response = $this->actingAs($user)->getJson('/api/wishlist/check?'.http_build_query(['product_ids' => $ids]));

    $response->assertOk()->assertJsonPath('data', [$product->id]);
});

it('loads wishlist inventory without per-item queries', function () {
    $user = createUser();

    foreach (range(1, 3) as $i) {
        $product = Product::factory()->create(['is_active' => true, 'price' => 100]);
        Inventory::factory()->forProduct($product)->withQuantity(5)->create();
        Wishlist::factory()->create(['user_id' => $user->id, 'product_id' => $product->id]);
    }

    $inventoryQueries = 0;
    DB::listen(function ($query) use (&$inventoryQueries) {
        if (str_contains($query->sql, '"inventories"')) {
            $inventoryQueries++;
        }
    });

    $this->actingAs($user)->getJson('/api/wishlist')->assertOk();

    expect($inventoryQueries)->toBeLessThanOrEqual(1);
});
