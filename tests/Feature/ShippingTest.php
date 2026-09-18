<?php

namespace Tests\Feature;

use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    public function test_api_returns_shipping_rates_for_city(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka', 'Gazipur'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60, 'free_shipping_min' => 1000]);

        $response = $this->getJson('/api/shipping/rates?city=Dhaka&subtotal=500');

        $response->assertOk()->assertJsonPath('data.0.shipping_cost', 60);
    }

    public function test_api_returns_empty_for_unknown_city_without_fallback(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60]);

        $response = $this->getJson('/api/shipping/rates?city=Comilla&subtotal=500');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_api_uses_fallback_zone_for_unknown_city(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
        $fallback = ShippingZone::create(['name' => 'Rest of Bangladesh', 'cities' => null, 'is_fallback' => true, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $fallback->id, 'price' => 120]);

        $response = $this->getJson('/api/shipping/rates?city=Comilla&subtotal=500');

        $response->assertOk()->assertJsonPath('data.0.shipping_cost', 120);
    }

    public function test_free_shipping_when_above_min(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60, 'free_shipping_min' => 1000]);

        $response = $this->getJson('/api/shipping/rates?city=Dhaka&subtotal=1500');

        $response->assertOk()->assertJsonPath('data.0.shipping_cost', 0)->assertJsonPath('data.0.is_free', true);
    }

    public function test_inactive_method_not_returned(): void
    {
        $method = ShippingMethod::create(['name' => 'Express', 'is_active' => false]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 150]);

        $response = $this->getJson('/api/shipping/rates?city=Dhaka&subtotal=500');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_inactive_zone_not_returned(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => false]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60]);

        $response = $this->getJson('/api/shipping/rates?city=Dhaka&subtotal=500');

        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_requires_city_and_subtotal(): void
    {
        $this->getJson('/api/shipping/rates')->assertStatus(422);
        $this->getJson('/api/shipping/rates?city=Dhaka')->assertStatus(422);
        $this->getJson('/api/shipping/rates?subtotal=500')->assertStatus(422);
    }

    public function test_rate_is_case_insensitive(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Dhaka', 'cities' => ['Dhaka'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60]);

        $response = $this->getJson('/api/shipping/rates?city=dhaka&subtotal=500');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_only_one_fallback_zone_allowed(): void
    {
        ShippingZone::create(['name' => 'Rest of Bangladesh', 'country' => 'Bangladesh', 'cities' => null, 'is_fallback' => true, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->postJson('/admin/shipping/zones', [
            'name' => 'Another Fallback',
            'country' => 'Bangladesh',
            'is_fallback' => true,
            'is_active' => true,
        ]);

        $response->assertSessionHasErrors('is_fallback');
    }

    public function test_duplicate_cities_across_zones_rejected(): void
    {
        ShippingZone::create(['name' => 'Dhaka', 'country' => 'Bangladesh', 'cities' => ['Dhaka', 'Gazipur'], 'is_fallback' => false, 'is_active' => true]);

        $response = $this->actingAs($this->admin)->postJson('/admin/shipping/zones', [
            'name' => 'Dhaka Extended',
            'country' => 'Bangladesh',
            'cities' => ['Dhaka', 'Narayanganj'],
            'is_fallback' => false,
            'is_active' => true,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('cities');
    }

    public function test_api_returns_rates_for_country(): void
    {
        $method = ShippingMethod::create(['name' => 'Standard', 'is_active' => true]);
        $zone = ShippingZone::create(['name' => 'Riyadh', 'country' => 'SA', 'cities' => ['Riyadh'], 'is_fallback' => false, 'is_active' => true]);
        ShippingRate::create(['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id, 'price' => 60]);

        // Non-BD zones are unreachable unless the caller passes a country.
        $response = $this->getJson('/api/shipping/rates?city=Riyadh&subtotal=50&country=SA');

        $response->assertOk()->assertJsonPath('data.0.shipping_cost', 60);

        $response = $this->getJson('/api/shipping/rates?city=Riyadh&subtotal=50');

        $response->assertOk()->assertJsonPath('data', []);
    }
}
