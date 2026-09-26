<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $user = $request->user();

        $rules = [
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:2000',
        ];

        if (! $user) {
            $rules['guest_name'] = 'required|string|max:100';
            $rules['guest_email'] = 'required|email|max:255';
            $rules['order_number'] = 'required|string|max:50';
        }

        $validated = $request->validate($rules);

        if ($user) {
            $email = strtolower(trim((string) $user->email));

            // Either a review from this account or a guest review from the
            // same email counts as already reviewed.
            $existing = Review::where('product_id', $product->id)
                ->where(function ($query) use ($user, $email) {
                    $query->where('user_id', $user->id);

                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(TRIM(guest_email)) = ?', [$email]);
                    }
                })
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product.',
                ], 422);
            }

            // Only delivered buyers may review: the flag is stored at creation
            // so the badge survives even if the order record later changes.
            // Guest checkouts carry no user_id, so match those by email too:
            // BD shops see heavy guest volume.
            $email = strtolower(trim((string) $user->email));

            $bought = Order::whereIn('status', ['delivered', 'completed'])
                ->where(function ($query) use ($user, $email) {
                    $query->where('user_id', $user->id);

                    if ($email !== '') {
                        $query->orWhereRaw('LOWER(TRIM(guest_email)) = ?', [$email]);
                    }
                })
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->exists();

            if (! $bought) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only customers with a delivered order can review this product.',
                ], 422);
            }

            $attributes = [
                'user_id' => $user->id,
                'verified_purchase' => true,
            ];
        } else {
            // Guest path: the order number plus its email prove the purchase,
            // same knowledge the order-lookup endpoint already requires.
            $email = strtolower(trim($validated['guest_email']));

            $order = Order::where('order_number', $validated['order_number'])
                ->whereIn('status', ['delivered', 'completed'])
                ->whereRaw('LOWER(TRIM(guest_email)) = ?', [$email])
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'We could not verify a delivered order for these details.',
                ], 422);
            }

            $duplicate = Review::where('product_id', $product->id)
                ->whereRaw('LOWER(TRIM(guest_email)) = ?', [$email])
                ->exists();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product.',
                ], 422);
            }

            $attributes = [
                'user_id' => null,
                'guest_name' => trim($validated['guest_name']),
                'guest_email' => $email,
                'verified_purchase' => true,
            ];
        }

        // The checks above and this insert are not atomic: concurrent
        // submits can both pass the checks, and the loser hits the unique
        // index (or a duplicate guest row). Surface 422, never a 500.
        try {
            $review = Review::create(array_merge($attributes, [
                'product_id' => $product->id,
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'] ?? null,
                'is_approved' => false,
            ]));
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
