<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Discount Combination Mode
    |--------------------------------------------------------------------------
    |
    | How multiple automatic discounts combine on one order:
    | - single_winner: highest priority discount takes the whole order.
    | - waterfall: priority-ranked discounts claim the lines they cover;
    |   uncovered lines fall through to the next discount.
    | - best_per_line: every line takes its highest-value discount.
    |
    | Coupons always stack with automatic discounts line-by-line when both
    | sides are stackable; otherwise the line takes the better deal.
    |
    */
    'combination_mode' => env('DISCOUNT_COMBINATION_MODE', 'waterfall'),
];
