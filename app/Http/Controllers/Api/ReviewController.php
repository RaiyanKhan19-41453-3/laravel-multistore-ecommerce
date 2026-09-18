<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request, string $slug): JsonResponse
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();

        $reviews = Review::approved()
            ->where('product_id', $product->id)
            ->with('user:id,name')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'total' => Review::approved()->where('product_id', $product->id)->count(),
            'average' => (float) Review::approved()->where('product_id', $product->id)->avg('rating'),
            'distribution' => Review::approved()
                ->where('product_id', $product->id)
                ->selectRaw('rating, count(*) as count')
                ->groupBy('rating')
                ->pluck('count', 'rating')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $reviews,
                'summary' => $summary,
            ],
        ]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $product = Product::active()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:2000',
        ]);

        $existing = Review::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        // The check above and this insert are not atomic: concurrent
        // submits can both pass the check, and the loser hits the unique
        // index. Surface the same 422, never a 500.
        try {
            $review = Review::create([
                'user_id' => $request->user()->id,
                'product_id' => $product->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'] ?? null,
                'is_approved' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted for moderation.',
            'data' => $review->load('user:id,name'),
        ], 201);
    }
}
