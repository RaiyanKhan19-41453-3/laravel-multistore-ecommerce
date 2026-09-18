<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\AdminStoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingController extends Controller
{
    public function index(): Response
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        $scope = fn ($q) => $storeId ? $q->where('store_id', $storeId) : $q;

        $methods = $scope(ShippingMethod::orderBy('sort_order'))->get();
        $zones = $scope(ShippingZone::orderBy('sort_order'))->get();
        $rates = $scope(ShippingRate::with(['shippingMethod', 'shippingZone']))->get();

        return Inertia::render('admin/shipping/index', [
            'methods' => $methods,
            'zones' => $zones,
            'rates' => $rates,
        ]);
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'estimated_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        ShippingMethod::create($validated);

        return to_route('admin.shipping.index');
    }

    public function updateMethod(Request $request, ShippingMethod $method): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'estimated_days' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $method->update($validated);

        return to_route('admin.shipping.index');
    }

    public function destroyMethod(ShippingMethod $method): RedirectResponse
    {
        $method->delete();

        return to_route('admin.shipping.index');
    }

    public function storeZone(Request $request): RedirectResponse
    {
        $storeId = app(AdminStoreContext::class)->selectedId();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:100',
            'cities' => 'required_if:is_fallback,false|array',
            'cities.*' => 'string|max:100',
            'is_fallback' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $isFallback = $validated['is_fallback'] ?? false;

        if ($isFallback) {
            $existing = ShippingZone::where('is_fallback', true)
                ->where('country', $validated['country'])
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->first();
            if ($existing) {
                return back()->withErrors(['is_fallback' => 'A fallback zone already exists for this country. Edit the existing one instead.']);
            }
            $validated['cities'] = null;
        }

        ShippingZone::validateNoDuplicateCities(
            null,
            $validated['cities'] ?? null,
            $isFallback,
            $storeId
        );

        ShippingZone::create($validated);

        return to_route('admin.shipping.index');
    }

    public function updateZone(Request $request, ShippingZone $zone): RedirectResponse
    {
        $storeId = app(AdminStoreContext::class)->selectedId() ?? $zone->store_id;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:100',
            'cities' => 'required_if:is_fallback,false|array',
            'cities.*' => 'string|max:100',
            'is_fallback' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $isFallback = $validated['is_fallback'] ?? false;

        if ($isFallback) {
            $existing = ShippingZone::where('is_fallback', true)
                ->where('country', $validated['country'])
                ->where('id', '!=', $zone->id)
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->first();
            if ($existing) {
                return back()->withErrors(['is_fallback' => 'A fallback zone already exists for this country. Edit the existing one instead.']);
            }
            $validated['cities'] = null;
        }

        ShippingZone::validateNoDuplicateCities(
            $zone->id,
            $validated['cities'] ?? null,
            $isFallback,
            $storeId
        );

        $zone->update($validated);

        return to_route('admin.shipping.index');
    }

    public function destroyZone(ShippingZone $zone): RedirectResponse
    {
        $zone->delete();

        return to_route('admin.shipping.index');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $storeId = app(AdminStoreContext::class)->selectedId();
        $exists = fn (string $table) => app(AdminStoreContext::class)->existsInStore($table, $storeId);

        $validated = $request->validate([
            'shipping_method_id' => ['required', $exists('shipping_methods')],
            'shipping_zone_id' => ['required', $exists('shipping_zones')],
            'price' => 'required|numeric|min:0',
            'free_shipping_min' => 'nullable|numeric|min:0',
        ]);

        $method = ShippingMethod::find($validated['shipping_method_id']);
        $zone = ShippingZone::find($validated['shipping_zone_id']);

        if ($method && $zone && $method->store_id && $zone->store_id && $method->store_id !== $zone->store_id) {
            return back()->withErrors(['shipping_zone_id' => 'Method and zone must belong to the same store.']);
        }

        ShippingRate::updateOrCreate(
            [
                'shipping_method_id' => $validated['shipping_method_id'],
                'shipping_zone_id' => $validated['shipping_zone_id'],
            ],
            [
                'price' => $validated['price'],
                'free_shipping_min' => $validated['free_shipping_min'] ?? null,
            ],
        );

        return to_route('admin.shipping.index');
    }

    public function destroyRate(ShippingRate $rate): RedirectResponse
    {
        $rate->delete();

        return to_route('admin.shipping.index');
    }
}
