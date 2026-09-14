<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZATCA E-Invoicing (Fatoora)
    |--------------------------------------------------------------------------
    | Leave disabled for non-Saudi stores: orders keep tax_amount = 0 and no
    | ZATCA documents are generated. Enable per Saudi deployment via env.
    */
    'enabled' => env('ZATCA_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Seller Tax Profile
    |--------------------------------------------------------------------------
    | Legal identity printed on every tax invoice. VAT number must be the
    | 15-digit TRN issued by ZATCA. Invoice generation refuses to run
    | until the profile validates.
    */
    'seller' => [
        'name_ar' => env('ZATCA_SELLER_NAME_AR', ''),
        'name_en' => env('ZATCA_SELLER_NAME', ''),
        'vat_number' => env('ZATCA_VAT_NUMBER', ''),
        'cr_number' => env('ZATCA_CR_NUMBER', ''),
        'street' => env('ZATCA_SELLER_STREET', ''),
        'building_number' => env('ZATCA_SELLER_BUILDING', ''),
        'city' => env('ZATCA_SELLER_CITY', ''),
        'postal_code' => env('ZATCA_SELLER_POSTAL', ''),
        'country' => env('ZATCA_SELLER_COUNTRY', 'SA'),
    ],

    /*
    |--------------------------------------------------------------------------
    | VAT
    |--------------------------------------------------------------------------
    | Standard Saudi rate is 15%. Zero-rated and exempt categories are
    | matched by product/category slug lists below.
    */
    'vat_rate' => (float) env('ZATCA_VAT_RATE', 15.0),
    'zero_rated_category_slugs' => array_filter(explode(',', (string) env('ZATCA_ZERO_RATED_CATEGORIES', ''))),
    'exempt_category_slugs' => array_filter(explode(',', (string) env('ZATCA_EXEMPT_CATEGORIES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Phase 2 (Integration)
    |--------------------------------------------------------------------------
    | Fatoora API endpoints and device credentials. Sandbox by default;
    | flip to production only after sandbox certification.
    */
    'sandbox' => env('ZATCA_SANDBOX', true),
    'api_base' => env('ZATCA_API_BASE', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal'),

    /*
    |--------------------------------------------------------------------------
    | Fatoora Endpoints
    |--------------------------------------------------------------------------
    | Relative to api_base. Override per environment if ZATCA versions them.
    */
    'endpoints' => [
        'compliance' => env('ZATCA_ENDPOINT_COMPLIANCE', '/compliance'),
        'production_csids' => env('ZATCA_ENDPOINT_PRODUCTION_CSIDS', '/production/csids'),
        'clearance' => env('ZATCA_ENDPOINT_CLEARANCE', '/invoices/clearance/single'),
        'reporting' => env('ZATCA_ENDPOINT_REPORTING', '/invoices/reporting/single'),
    ],
    'device' => [
        'serial' => env('ZATCA_DEVICE_SERIAL', ''),
        'csid' => env('ZATCA_CSID', ''),
        'private_key' => env('ZATCA_PRIVATE_KEY', ''),
        'certificate' => env('ZATCA_CERTIFICATE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting SLA
    |--------------------------------------------------------------------------
    | Simplified (B2C) invoices must reach Fatoora within 24 hours.
    | Jobs retry with backoff; this caps total attempts before alerting.
    */
    'reporting_max_attempts' => (int) env('ZATCA_REPORTING_MAX_ATTEMPTS', 10),
];
