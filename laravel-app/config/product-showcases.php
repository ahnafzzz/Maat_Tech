<?php

$lampProductSlug = env('FEATURED_LAMP_PRODUCT_SLUG', 'series-x-articulated-lamp');

return [
    /*
    |--------------------------------------------------------------------------
    | Explicit product-to-model associations
    |--------------------------------------------------------------------------
    |
    | The source viewer and the catalog record still require final business
    | identity approval. Override FEATURED_LAMP_PRODUCT_SLUG if the canonical
    | published product uses another slug, or set it to an empty value to use
    | ordinary product images only. No positional/first-product matching occurs.
    |
    */
    'products' => $lampProductSlug === '' ? [] : [
        $lampProductSlug => [
            'model_id' => 'maat-led-swing-arm-desk-lamp-v1',
            'manifest' => 'assets/models/desk-lamp/desk-lamp.de824f25cf33f9e6.json',
            'poster' => 'assets/models/desk-lamp/desk-lamp-poster.webp',
        ],
    ],
];
