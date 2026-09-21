<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Store Identity (per-client)
    |--------------------------------------------------------------------------
    | Admin Settings UI overrides these env defaults via the settings table,
    | so one codebase can serve BD (BDT/en) or SA (SAR/ar) deployments.
    */
    'name' => env('STORE_NAME', 'My Store'),

    'country' => env('STORE_COUNTRY', 'BD'),

    'currency' => env('STORE_CURRENCY', 'BDT'),

    'locale' => env('STORE_LOCALE', env('APP_LOCALE', 'en')),

    'timezone' => env('STORE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Dhaka')),

];
