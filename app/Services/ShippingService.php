<?php

namespace App\Services;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Support\Collection;

class ShippingService
{
    /**
     * Get available shipping rates for a city (estimate endpoint).
     * The frontend uses this to display options; the actual cost is
     * recalculated server-side during checkout.
     */
    public function getAvailableRates(string $city, float $discountedSubtotal, string $country = 'Bangladesh'): Collection
    {
        $zone = ShippingZone::findForCity($city, $country);

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
    public function validateAndGetRate(int $rateId, string $city, string $country = 'Bangladesh'): ShippingRate
    {
        $rate = ShippingRate::with('shippingMethod')->find($rateId);

        if (! $rate) {
            throw new \InvalidArgumentException('Selected shipping rate is invalid.');
        }

        if (! $rate->shippingMethod->is_active) {
            throw new \InvalidArgumentException('Selected shipping method is no longer available.');
        }

        $zone = ShippingZone::findForCity($city, $country);

        if (! $zone || $rate->shipping_zone_id !== $zone->id) {
            throw new \InvalidArgumentException('Selected shipping rate is not available for your city.');
        }

        return $rate;
    }

    public function calculateShippingCost(ShippingRate $rate, float $discountedSubtotal): float
    {
        return $rate->getShippingCost($discountedSubtotal);
    }
}
