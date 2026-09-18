<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Review::with('user:id,name', 'product:id,name,slug');

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        $reviews = $query->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/reviews/index', [
            'reviews' => $reviews,
            'filters' => (object) $request->only(['is_approved', 'product_id']),
        ]);
    }

    public function approve(Review $review, NotificationService $notifications): RedirectResponse
    {
        $wasApproved = $review->is_approved;

        $review->update(['is_approved' => true]);

        if (! $wasApproved) {
            $notifications->notifyReviewApproved($review);
        }

        return to_route('admin.reviews.index');
    }

    public function destroy(Review $review): RedirectResponse
    {
        $review->delete();

        return to_route('admin.reviews.index');
    }
}
