<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createUser(): User
{
    return User::factory()->create();
}

function authHeaders(User $user): array
{
    $token = $user->createToken('test-token')->plainTextToken;

    return ['Authorization' => "Bearer $token"];
}

function createProduct(float $price = 500, int $stock = 50): Product
{
    $product = Product::factory()->create(['price' => $price, 'is_active' => true]);
    Inventory::factory()->forProduct($product)->withQuantity($stock)->create();

    return $product;
}

function createCartWithItem(User $user, Product $product, int $quantity = 1): Cart
{
    $cart = Cart::create([
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);

    return $cart;
}

function createTestShippingRate(): ShippingRate
{
    $method = ShippingMethod::firstOrCreate(['name' => 'Standard'], ['is_active' => true, 'estimated_days' => 5]);
    $zone = ShippingZone::firstOrCreate(['name' => 'Dhaka'], [
        'cities' => ['Dhaka'],
        'is_fallback' => false,
        'is_active' => true,
    ]);

    return ShippingRate::firstOrCreate(
        ['shipping_method_id' => $method->id, 'shipping_zone_id' => $zone->id],
        ['price' => 60, 'free_shipping_min' => null]
    );
}

function shippingData(): array
{
    $rate = createTestShippingRate();

    return [
        'shipping_name' => 'Test User',
        'phone' => '01712345678',
        'shipping_address' => '123 Test Street',
        'shipping_city' => 'Dhaka',
        'shipping_state' => 'Dhaka',
        'shipping_country' => 'Bangladesh',
        'shipping_rate_id' => $rate->id,
    ];
}
