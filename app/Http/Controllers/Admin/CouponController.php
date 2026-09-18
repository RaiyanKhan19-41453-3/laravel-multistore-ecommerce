<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Discount;
use App\Support\CurrentStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->where('store_id', $storeId)],
            'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['discount_id'] = $discount->id;

        $coupon = Coupon::create($validated);

        // Coupons live on their discount's store, even when the request
        // resolved elsewhere. Set directly (bypasses fillable); the
        // central auto-fill skips non-empty values, so this wins.
        if ($discount->store_id !== null && $coupon->store_id !== $discount->store_id) {
            $coupon->store_id = $discount->store_id;
            $coupon->save();
        }

        return to_route('admin.discounts.show', $discount);
    }

    public function update(Request $request, Discount $discount, Coupon $coupon): RedirectResponse
    {
        if ($coupon->discount_id !== $discount->id) {
            abort(422, 'Coupon does not belong to this discount.');
        }

        $storeId = app(CurrentStore::class)->scopeId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon->id)->where('store_id', $storeId)],
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
