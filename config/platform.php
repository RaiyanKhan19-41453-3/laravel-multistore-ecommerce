<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Billing
    |--------------------------------------------------------------------------
    | Subscriptions gate storefront + merchant admin access. Enforcement is
    | OFF by default so existing installs and tests keep working; flip it
    | on only after assigning active subscriptions to current stores
    | (their trial windows count from each store's creation date).
    */
    'billing' => [
        'enforced' => env('PLATFORM_BILLING_ENFORCED', false),
        'trial_days' => (int) env('PLATFORM_BILLING_TRIAL_DAYS', 14),
    ],

];
