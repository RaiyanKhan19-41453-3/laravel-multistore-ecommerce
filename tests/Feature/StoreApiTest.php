<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;

it('returns paginated active products', function () {
    Product::factory()->count(3)->create(['is_active' => true]);

    $response = $this->getJson('/api/products');

    $response->assertOk()->assertJson([
        'success' => true,
    ]);

    $response->assertJsonCount(3, 'data.data');
});

it('does not return inactive products', function () {
    Product::factory()->create(['is_active' => true]);
    Product::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/products');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.data');
});

it('searches products by name', function () {
    Product::factory()->create(['name' => 'Blue Running Shoes', 'is_active' => true]);
    Product::factory()->create(['name' => 'Red Sandals', 'is_active' => true]);

    $response = $this->getJson('/api/products?search=running');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.data');
    $response->assertJsonPath('data.data.0.name', 'Blue Running Shoes');
});

it('filters products by brand', function () {
    $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike', 'is_active' => true]);
    Product::factory()->create(['brand_id' => $brand->id, 'is_active' => true]);
    Product::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/products?brand_id={$brand->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data.data');
});

it('filters products by category', function () {
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);
    $product->categories()->attach($category);

    Product::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/products?category_id={$category->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data.data');
});

it('filters featured products', function () {
    Product::factory()->create(['is_active' => true, 'is_featured' => true]);
    Product::factory()->create(['is_active' => true, 'is_featured' => false]);

    $response = $this->getJson('/api/products?is_featured=1');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.data');
});

it('sorts products by price', function () {
    Product::factory()->create(['is_active' => true, 'price' => 500]);
    Product::factory()->create(['is_active' => true, 'price' => 100]);

    $response = $this->getJson('/api/products?sort=price&direction=asc');

    $response->assertOk();
    $response->assertJsonPath('data.data.0.price', 100);
    $response->assertJsonPath('data.data.1.price', 500);
});

it('returns featured products', function () {
    Product::factory()->create(['is_active' => true, 'is_featured' => true]);
    Product::factory()->create(['is_active' => true, 'is_featured' => false]);

    $response = $this->getJson('/api/products/featured');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('returns product detail by slug', function () {
    $product = Product::factory()->create([
        'is_active' => true,
        'slug' => 'test-product',
        'price' => 299,
    ]);
    Inventory::factory()->forProduct($product)->withQuantity(10)->create();

    $response = $this->getJson('/api/products/test-product');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'name' => $product->name,
            'slug' => 'test-product',
            'price' => 299.0,
        ],
    ]);
});

it('returns 404 for non-existent product slug', function () {
    $response = $this->getJson('/api/products/does-not-exist');

    $response->assertStatus(404);
});

it('returns product with variants', function () {
    $product = Product::factory()->create(['type' => 'variable', 'is_active' => true, 'slug' => 'variant-product']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 150]);
    Inventory::factory()->forVariant($variant)->withQuantity(5)->create();

    $response = $this->getJson('/api/products/variant-product');

    $response->assertOk();
    $response->assertJsonCount(1, 'data.variants');
    $response->assertJsonPath('data.variants.0.price', 150);
});

it('returns active categories as tree', function () {
    $parent = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
    Category::create(['name' => 'Phones', 'slug' => 'phones', 'parent_id' => $parent->id, 'is_active' => true]);
    Category::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);

    $response = $this->getJson('/api/categories');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonCount(1, 'data.0.children');
});

it('returns category detail with products', function () {
    $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
    $product = Product::factory()->create(['is_active' => true]);
    $product->categories()->attach($category);

    $response = $this->getJson('/api/categories/shoes');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'name' => 'Shoes',
            'slug' => 'shoes',
        ],
    ]);

    $response->assertJsonCount(1, 'data.products.data');
});

it('returns 404 for non-existent category slug', function () {
    $response = $this->getJson('/api/categories/nonexistent');

    $response->assertStatus(404);
});

it('returns active brands', function () {
    Brand::create(['name' => 'Nike', 'slug' => 'nike', 'is_active' => true]);
    Brand::create(['name' => 'Inactive', 'slug' => 'inactive', 'is_active' => false]);

    $response = $this->getJson('/api/brands');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.name', 'Nike');
});

it('returns brand detail with products', function () {
    $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike', 'is_active' => true]);
    Product::factory()->create(['brand_id' => $brand->id, 'is_active' => true]);

    $response = $this->getJson('/api/brands/nike');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'name' => 'Nike',
            'slug' => 'nike',
        ],
    ]);

    $response->assertJsonCount(1, 'data.products.data');
});

it('returns 404 for non-existent brand slug', function () {
    $response = $this->getJson('/api/brands/nonexistent');

    $response->assertStatus(404);
});

it('product listing includes primary image url', function () {
    $product = Product::factory()->create(['is_active' => true]);
    ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/1/test_lg.jpg',
        'filename' => 'test_lg.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000,
        'is_primary' => true,
        'paths' => ['original' => 'products/1/test.jpg', 'thumbnail' => 'products/1/test_th.jpg'],
    ]);

    $response = $this->getJson('/api/products');

    $response->assertOk();
    $response->assertJsonPath('data.data.0.primary_image', '/storage/products/1/test_lg.jpg');
});
