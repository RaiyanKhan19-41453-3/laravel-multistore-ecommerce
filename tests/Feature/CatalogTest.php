<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Review;
use App\Services\CatalogCache;
use Illuminate\Support\Facades\DB;

it('renders the store products page with server-side products and filters', function () {
    Product::factory()->create(['name' => 'Catalog Test Shoe', 'is_active' => true]);

    $response = $this->get('/products?search=catalog');

    $response->assertOk()->assertInertia(
        fn ($p) => $p->component('store/products/index')
            ->has('products.data', 1)
            ->where('filters.search', 'catalog')
            ->has('brands')
            ->has('categories')
    );
});

it('loads approved review aggregates without per-product review queries', function () {
    $products = Product::factory()->count(3)->create(['is_active' => true]);

    foreach ($products as $product) {
        Review::factory()->approved()->count(2)->create(['product_id' => $product->id, 'rating' => 4]);
    }

    $reviewQueries = 0;
    DB::listen(function ($query) use (&$reviewQueries) {
        if (str_contains($query->sql, '"reviews"')) {
            $reviewQueries++;
        }
    });

    $response = $this->getJson('/api/products?per_page=10');

    $response->assertOk();
    expect($reviewQueries)->toBeLessThanOrEqual(2);

    $first = $response->json('data.data.0');
    expect($first['review_summary'])->toMatchArray(['total' => 2, 'average' => 4.0]);
});

it('serves featured products from cache and flushes on product save', function () {
    CatalogCache::flushFeatured();

    $product = Product::factory()->create([
        'name' => 'Original Featured',
        'is_active' => true,
        'is_featured' => true,
    ]);

    $this->getJson('/api/products/featured')->assertJsonPath('data.0.name', 'Original Featured');

    DB::table('products')->where('id', $product->id)->update(['name' => 'Sneaky Edit']);

    $this->getJson('/api/products/featured')->assertJsonPath('data.0.name', 'Original Featured');

    $product->name = 'Flushed Name';
    $product->save();

    $this->getJson('/api/products/featured')->assertJsonPath('data.0.name', 'Flushed Name');
});

it('includes descendant category products in category show', function () {
    $parent = Category::create(['name' => 'Parent Cat', 'slug' => 'parent-cat', 'is_active' => true]);
    $child = Category::create(['name' => 'Child Cat', 'slug' => 'child-cat', 'parent_id' => $parent->id, 'is_active' => true]);

    $product = Product::factory()->create(['is_active' => true]);
    $product->categories()->attach($child);

    $response = $this->getJson("/api/categories/{$parent->slug}");

    $response->assertOk();
    expect(collect($response->json('data.products.data'))->pluck('id'))->toContain($product->id);
});

it('returns localized names and review summaries on category pages', function () {
    $category = Category::create(['name' => 'Shoes', 'name_ar' => 'أحذية', 'slug' => 'shoes', 'is_active' => true]);
    $product = Product::factory()->create(['name' => 'Runner', 'name_ar' => 'عداء', 'is_active' => true]);
    $product->categories()->attach($category);
    Review::factory()->approved()->create(['product_id' => $product->id, 'rating' => 5]);

    $response = $this->getJson('/api/categories/shoes');

    $response->assertOk()->assertJsonPath('data.products.data.0.review_summary.total', 1);
    expect($response->json('data.products.data.0.brand'))->toBeNull();

    $this->app->setLocale('ar');
    $response = $this->getJson('/api/categories/shoes');

    $response->assertOk()
        ->assertJsonPath('data.name', 'أحذية')
        ->assertJsonPath('data.products.data.0.name', 'عداء');
});

it('returns localized names and review summaries on brand pages', function () {
    $brand = Brand::create(['name' => 'Nike', 'name_ar' => 'نايكي', 'slug' => 'nike', 'is_active' => true]);
    $product = Product::factory()->create(['brand_id' => $brand->id, 'is_active' => true]);
    Review::factory()->approved()->count(2)->create(['product_id' => $product->id, 'rating' => 4]);

    $response = $this->getJson('/api/brands/nike');

    $response->assertOk()
        ->assertJsonPath('data.products.data.0.review_summary.total', 2)
        ->assertJsonPath('data.products.data.0.brand.slug', 'nike');

    $this->app->setLocale('ar');
    $response = $this->getJson('/api/brands/nike');

    $response->assertOk()
        ->assertJsonPath('data.name', 'نايكي')
        ->assertJsonPath('data.products.data.0.brand.name', 'نايكي');
});

it('sends filters as a JSON object so array-prototype keys cannot leak to the client', function () {

    createProduct(100, 5);

    $response = $this->get('/products');

    $response->assertOk();
    $filters = $response->viewData('page')['props']['filters'];

    // $request->only() returns PHP [] when empty, which serializes to JSON []
    // — and JS [].sort is Array.prototype.sort, which useState() invokes as
    // a lazy initializer and crashes the page. stdClass serializes as {}.
    expect($filters)->toBeInstanceOf(stdClass::class);
    expect(json_encode($filters))->toBe('{}');
});

it('sorts by rating without dropping unreviewed products', function () {
    $rated = Product::factory()->create(['name' => 'Rated One', 'is_active' => true]);
    $plain = Product::factory()->create(['name' => 'Unreviewed One', 'is_active' => true]);
    Review::factory()->approved()->create(['product_id' => $rated->id, 'rating' => 5]);

    $response = $this->getJson('/api/products?sort=rating&direction=desc&per_page=10');

    $response->assertOk();
    $ids = collect($response->json('data.data'))->pluck('id')->all();

    expect($ids)->toContain($rated->id)->toContain($plain->id);
    expect($ids[0])->toBe($rated->id);
});
