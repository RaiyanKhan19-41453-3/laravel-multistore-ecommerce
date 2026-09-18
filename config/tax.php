<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Tax (per-client)
    |--------------------------------------------------------------------------
    | mode: off | vat | gst. ZATCA e-invoicing builds on top of vat mode for
    | Saudi deployments but any client can enable a plain VAT/GST without it.
    | Admin Settings UI overrides these env defaults via the settings table.
    */
    'mode' => env('TAX_MODE', 'off'),

    'rate' => (float) env('TAX_RATE', 0),

    'inclusive' => (bool) env('TAX_INCLUSIVE', false),

    'label' => env('TAX_LABEL', 'VAT'),

    'zero_rated_category_slugs' => array_filter(explode(',', (string) env('TAX_ZERO_RATED_CATEGORIES', ''))),

    'exempt_category_slugs' => array_filter(explode(',', (string) env('TAX_EXEMPT_CATEGORIES', ''))),

];
