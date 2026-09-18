<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Master switches for customer notifications. Each can be overridden at
    | runtime from the admin settings screen (notifications.mail_enabled and
    | notifications.sms_enabled). SMS stays off by default: it costs money
    | per message and needs a configured gateway.
    |
    */
    'mail_enabled' => env('NOTIFICATIONS_MAIL_ENABLED', true),

    'sms_enabled' => env('NOTIFICATIONS_SMS_ENABLED', false),
];
