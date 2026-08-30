<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $standard = ShippingMethod::updateOrCreate(
            ['name' => 'Standard Delivery'],
            [
                'description' => 'Regular delivery across Bangladesh',
                'estimated_days' => 5,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        $express = ShippingMethod::updateOrCreate(
            ['name' => 'Express Delivery'],
            [
                'description' => 'Faster delivery within major cities',
                'estimated_days' => 2,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $dhaka = ShippingZone::updateOrCreate(
            ['name' => 'Dhaka'],
            [
                'country' => 'Bangladesh',
                'cities' => ['Dhaka', 'Gazipur', 'Narayanganj', 'Savar'],
                'is_fallback' => false,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        ShippingZone::updateOrCreate(
            ['name' => 'Rest of Bangladesh'],
            [
                'country' => 'Bangladesh',
                'cities' => null,
                'is_fallback' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ShippingRate::updateOrCreate(
            ['shipping_method_id' => $standard->id, 'shipping_zone_id' => $dhaka->id],
            ['price' => 60, 'free_shipping_min' => 1000]
        );

        $outsideDhaka = ShippingZone::where('is_fallback', true)->first();

        if ($outsideDhaka) {
            ShippingRate::updateOrCreate(
                ['shipping_method_id' => $standard->id, 'shipping_zone_id' => $outsideDhaka->id],
                ['price' => 120, 'free_shipping_min' => 2000]
            );

            ShippingRate::updateOrCreate(
                ['shipping_method_id' => $express->id, 'shipping_zone_id' => $dhaka->id],
                ['price' => 150, 'free_shipping_min' => null]
            );

            ShippingRate::updateOrCreate(
                ['shipping_method_id' => $express->id, 'shipping_zone_id' => $outsideDhaka->id],
                ['price' => 200, 'free_shipping_min' => null]
            );
        }
    }
}
