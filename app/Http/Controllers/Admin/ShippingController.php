<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingController extends Controller
{
    public function index(): Response
    {
        $methods = ShippingMethod::orderBy('sort_order')->get();
        $zones = ShippingZone::orderBy('sort_order')->get();
        $rates = ShippingRate::with(['shippingMethod', 'shippingZone'])->get();

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
                ->first();
            if ($existing) {
                return back()->withErrors(['is_fallback' => 'A fallback zone already exists for this country. Edit the existing one instead.']);
            }
            $validated['cities'] = null;
        }

        ShippingZone::validateNoDuplicateCities(
            null,
            $validated['cities'] ?? null,
            $isFallback
        );

        ShippingZone::create($validated);

        return to_route('admin.shipping.index');
    }

    public function updateZone(Request $request, ShippingZone $zone): RedirectResponse
    {
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
                ->first();
            if ($existing) {
                return back()->withErrors(['is_fallback' => 'A fallback zone already exists for this country. Edit the existing one instead.']);
            }
            $validated['cities'] = null;
        }

        ShippingZone::validateNoDuplicateCities(
            $zone->id,
            $validated['cities'] ?? null,
            $isFallback
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
        $validated = $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'shipping_zone_id' => 'required|exists:shipping_zones,id',
            'price' => 'required|numeric|min:0',
            'free_shipping_min' => 'nullable|numeric|min:0',
        ]);

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
