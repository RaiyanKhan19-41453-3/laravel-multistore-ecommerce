<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Barcode Symbology
    |--------------------------------------------------------------------------
    | Code-128 encodes full alphanumeric SKUs. Only switch to EAN-13 if you
    | sell through retail chains that require registered numeric codes.
    */
    'type' => env('BARCODE_TYPE', 'code128'),

    /*
    |--------------------------------------------------------------------------
    | Safety cap for a single print run.
    |--------------------------------------------------------------------------
    */
    'max_labels' => (int) env('BARCODE_MAX_LABELS', 500),

    /*
    |--------------------------------------------------------------------------
    | Label layouts.
    |--------------------------------------------------------------------------
    | a4-*: stickers per A4 sheet (columns x rows). thermal-50x30: single
    | 50x30mm labels for XPrinter-style thermal printers, one per row.
    */
    'layouts' => [
        'a4-24' => ['label' => 'A4: 24 per sheet (3 × 8)', 'columns' => 3, 'kind' => 'a4'],
        'a4-40' => ['label' => 'A4: 40 per sheet (4 × 10)', 'columns' => 4, 'kind' => 'a4'],
        'a4-65' => ['label' => 'A4: 65 per sheet (5 × 13)', 'columns' => 5, 'kind' => 'a4'],
        'thermal-50x30' => ['label' => 'Thermal 50×30mm', 'columns' => 1, 'kind' => 'thermal'],
    ],

    'default_layout' => env('BARCODE_DEFAULT_LAYOUT', 'a4-40'),
];
