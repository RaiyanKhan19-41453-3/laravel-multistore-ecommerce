<?php

use App\Models\Brand;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\Product;
use App\Services\DiscountService;

function createCombinerScenario(): array
{
    $nike = Brand::create(['name' => 'Nike', 'slug' => 'nike-combiner', 'is_active' => true]);

    $airMax = Product::factory()->create(['price' => 150, 'brand_id' => $nike->id]);
    $hoodie = Product::factory()->create(['price' => 65]);
    $tshirt = Product::factory()->create(['price' => 35, 'brand_id' => $nike->id]);

    $flash = Discount::factory()->percentage()->create([
        'name' => 'Combiner Flash',
        'value' => 50,
        'priority' => 20,
        'stackable' => false,
        'is_active' => true,
    ]);
    $flash->products()->attach([$hoodie->id, $tshirt->id]);

    $nikeSale = Discount::factory()->percentage()->create([
        'name' => 'Combiner Nike',
        'value' => 15,
        'priority' => 3,
        'stackable' => false,
        'is_active' => true,
    ]);
    $nikeSale->brands()->attach($nike->id);

    $items = [
        ['cart_item_id' => 1, 'product_id' => $airMax->id, 'variant_id' => null, 'total' => 150],
        ['cart_item_id' => 2, 'product_id' => $hoodie->id, 'variant_id' => null, 'total' => 65],
        ['cart_item_id' => 3, 'product_id' => $tshirt->id, 'variant_id' => null, 'total' => 35],
    ];

    return [
        'productIds' => [$airMax->id, $hoodie->id, $tshirt->id],
        'productTotals' => [$airMax->id => 150, $hoodie->id => 65, $tshirt->id => 35],
        'items' => $items,
    ];
}

it('single_winner mode takes only the top priority discount', function () {
    config(['discounts.combination_mode' => 'single_winner']);
    $scenario = createCombinerScenario();

    $result = (new DiscountService)->bestDiscountForOrder(
        250, null, $scenario['productIds'], $scenario['productTotals'], [], $scenario['items']
    );

    $this->assertNotNull($result);
    expect($result['amount'])->toBe(50.0);
    expect($result['discount']->name)->toBe('Combiner Flash');
});

it('waterfall mode fills uncovered lines with lower priority discounts', function () {
    config(['discounts.combination_mode' => 'waterfall']);
    $scenario = createCombinerScenario();

    $result = (new DiscountService)->bestDiscountForOrder(
        250, null, $scenario['productIds'], $scenario['productTotals'], [], $scenario['items']
    );

    $this->assertNotNull($result);
    expect($result['total_amount'])->toBe(72.5);
    expect($result['stacked'])->toBeTrue();

    $byName = collect($result['discounts'])->mapWithKeys(fn ($d) => [$d['discount']->name => $d['amount']]);
    expect($byName['Combiner Flash'])->toBe(50.0);
    expect($byName['Combiner Nike'])->toBe(22.5);
});

it('best_per_line mode takes the max deal on every line', function () {
    config(['discounts.combination_mode' => 'best_per_line']);
    $scenario = createCombinerScenario();

    $result = (new DiscountService)->bestDiscountForOrder(
        250, null, $scenario['productIds'], $scenario['productTotals'], [], $scenario['items']
    );

    $this->assertNotNull($result);
    expect($result['total_amount'])->toBe(72.5);
});

it('waterfall prefers priority while best_per_line prefers amount on contested lines', function () {
    $product = Product::factory()->create(['price' => 100]);

    $highPriority = Discount::factory()->percentage()->create([
        'name' => 'High Priority Small',
        'value' => 10,
        'priority' => 20,
        'stackable' => false,
        'is_active' => true,
    ]);
    $highPriority->products()->attach($product);

    $lowPriority = Discount::factory()->fixed()->create([
        'name' => 'Low Priority Big',
        'value' => 80,
        'priority' => 3,
        'stackable' => false,
        'is_active' => true,
    ]);
    $lowPriority->products()->attach($product);

    $items = [['cart_item_id' => 1, 'product_id' => $product->id, 'variant_id' => null, 'total' => 100]];

    config(['discounts.combination_mode' => 'waterfall']);
    $waterfall = (new DiscountService)->bestDiscountForOrder(
        100, null, [$product->id], [$product->id => 100], [], $items
    );
    expect($waterfall['amount'] ?? $waterfall['total_amount'])->toBe(10.0);

    config(['discounts.combination_mode' => 'best_per_line']);
    $bestLine = (new DiscountService)->bestDiscountForOrder(
        100, null, [$product->id], [$product->id => 100], [], $items
    );
    expect($bestLine['amount'] ?? $bestLine['total_amount'])->toBe(80.0);
});

it('rejects unknown combination modes', function () {
    config(['discounts.combination_mode' => 'bogus']);

    $service = new DiscountService;

    expect(fn () => $service->bestDiscountForOrder(100))->toThrow(InvalidArgumentException::class);
});

function createStackableCouponScenario(string $suffix = ''): array
{
    $product = Product::factory()->create(['price' => 1000]);

    $auto = Discount::factory()->percentage()->create([
        'name' => 'Stackable Auto '.$suffix,
        'value' => 10,
        'priority' => 5,
        'stackable' => true,
        'is_active' => true,
    ]);
    $auto->products()->attach($product);

    $couponDiscount = Discount::factory()->fixed()->create([
        'name' => 'Stackable Coupon Discount '.$suffix,
        'value' => 50,
        'stackable' => true,
        'is_active' => true,
    ]);
    $couponDiscount->products()->attach($product);
    Coupon::factory()->for($couponDiscount)->create([
        'code' => 'STACKDETAIL_'.strtoupper($suffix ?: 'X'),
        'is_active' => true,
    ]);

    return [
        'productIds' => [$product->id],
        'productTotals' => [$product->id => 1000],
        'items' => [
            ['cart_item_id' => 1, 'product_id' => $product->id, 'variant_id' => null, 'total' => 1000],
        ],
    ];
}

