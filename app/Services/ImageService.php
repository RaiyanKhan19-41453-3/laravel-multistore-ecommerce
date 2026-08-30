<?php

namespace App\Services;

use App\Models\ProductImage;
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

        return ProductImage::create([
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
