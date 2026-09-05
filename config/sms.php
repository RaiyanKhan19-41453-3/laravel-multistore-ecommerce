<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Gateway
    |--------------------------------------------------------------------------
    */
    'default' => env('SMS_GATEWAY', 'twilio'),

    /*
    |--------------------------------------------------------------------------
    | Enabled SMS Gateways
    |--------------------------------------------------------------------------
    */
    'enabled' => [
        'twilio' => env('SMS_TWILIO_ENABLED', false),
        'ssl_wireless' => env('SMS_SSL_WIRELESS_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => (int) env('SMS_OTP_LENGTH', 6),
        'expiry_minutes' => (int) env('SMS_OTP_EXPIRY_MINUTES', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Configurations
    |--------------------------------------------------------------------------
    */
    'gateways' => [
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from_number' => env('TWILIO_FROM_NUMBER'),
        ],
        'ssl_wireless' => [
            'api_token' => env('SSL_WIRELESS_API_TOKEN'),
            'sid' => env('SSL_WIRELESS_SID'),
        ],
    ],
];
