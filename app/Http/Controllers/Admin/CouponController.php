<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Discount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Discount $discount): Response
    {
        $discount->load('coupons');

        return Inertia::render('admin/discounts/show', [
            'discount' => $discount,
        ]);
    }

    public function store(Request $request, Discount $discount): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['discount_id'] = $discount->id;

        Coupon::create($validated);

        return to_route('admin.discounts.show', $discount);
    }

    public function update(Request $request, Discount $discount, Coupon $coupon): RedirectResponse
    {
        if ($coupon->discount_id !== $discount->id) {
            abort(422, 'Coupon does not belong to this discount.');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code,'.$coupon->id,
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        $coupon->update($validated);

        return to_route('admin.discounts.show', $discount);
    }

    public function toggle(Discount $discount, Coupon $coupon): RedirectResponse
    {
        if ($coupon->discount_id !== $discount->id) {
            abort(422, 'Coupon does not belong to this discount.');
        }

        $coupon->update(['is_active' => ! $coupon->is_active]);

        return to_route('admin.discounts.show', $discount);
    }

    public function destroy(Discount $discount, Coupon $coupon): RedirectResponse
    {
        if ($coupon->discount_id !== $discount->id) {
            abort(422, 'Coupon does not belong to this discount.');
        }

        $coupon->delete();

        return to_route('admin.discounts.show', $discount);
    }
}
