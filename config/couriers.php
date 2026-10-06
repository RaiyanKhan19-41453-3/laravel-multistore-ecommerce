<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Courier Catalog (code-managed)
    |--------------------------------------------------------------------------
    | Couriers are defined here, not in the database: names, order, on/off
    | switches, API credentials, and webhook secrets via env(). Shipment
    | history rows keep working because they snapshot courier code + name.
    */

    'pathao' => [
        'name' => 'Pathao Courier',
        'enabled' => env('PATHAO_ENABLED', true),
        'sort_order' => 1,
        'settings' => [
            'client_id' => env('PATHAO_CLIENT_ID'),
            'client_secret' => env('PATHAO_CLIENT_SECRET'),
            'username' => env('PATHAO_USERNAME'),
            'password' => env('PATHAO_PASSWORD'),
            'store_id' => env('PATHAO_STORE_ID'),
            'sandbox' => env('PATHAO_SANDBOX', true),
        ],
        'webhook_secret' => env('PATHAO_WEBHOOK_SECRET'),
    ],

    'paperfly' => [
        'name' => 'Paperfly',
        'enabled' => env('PAPERFLY_ENABLED', true),
        'sort_order' => 2,
        'settings' => [
            'merchant_id' => env('PAPERFLY_MERCHANT_ID'),
            'username' => env('PAPERFLY_USERNAME'),
            'password' => env('PAPERFLY_PASSWORD'),
        ],
        'webhook_secret' => env('PAPERFLY_WEBHOOK_SECRET'),
    ],

    'sa_paribahan' => [
        'name' => 'SA Paribahan',
        'enabled' => env('SA_PARIBAHAN_ENABLED', true),
        'sort_order' => 3,
        'settings' => [
            'api_key' => env('SA_PARIBAHAN_API_KEY'),
            'booking_branch' => env('SA_PARIBAHAN_BOOKING_BRANCH'),
        ],
        'webhook_secret' => env('SA_PARIBAHAN_WEBHOOK_SECRET'),
    ],

    'sundarban' => [
        'name' => 'Sundarban',
        'enabled' => env('SUNDARBAN_ENABLED', true),
        'sort_order' => 4,
        'settings' => [
            'api_key' => env('SUNDARBAN_API_KEY'),
            'booking_user_id' => env('SUNDARBAN_BOOKING_USER_ID'),
        ],
        'webhook_secret' => env('SUNDARBAN_WEBHOOK_SECRET'),
    ],

    'ecourier' => [
        'name' => 'Ecourier',
        'enabled' => env('ECOURIER_ENABLED', true),
        'sort_order' => 5,
        'settings' => [
            'user_id' => env('ECOURIER_USER_ID'),
            'api_key' => env('ECOURIER_API_KEY'),
        ],
        'webhook_secret' => env('ECOURIER_WEBHOOK_SECRET'),
    ],

    'steadfast' => [
        'name' => 'Steadfast',
        'enabled' => env('STEADFAST_ENABLED', true),
        'sort_order' => 6,
        'settings' => [
            'api_key' => env('STEADFAST_API_KEY'),
            'secret_key' => env('STEADFAST_SECRET_KEY'),
        ],
        'webhook_secret' => env('STEADFAST_WEBHOOK_SECRET'),
    ],

    'redx' => [
        'name' => 'RedX',
        'enabled' => env('REDX_ENABLED', true),
        'sort_order' => 7,
        'settings' => [
            'api_token' => env('REDX_API_TOKEN'),
            'sandbox' => env('REDX_SANDBOX', true),
        ],
        'webhook_secret' => env('REDX_WEBHOOK_SECRET'),
    ],

    'smsa' => [
        'name' => 'SMSA Express',
        'enabled' => env('SMSA_ENABLED', true),
        'sort_order' => 8,
        'settings' => [
            'api_key' => env('SMSA_API_KEY'),
            'base_url' => env('SMSA_BASE_URL'),
            'sandbox' => env('SMSA_SANDBOX', false),
        ],
        'webhook_secret' => env('SMSA_WEBHOOK_SECRET'),
    ],

    'aramex' => [
        'name' => 'Aramex',
        'enabled' => env('ARAMEX_ENABLED', true),
        'sort_order' => 9,
        'settings' => [
            'account_number' => env('ARAMEX_ACCOUNT_NUMBER'),
            'username' => env('ARAMEX_USERNAME'),
            'password' => env('ARAMEX_PASSWORD'),
            'account_pin' => env('ARAMEX_ACCOUNT_PIN'),
            'base_url' => env('ARAMEX_BASE_URL'),
        ],
        'webhook_secret' => env('ARAMEX_WEBHOOK_SECRET'),
    ],

    'other' => [
        'name' => 'Other',
        'enabled' => env('OTHER_COURIER_ENABLED', true),
        'sort_order' => 99,
        'settings' => [],
        'webhook_secret' => null,
    ],

];
