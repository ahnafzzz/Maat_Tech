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
            'manifest' => 'assets/models/desk-lamp/desk-lamp.4843375218151318.json',
            'poster' => 'assets/models/desk-lamp/desk-lamp-poster.webp',
            'marketing_slides' => [
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/01-workspace.webp', 'alt' => 'Desk lamp lighting a dual-screen workspace'],
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/02-focus.webp', 'alt' => 'Desk lamp illuminating a focused work surface'],
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/03-reading.webp', 'alt' => 'Desk lamp positioned over books and reading notes'],
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/04-position.webp', 'alt' => 'Desk lamp shown in reach, repositioned, and folded poses'],
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/05-light.webp', 'alt' => 'Desk lamp showing warm, neutral, and white light previews'],
                ['type' => 'landscape', 'image' => 'images/storefront/slideshow/06-clamp.webp', 'alt' => 'Desk-edge clamp preserving the work surface'],
                ['type' => 'portrait', 'image' => 'images/storefront/slideshow/07-workspace-portrait.webp', 'alt' => 'Desk lamp in a lit computer workspace', 'heading' => 'From first sketch to final review', 'copy' => 'Position the articulated arm where the task needs it, then keep the work surface open.'],
                ['type' => 'portrait', 'image' => 'images/storefront/slideshow/08-upgraded-portrait.webp', 'alt' => 'Desk lamp illuminating a laptop and monitor workspace', 'heading' => 'One lamp for changing tasks', 'copy' => 'Move between keyboard work, notes, and close-up tasks without rearranging the desk.'],
                ['type' => 'portrait', 'image' => 'images/storefront/slideshow/09-built-for-work-portrait.webp', 'alt' => 'Swing-arm desk lamp over a computer workstation', 'heading' => 'Light where the work moves', 'copy' => 'Swing the head between screens, books, and detailed work while the clamp leaves room below.'],
            ],
        ],
    ],
];