it('stacks coupon with auto in every mode when fully detailed', function () {
    foreach (['single_winner', 'waterfall', 'best_per_line'] as $mode) {
        config(['discounts.combination_mode' => $mode]);
        $scenario = createStackableCouponScenario($mode);

        $result = (new DiscountService)->bestDiscountForOrder(
            1000, 'STACKDETAIL_'.strtoupper($mode), $scenario['productIds'], $scenario['productTotals'], [], $scenario['items']
        );

        $this->assertNotNull($result);
        expect($result['stacked'] ?? false)->toBeTrue();
        expect($result['total_amount'] ?? $result['amount'])->toBe(150.0);
    }
});

it('picks max on contested coupon line when not stackable in every mode', function () {
    foreach (['single_winner', 'waterfall', 'best_per_line'] as $mode) {
        config(['discounts.combination_mode' => $mode]);

        $product = Product::factory()->create(['price' => 1000]);

        $auto = Discount::factory()->percentage()->create([
            'value' => 10,
            'priority' => 5,
            'stackable' => true,
            'is_active' => true,
        ]);
        $auto->products()->attach($product);

        $couponDiscount = Discount::factory()->fixed()->create([
            'value' => 250,
            'stackable' => false,
            'is_active' => true,
        ]);
        $couponDiscount->products()->attach($product);
        Coupon::factory()->for($couponDiscount)->create([
            'code' => 'BIGDETAIL_'.strtoupper($mode),
            'is_active' => true,
        ]);

        $result = (new DiscountService)->bestDiscountForOrder(
            1000,
            'BIGDETAIL_'.strtoupper($mode),
            [$product->id],
            [$product->id => 1000],
            [],
            [['cart_item_id' => 1, 'product_id' => $product->id, 'variant_id' => null, 'total' => 1000]]
        );

        $this->assertNotNull($result);
        expect($result['stacked'] ?? false)->toBeFalse();
        expect($result['amount'] ?? $result['total_amount'])->toBe(250.0);
    }
});

it('splits lines between auto winner and coupon in per-line modes', function () {
    foreach (['waterfall', 'best_per_line'] as $mode) {
        config(['discounts.combination_mode' => $mode]);

        $productA = Product::factory()->create(['price' => 1000]);
        $productB = Product::factory()->create(['price' => 500]);

        $auto = Discount::factory()->percentage()->create([
            'value' => 10,
            'priority' => 5,
            'stackable' => true,
            'is_active' => true,
        ]);
        $auto->products()->attach($productA);

        $couponDiscount = Discount::factory()->fixed()->create([
            'value' => 60,
            'stackable' => false,
            'is_active' => true,
        ]);
        $couponDiscount->products()->attach($productB);
        Coupon::factory()->for($couponDiscount)->create([
            'code' => 'SPLIT_'.strtoupper($mode),
            'is_active' => true,
        ]);

        $items = [
            ['cart_item_id' => 1, 'product_id' => $productA->id, 'variant_id' => null, 'total' => 1000],
            ['cart_item_id' => 2, 'product_id' => $productB->id, 'variant_id' => null, 'total' => 500],
        ];

        $result = (new DiscountService)->bestDiscountForOrder(
            1500,
            'SPLIT_'.strtoupper($mode),
            [$productA->id, $productB->id],
            [$productA->id => 1000, $productB->id => 500],
            [],
            $items
        );

        $this->assertNotNull($result);
        expect($result['stacked'] ?? false)->toBeTrue();
        expect($result['total_amount'] ?? $result['amount'])->toBe(160.0);

        $byId = collect($result['discounts'])->mapWithKeys(fn ($d) => [$d['discount']->id => $d['amount']]);
        expect($byId[$auto->id])->toBe(100.0);
        expect($byId[$couponDiscount->id])->toBe(60.0);
    }
});

it('single_winner cannot split one auto and one coupon across lines', function () {
    config(['discounts.combination_mode' => 'single_winner']);

    $productA = Product::factory()->create(['price' => 1000]);
    $productB = Product::factory()->create(['price' => 500]);

    $auto = Discount::factory()->percentage()->create([
        'value' => 10,
        'priority' => 5,
        'stackable' => true,
        'is_active' => true,
    ]);
    $auto->products()->attach($productA);

    $couponDiscount = Discount::factory()->fixed()->create([
        'value' => 60,
        'stackable' => false,
        'is_active' => true,
    ]);
    $couponDiscount->products()->attach($productB);
    Coupon::factory()->for($couponDiscount)->create([
        'code' => 'SPLIT_SINGLE',
        'is_active' => true,
    ]);

    $items = [
        ['cart_item_id' => 1, 'product_id' => $productA->id, 'variant_id' => null, 'total' => 1000],
        ['cart_item_id' => 2, 'product_id' => $productB->id, 'variant_id' => null, 'total' => 500],
    ];

    $result = (new DiscountService)->bestDiscountForOrder(
        1500,
        'SPLIT_SINGLE',
        [$productA->id, $productB->id],
        [$productA->id => 1000, $productB->id => 500],
        [],
        $items
    );

    $this->assertNotNull($result);
    expect($result['stacked'] ?? false)->toBeFalse();
    expect($result['amount'] ?? $result['total_amount'])->toBe(100.0);
    expect($result['discount']->id)->toBe($auto->id);
});
