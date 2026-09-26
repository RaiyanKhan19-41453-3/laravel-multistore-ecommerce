<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ImageService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DemoMediaSeeder extends Seeder
{
    /**
     * Attach demo imagery from the committed fixture pack
     * (database/seeders/fixtures/images): product shots through
     * ImageService so all sizes exist, brand logos and category covers
     * as plain files. Idempotent: skips anything already imaged.
     */
    public function run(): void
    {
        $fixtures = database_path('seeders/fixtures/images');

        if (! is_dir($fixtures)) {
            return;
        }

        $this->seedBrandLogos("{$fixtures}/brands");
        $this->seedCategoryCovers("{$fixtures}/categories");
        $this->seedProductShots("{$fixtures}/products");
    }

    private function seedBrandLogos(string $dir): void
    {
        foreach (['nike', 'adidas', 'bata'] as $slug) {
            $file = "{$dir}/{$slug}.jpg";

            if (! is_file($file)) {
                continue;
            }

            $brand = Brand::where('slug', $slug)->first();

            if (! $brand || $brand->logo) {
                continue;
            }

            $path = "brand-logos/{$slug}.jpg";
            Storage::disk('public')->put($path, file_get_contents($file));
            $brand->update(['logo' => $path]);
        }
    }

    private function seedCategoryCovers(string $dir): void
    {
        $map = [
            'clothing' => 'c1.jpg',
            'shoes' => 'c2.jpg',
            'accessories' => 'c3.jpg',
            't-shirts' => 'c4.jpg',
            'sneakers' => 'c5.jpg',
            'bags' => 'c6.jpg',
        ];

        foreach ($map as $slug => $file) {
            $path = "{$dir}/{$file}";

            if (! is_file($path)) {
                continue;
            }

            $category = Category::where('slug', $slug)->first();

            if (! $category || $category->image) {
                continue;
            }

            $stored = "categories/{$slug}.jpg";
            Storage::disk('public')->put($stored, file_get_contents($path));
            $category->update(['image' => $stored]);
        }
    }

    private function seedProductShots(string $dir): void
    {
        $files = glob("{$dir}/*.jpg") ?: [];

        if ($files === []) {
            return;
        }

        $service = new ImageService;
        $index = 0;

        // Three different shots per product so galleries, rails, and the
        // quick-view modal have something to show.
        foreach (Product::query()->orderBy('id')->cursor() as $product) {
            $existing = ProductImage::where('product_id', $product->id)
                ->whereNull('product_variant_id')
                ->count();

            for ($i = $existing; $i < 3; $i++) {
                $file = $files[($index + $product->id) % count($files)];
                $index++;

                $image = $service->upload(
                    new UploadedFile($file, basename($file), 'image/jpeg', null, true),
                    $product->id
                );

                if ($existing === 0 && $i === 0) {
                    $image->update(['is_primary' => true]);
                }
            }
        }

        // One different shot per variant so variant switching visibly
        // changes the gallery on the storefront.
        foreach (ProductVariant::query()->orderBy('id')->cursor() as $variant) {
            if (ProductImage::where('product_variant_id', $variant->id)->exists()) {
                continue;
            }

            $file = $files[($index + $variant->id * 3) % count($files)];
            $index++;

            $service->upload(
                new UploadedFile($file, basename($file), 'image/jpeg', null, true),
                $variant->product_id,
                $variant->id
            );
        }
    }
}
