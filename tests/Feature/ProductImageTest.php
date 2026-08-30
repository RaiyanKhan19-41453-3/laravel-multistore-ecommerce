<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->seed(PermissionSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('super-admin');

    $this->product = Product::factory()->create([
        'type' => 'simple',
    ]);
});

test('admin can view product images page', function () {
    $response = $this->actingAs($this->user)
        ->get(route('admin.products.images.index', $this->product));

    $response->assertOk();
});

test('admin can upload product images', function () {
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $response = $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_images', [
        'product_id' => $this->product->id,
        'filename' => 'product.jpg',
    ]);
});

test('admin can upload multiple images at once', function () {
    $files = [
        UploadedFile::fake()->image('image1.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image2.png', 800, 600)->size(500),
        UploadedFile::fake()->image('image3.jpg', 800, 600)->size(500),
    ];

    $response = $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => $files,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseCount('product_images', 3);
});

test('upload rejects invalid file types', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $response->assertInvalid(['images.0']);
});

test('upload rejects files over 10mb', function () {
    $file = UploadedFile::fake()->image('large.jpg', 800, 600)->size(11000);

    $response = $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $response->assertInvalid(['images.0']);
});

test('variant image must belong to same product', function () {
    $otherProduct = Product::factory()->create(['type' => 'variable']);
    $variant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $response = $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
            'product_variant_id' => $variant->id,
        ]);

    $response->assertInvalid(['product_variant_id']);
});

test('admin can update image metadata', function () {
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $image = $this->product->images()->first();

    $response = $this->actingAs($this->user)
        ->put(route('admin.products.images.update', [$this->product, $image]), [
            'alt_text' => 'Updated alt text',
            'sort_order' => 5,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_images', [
        'id' => $image->id,
        'alt_text' => 'Updated alt text',
        'sort_order' => 5,
    ]);
});

test('setting primary unsets old primary', function () {
    $files = [
        UploadedFile::fake()->image('image1.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image2.jpg', 800, 600)->size(500),
    ];

    $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => $files,
        ]);

    $images = $this->product->images()->get();
    $firstImage = $images->first();
    $secondImage = $images->last();

    $this->assertTrue($firstImage->is_primary);
    $this->assertFalse($secondImage->is_primary);

    $this->actingAs($this->user)
        ->put(route('admin.products.images.update', [$this->product, $secondImage]), [
            'is_primary' => true,
        ]);

    $firstImage->refresh();
    $secondImage->refresh();

    $this->assertFalse($firstImage->is_primary);
    $this->assertTrue($secondImage->is_primary);
});

test('admin can delete image', function () {
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $image = $this->product->images()->first();

    $response = $this->actingAs($this->user)
        ->delete(route('admin.products.images.destroy', [$this->product, $image]));

    $response->assertRedirect();

    $this->assertSoftDeleted('product_images', ['id' => $image->id]);
});

test('admin can bulk delete images', function () {
    $files = [
        UploadedFile::fake()->image('image1.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image2.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image3.jpg', 800, 600)->size(500),
    ];

    $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => $files,
        ]);

    $images = $this->product->images()->get();
    $this->assertCount(3, $images);

    $response = $this->actingAs($this->user)
        ->delete(route('admin.products.images.bulk-destroy', $this->product), [
            'image_ids' => [$images[0]->id, $images[2]->id],
        ]);

    $response->assertRedirect();

    $this->assertSoftDeleted('product_images', ['id' => $images[0]->id]);
    $this->assertSoftDeleted('product_images', ['id' => $images[2]->id]);
    $this->assertDatabaseHas('product_images', ['id' => $images[1]->id, 'deleted_at' => null]);
});

test('admin can reorder images', function () {
    $files = [
        UploadedFile::fake()->image('image1.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image2.jpg', 800, 600)->size(500),
        UploadedFile::fake()->image('image3.jpg', 800, 600)->size(500),
    ];

    $this->actingAs($this->user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => $files,
        ]);

    $images = $this->product->images()->get();

    $response = $this->actingAs($this->user)
        ->put(route('admin.products.images.reorder', $this->product), [
            'images' => [
                ['id' => $images[0]->id, 'sort_order' => 2],
                ['id' => $images[1]->id, 'sort_order' => 0],
                ['id' => $images[2]->id, 'sort_order' => 1],
            ],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_images', ['id' => $images[0]->id, 'sort_order' => 2]);
    $this->assertDatabaseHas('product_images', ['id' => $images[1]->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('product_images', ['id' => $images[2]->id, 'sort_order' => 1]);
});

test('unauthenticated user cannot upload images', function () {
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $response = $this->post(route('admin.products.images.store', $this->product), [
        'images' => [$file],
    ]);

    $response->assertRedirect('/admin/login');
});

test('non-admin user cannot upload images', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('product.jpg', 800, 600)->size(500);

    $response = $this->actingAs($user)
        ->post(route('admin.products.images.store', $this->product), [
            'images' => [$file],
        ]);

    $response->assertForbidden();
});
