<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with(['product' => function ($q) {
                $q->active()->with(['brand', 'inventory', 'images' => fn ($iq) => $iq->primary()->limit(1)])->withCount('variants');
            }])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $items->getCollection()->transform(fn ($item) => [
            'id' => $item->id,
            'created_at' => $item->created_at,
            'product' => $item->product ? [
                'id' => $item->product->id,
                'name' => $item->product->displayName(),
                'slug' => $item->product->slug,
                'price' => (float) $item->product->price,
                'compare_at_price' => $item->product->compare_at_price ? (float) $item->product->compare_at_price : null,
                'brand' => $item->product->brand ? [
                    'name' => $item->product->brand->displayName(),
                    'slug' => $item->product->brand->slug,
                ] : null,
                'primary_image' => $item->product->images->first()?->getUrl('medium'),
                'in_stock' => $item->product->inventory?->getIsInStock() ?? false,
            ] : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $productIds = array_slice(
            array_filter(array_map('intval', (array) $request->query('product_ids', []))),
            0,
            100
        );

        $wishlisted = Wishlist::where('user_id', $request->user()->id)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $wishlisted,
        ]);
    }

    public function toggle(Request $request, string $slug): JsonResponse
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'success' => true,
                'data' => ['wishlisted' => false],
            ]);
        }

        Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['wishlisted' => true],
        ], 201);
    }

    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        if ($wishlist->user_id !== $request->user()->id) {
            abort(403);
        }

        $wishlist->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from wishlist.',
        ]);
    }
}
