<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;

it('serves a sitemap with live storefront URLs', function () {
    $product = Product::factory()->create(['is_active' => true, 'slug' => 'sitemap-tee']);
    $category = Category::create(['name' => 'Sitemap Cats', 'slug' => 'sitemap-cats', 'is_active' => true]);
    $brand = Brand::create(['name' => 'Sitemap Brand', 'slug' => 'sitemap-brand', 'is_active' => true]);
    $page = CmsPage::factory()->create(['slug' => 'sitemap-about', 'is_published' => true]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $body = $response->getContent();

    expect($body)->toContain('/products/sitemap-tee')
        ->and($body)->toContain('/categories/sitemap-cats')
        ->and($body)->toContain('/brands/sitemap-brand')
        ->and($body)->toContain('/pages/sitemap-about')
        ->and($body)->toContain('<urlset');

    expect($product->fresh())->not->toBeNull();
    expect($category->fresh())->not->toBeNull();
    expect($brand->fresh())->not->toBeNull();
    expect($page->fresh())->not->toBeNull();
});

it('excludes inactive records from the sitemap', function () {
    Product::factory()->create(['is_active' => false, 'slug' => 'sitemap-hidden']);

    $body = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($body)->not->toContain('/products/sitemap-hidden');
});
