<?php

namespace App\Services;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\CurrentStore;
use Illuminate\Support\Collection;

class ShippingService
{
    /**
     * Get available shipping rates for a city (estimate endpoint).
     * The frontend uses this to display options; the actual cost is
     * recalculated server-side during checkout.
     */
    public function getAvailableRates(string $city, float $discountedSubtotal, string $country = 'Bangladesh', ?int $storeId = null): Collection
    {
        $storeId ??= app(CurrentStore::class)->scopeId();

        $zone = $this->findZoneForCity($city, $country, $storeId);

        if (! $zone) {
            return collect();
        }

        return ShippingRate::with('shippingMethod')
            ->where('shipping_zone_id', $zone->id)
            ->whereHas('shippingMethod', fn ($q) => $q->active())
            ->get()
            ->map(function (ShippingRate $rate) use ($discountedSubtotal) {
                return [
                    'id' => $rate->id,
                    'method_id' => $rate->shipping_method_id,
                    'method_name' => $rate->shippingMethod->name,
                    'method_description' => $rate->shippingMethod->description,
                    'estimated_days' => $rate->shippingMethod->estimated_days,
                    'price' => (float) $rate->price,
                    'free_shipping_min' => $rate->free_shipping_min ? (float) $rate->free_shipping_min : null,
                    'shipping_cost' => $rate->getShippingCost($discountedSubtotal),
                    'is_free' => $rate->getShippingCost($discountedSubtotal) <= 0,
                ];
            });
    }

    /**
     * Validate that a shipping rate exists, is active, and belongs to the customer's zone.
     * Returns the rate with shippingMethod loaded, or throws with a validation message.
     */
    public function validateAndGetRate(int $rateId, string $city, string $country = 'Bangladesh', ?int $storeId = null): ShippingRate
    {
        $storeId ??= app(CurrentStore::class)->scopeId();

        $query = ShippingRate::with('shippingMethod')->whereKey($rateId);

        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        $rate = $query->first();

        if (! $rate) {
            throw new \InvalidArgumentException('Selected shipping rate is invalid.');
        }

        if (! $rate->shippingMethod->is_active) {
            throw new \InvalidArgumentException('Selected shipping method is no longer available.');
        }

        $zone = $this->findZoneForCity($city, $country, $storeId);

        if (! $zone || $rate->shipping_zone_id !== $zone->id) {
            throw new \InvalidArgumentException('Selected shipping rate is not available for your city.');
        }

        if ($zone->store_id !== null && $rate->store_id !== null && $zone->store_id !== $rate->store_id) {
            throw new \InvalidArgumentException('Selected shipping rate is not available for your city.');
        }

        return $rate;
    }

    private function findZoneForCity(string $city, string $country, ?int $storeId): ?ShippingZone
    {
        // Same semantics as ShippingZone::findForCity (active non-fallback
        // match, then active fallback), additionally scoped to the store.
        $zone = ShippingZone::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->active()->nonFallback()
            ->where('country', $country)
            ->get()
            ->first(fn ($z) => $z->containsCity($city));

        return $zone ?? ShippingZone::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->active()->where('is_fallback', true)->where('country', $country)->first();
    }

    public function calculateShippingCost(ShippingRate $rate, float $discountedSubtotal): float
    {
        return $rate->getShippingCost($discountedSubtotal);
    }
}
