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
        'card' => 20,
        'cod' => null, // COD never expires
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
    ],
];
