<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Scopes\BelongsToStore;
use App\Support\CurrentStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageService
{
    private const SIZES = [
        'large' => 1200,
        'medium' => 600,
        'thumbnail' => 300,
    ];

    private const JPEG_QUALITY = 85;

    private const FORMAT = 'jpg';

    private static ?ImageManager $manager = null;

    private static function manager(): ImageManager
    {
        if (self::$manager === null) {
            self::$manager = new ImageManager(new Driver);
        }

        return self::$manager;
    }

    public function upload(UploadedFile $file, int $productId, ?int $variantId = null): ProductImage
    {
        $original = self::manager()->read($file);

        $uuid = (string) Str::uuid();
        $directory = "products/{$productId}";

        try {
            // Prefer the parent product's store: an admin managing several
            // stores may upload while resolved to a different (or default)
            // store, and the file prefix must match the record's store_id.
            $productStoreId = Product::withoutGlobalScope(BelongsToStore::class)
                ->whereKey($productId)->value('store_id');
            $storeId = $productStoreId
                ?? app(CurrentStore::class)->id()
                ?? app(CurrentStore::class)->default()?->id;

            if ($storeId) {
                $directory = "products/{$storeId}/{$productId}";
            }
        } catch (\Throwable) {
            // Keep legacy path when store context is unavailable.
            $productStoreId = null;
        }

        $originalFilename = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();

        $width = $original->width();
        $height = $original->height();

        $original->orient();

        $paths = [];

        $originalPath = "{$directory}/{$uuid}_org.".self::FORMAT;
        $encoded = $original->toJpeg(self::JPEG_QUALITY);
        Storage::disk('public')->put($originalPath, (string) $encoded);
        $paths['original'] = $originalPath;

        foreach (self::SIZES as $size => $maxWidth) {
            $resized = $original->resizeDown(
                width: $maxWidth,
                height: null,
            );

            $sizePath = "{$directory}/{$uuid}_".substr($size, 0, 2).'.'.self::FORMAT;
            $resizedEncoded = $resized->toJpeg(self::JPEG_QUALITY);
            Storage::disk('public')->put($sizePath, (string) $resizedEncoded);

            $paths[$size] = $sizePath;
        }

        $image = new ProductImage([
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'path' => $paths['original'],
            'filename' => $originalFilename,
            'mime_type' => 'image/jpeg',
            'size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'paths' => $paths,
        ]);

        // Keep the record on the product's store even when the request
        // resolved to another store. Set directly (bypasses fillable);
        // the central auto-fill skips non-empty values, so this wins.
        // Falls back to auto-fill when the product has no store yet.
        if (! empty($productStoreId)) {
            $image->store_id = $productStoreId;
        }

        $image->save();

        return $image;
    }

    public function delete(ProductImage $image): void
    {
        foreach ($image->paths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $image->delete();
    }

    public function setPrimary(ProductImage $image): void
    {
        $query = ProductImage::where('product_id', $image->product_id);

        if ($image->product_variant_id) {
            $query->where('product_variant_id', $image->product_variant_id);
        } else {
            $query->whereNull('product_variant_id');
        }

        $query->where('id', '!=', $image->id)
            ->update(['is_primary' => false]);

        $image->update(['is_primary' => true]);
    }
}
