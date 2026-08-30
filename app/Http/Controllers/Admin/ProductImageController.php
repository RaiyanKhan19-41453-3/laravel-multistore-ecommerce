<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductImageController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function index(Product $product): Response
    {
        $product->load([
            'images' => fn ($q) => $q->whereNull('product_variant_id'),
            'variants' => fn ($q) => $q->with('images'),
        ]);

        $images = $product->images->map(fn ($img) => [
            'id' => $img->id,
            'path' => $img->path,
            'filename' => $img->filename,
            'mime_type' => $img->mime_type,
            'size' => $img->size,
            'width' => $img->width,
            'height' => $img->height,
            'alt_text' => $img->alt_text,
            'sort_order' => $img->sort_order,
            'is_primary' => $img->is_primary,
            'paths' => $img->paths,
            'urls' => $img->getUrls(),
        ]);

        $variants = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'images' => $variant->images->map(fn ($img) => [
                'id' => $img->id,
                'path' => $img->path,
                'filename' => $img->filename,
                'mime_type' => $img->mime_type,
                'size' => $img->size,
                'width' => $img->width,
                'height' => $img->height,
                'alt_text' => $img->alt_text,
                'sort_order' => $img->sort_order,
                'is_primary' => $img->is_primary,
                'paths' => $img->paths,
                'urls' => $img->getUrls(),
            ]),
        ]);

        return Inertia::render('admin/products/images', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'type' => $product->type,
            ],
            'images' => $images,
            'variants' => $variants,
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'required|file|mimes:jpeg,png,webp,gif|max:10240',
            'product_variant_id' => 'nullable|exists:product_variants,id',
        ]);

        if ($request->product_variant_id) {
            $variant = ProductVariant::findOrFail($request->product_variant_id);
            if ($variant->product_id !== $product->id) {
                return back()->withErrors(['product_variant_id' => 'Variant does not belong to this product.']);
            }
        }

        $images = [];
        foreach ($request->file('images') as $file) {
            $image = $this->imageService->upload(
                $file,
                $product->id,
                $request->product_variant_id,
            );
            $images[] = $image;
        }

        $hasPrimary = ProductImage::where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->primary()
            ->exists();

        if (! $hasPrimary && ! $request->product_variant_id) {
            $firstImage = $images[0];
            $this->imageService->setPrimary($firstImage);
        }

        return back()->with('success', count($images).' image(s) uploaded successfully.');
    }

    public function update(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            return back()->withErrors(['image' => 'Image does not belong to this product.']);
        }

        $validated = $request->validate([
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_primary' => 'nullable|boolean',
        ]);

        if (isset($validated['is_primary']) && $validated['is_primary']) {
            $this->imageService->setPrimary($image);
            unset($validated['is_primary']);
        } elseif (isset($validated['is_primary']) && ! $validated['is_primary']) {
            $validated['is_primary'] = false;
        }

        $image->update($validated);

        return back()->with('success', 'Image updated successfully.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        if ($image->product_id !== $product->id) {
            return back()->withErrors(['image' => 'Image does not belong to this product.']);
        }

        $wasPrimary = $image->is_primary;
        $variantId = $image->product_variant_id;

        $this->imageService->delete($image);

        if ($wasPrimary) {
            $nextImage = ProductImage::where('product_id', $product->id)
                ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
                ->orderBy('sort_order')
                ->first();

            if ($nextImage) {
                $this->imageService->setPrimary($nextImage);
            }
        }

        return back()->with('success', 'Image deleted successfully.');
    }

    public function bulkDestroy(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'image_ids' => 'required|array',
            'image_ids.*' => 'required|exists:product_images,id',
        ]);

        $hadPrimary = false;
        $deletedVariantIds = collect();

        foreach ($validated['image_ids'] as $imageId) {
            $image = ProductImage::where('id', $imageId)
                ->where('product_id', $product->id)
                ->first();

            if (! $image) {
                continue;
            }

            if ($image->is_primary) {
                $hadPrimary = true;
                $deletedVariantIds->push($image->product_variant_id);
            }

            $this->imageService->delete($image);
        }

        if ($hadPrimary) {
            foreach ($deletedVariantIds->unique() as $variantId) {
                $nextImage = ProductImage::where('product_id', $product->id)
                    ->when($variantId, fn ($q) => $q->where('product_variant_id', $variantId), fn ($q) => $q->whereNull('product_variant_id'))
                    ->orderBy('sort_order')
                    ->first();

                if ($nextImage) {
                    $this->imageService->setPrimary($nextImage);
                }
            }
        }

        return back()->with('success', count($validated['image_ids']).' image(s) deleted successfully.');
    }

    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'images' => 'required|array',
            'images.*.id' => 'required|exists:product_images,id',
            'images.*.sort_order' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($validated, $product) {
            foreach ($validated['images'] as $item) {
                ProductImage::where('id', $item['id'])
                    ->where('product_id', $product->id)
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return back()->with('success', 'Images reordered successfully.');
    }
}
