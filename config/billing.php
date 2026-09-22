<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing enforcement
    |--------------------------------------------------------------------------
    |
    | When false (default for local/test), pairing and publishing work without
    | a subscription. Set BILLING_ENFORCE=true in production when Stripe is
    | configured and you want Screen licences enforced.
    |
    */

    'enforce' => (bool) env('BILLING_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs (per-screen licence)
    |--------------------------------------------------------------------------
    */

    'prices' => [
        'monthly' => env('DZ_SIGNAGE_MONTHLY_PRICE_ID'),
        'yearly' => env('DZ_SIGNAGE_YEARLY_PRICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalog display (Figma commercial amounts)
    |--------------------------------------------------------------------------
    |
    | Used for marketing + Billing UI when Stripe Price IDs are not configured
    | (local/dev). Live amounts still come from Stripe when configured.
    | Amounts are minor units (pence). Defaults match landing/billing tests.
    |
    */

    'catalog' => [
        'currency' => env('CASHIER_CURRENCY', 'gbp'),
        // Legacy single-price catalog fallback — mirrors Starter monthly/yearly.
        'monthly_amount' => (int) env('BILLING_CATALOG_MONTHLY_AMOUNT', 1900),
        'yearly_amount' => (int) env('BILLING_CATALOG_YEARLY_AMOUNT', 18000),
    ],

    'subscription_type' => 'default',

    'min_screen_licenses' => (int) env('BILLING_MIN_SCREEN_LICENSES', 1),

    'max_screen_licenses' => (int) env('BILLING_MAX_SCREEN_LICENSES', 500),

    /*
    |--------------------------------------------------------------------------
    | Price display cache (seconds)
    |--------------------------------------------------------------------------
    */

    'price_cache_ttl' => (int) env('BILLING_PRICE_CACHE_TTL', 3600),

];
