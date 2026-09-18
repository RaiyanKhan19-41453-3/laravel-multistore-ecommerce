<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default' => env('PAYMENT_GATEWAY', 'sslcommerz'),

    'verify_webhooks' => env('VERIFY_PAYMENT_WEBHOOKS', true),

    /*
    |--------------------------------------------------------------------------
    | Order Reservation TTL (minutes)
    |--------------------------------------------------------------------------
    | How long a pending order holds inventory before expiring.
    | Different gateways may override this via payment_method_ttl.
    */
    'reservation_ttl_minutes' => (int) env('ORDER_RESERVATION_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Payment Method TTL Overrides
    |--------------------------------------------------------------------------
    | Per-method reservation timeouts in minutes. Null uses the default.
    */
    'payment_method_ttl' => [
        'bkash' => 15,
        'nagad' => 15,
        'rocket' => 15,
        'moyasar' => 20,
        'tabby' => 30,
        'stripe' => 30,
        'card' => 20,
        'cod' => null, // COD never expires
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Payment Gateways
    |--------------------------------------------------------------------------
    | Set to false to disable a gateway. Disabled gateways won't appear
    | in checkout or accept payments.
    */
    'enabled' => [
        'cod' => env('PAYMENT_COD_ENABLED', true),
        'sslcommerz' => env('PAYMENT_SSLCOMMERZ_ENABLED', true),
        'bkash' => env('PAYMENT_BKASH_ENABLED', false),
        'nagad' => env('PAYMENT_NAGAD_ENABLED', false),
        'rocket' => env('PAYMENT_ROCKET_ENABLED', false),
        'moyasar' => env('PAYMENT_MOYASAR_ENABLED', false),
        'tabby' => env('PAYMENT_TABBY_ENABLED', false),
        'stripe' => env('PAYMENT_STRIPE_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Configurations
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'sslcommerz' => [
            'store_id' => env('SSLCOMMERZ_STORE_ID'),
            'store_password' => env('SSLCOMMERZ_STORE_PASSWORD'),
            'sandbox' => env('SSLCOMMERZ_SANDBOX', true),
        ],
        'bkash' => [
            'app_key' => env('BKASH_APP_KEY'),
            'app_secret' => env('BKASH_APP_SECRET'),
            'username' => env('BKASH_USERNAME'),
            'password' => env('BKASH_PASSWORD'),
            'sandbox' => env('BKASH_SANDBOX', true),
        ],
        'moyasar' => [
            'api_key' => env('MOYASAR_API_KEY'),
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
            'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com'),
        ],
        'tabby' => [
            'public_key' => env('TABBY_PUBLIC_KEY'),
            'secret_key' => env('TABBY_SECRET_KEY'),
            'merchant_code' => env('TABBY_MERCHANT_CODE'),
            'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
        ],
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'base_url' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
        ],
    ],
];
