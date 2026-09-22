<?php

/**
 * Authoritative RMSignage billing plan catalog.
 *
 * Commercial structure (overrides older per-Screen-only Figma amounts):
 * - Starter: £19/mo · £15/mo annual equivalent (£180/yr) · 5 Screens · 200 GB · 5 seats
 * - Business: £49/mo · £39/mo annual equivalent (£468/yr) · 20 Screens · 500 GB · 15 seats
 * - Enterprise: Custom · Contact Sales
 *
 * Pro (£99) is intentionally absent — do not reintroduce.
 */

return [

    'default_plan' => 'starter',

    'plans' => [

        'starter' => [
            'key' => 'starter',
            'name' => 'Starter',
            'tagline' => 'For growing venues that need the full product toolkit.',
            'monthly_amount' => (int) env('BILLING_STARTER_MONTHLY_AMOUNT', 1900),
            'yearly_amount' => (int) env('BILLING_STARTER_YEARLY_AMOUNT', 18000), // £15/mo × 12
            'yearly_monthly_equivalent' => (int) env('BILLING_STARTER_YEARLY_EQ', 1500),
            'prices' => [
                'monthly' => env('DZ_SIGNAGE_STARTER_MONTHLY_PRICE_ID', env('DZ_SIGNAGE_MONTHLY_PRICE_ID')),
                'yearly' => env('DZ_SIGNAGE_STARTER_YEARLY_PRICE_ID', env('DZ_SIGNAGE_YEARLY_PRICE_ID')),
            ],
            'screen_limit' => 5,
            'storage_gb' => 200,
            'team_limit' => 5,
            'features' => [
                'full_template_library',
                'brand_kit',
                'images_videos',
                'playlists',
                'advanced_scheduling',
                'locations',
                'priority_email_support',
                'basic_analytics',
            ],
            'feature_labels' => [
                '5 connected Screens',
                'Full template library',
                'Brand Kit',
                'Images & videos',
                'Playlists',
                'Advanced scheduling',
                'Locations & Screen groups',
                '200 GB storage',
                '5 team members',
                'Priority email support',
            ],
            'popular' => false,
            'enterprise' => false,
            'active' => true,
            'sort_order' => 1,
            'cta' => 'Get Started',
        ],

        'business' => [
            'key' => 'business',
            'name' => 'Business',
            'tagline' => 'For multi-site operators who need deeper insight and scale.',
            'monthly_amount' => (int) env('BILLING_BUSINESS_MONTHLY_AMOUNT', 4900),
            'yearly_amount' => (int) env('BILLING_BUSINESS_YEARLY_AMOUNT', 46800), // £39/mo × 12
            'yearly_monthly_equivalent' => (int) env('BILLING_BUSINESS_YEARLY_EQ', 3900),
            'prices' => [
                'monthly' => env('DZ_SIGNAGE_BUSINESS_MONTHLY_PRICE_ID'),
                'yearly' => env('DZ_SIGNAGE_BUSINESS_YEARLY_PRICE_ID'),
            ],
            'screen_limit' => 20,
            'storage_gb' => 500,
            'team_limit' => 15,
            'features' => [
                'full_template_library',
                'premium_templates',
                'brand_kit',
                'images_videos',
                'playlists',
                'advanced_scheduling',
                'locations',
                'advanced_analytics',
                'priority_support',
                'custom_branding',
            ],
            'feature_labels' => [
                '20 connected Screens',
                'Full & premium Templates',
                'Brand Kit',
                'Advanced scheduling',
                'Locations & Screen groups',
                'Advanced Analytics',
                '500 GB storage',
                '15 team members',
                'Priority support',
                'Custom branding',
            ],
            'popular' => true,
            'enterprise' => false,
            'active' => true,
            'sort_order' => 2,
            'cta' => 'Get Started',
        ],

        'enterprise' => [
            'key' => 'enterprise',
            'name' => 'Enterprise',
            'tagline' => 'Custom estates, SLAs, and dedicated success.',
            'monthly_amount' => null,
            'yearly_amount' => null,
            'yearly_monthly_equivalent' => null,
            'prices' => [
                'monthly' => null,
                'yearly' => null,
            ],
            'screen_limit' => null,
            'storage_gb' => null,
            'team_limit' => null,
            'features' => [
                'unlimited_screens',
                'custom_storage',
                'dedicated_account_manager',
                'custom_onboarding',
                'advanced_permissions',
                'sla',
                'custom_pricing',
                'all_business_features',
            ],
            'feature_labels' => [
                'Custom / unlimited Screens',
                'Custom storage',
                'Dedicated account manager',
                'Custom onboarding',
                'Advanced permissions',
                'SLA',
                'Everything in Business',
                'Custom pricing',
            ],
            'popular' => false,
            'enterprise' => true,
            'active' => true,
            'sort_order' => 3,
            'cta' => 'Contact Sales',
        ],

    ],

    /*
    | Comparison matrix rows for marketing / Billing UI.
    | Values: string labels or bool.
    */
    'comparison' => [
        ['feature' => 'Connected Screens', 'starter' => '5', 'business' => '20', 'enterprise' => 'Custom'],
        ['feature' => 'Storage', 'starter' => '200 GB', 'business' => '500 GB', 'enterprise' => 'Custom'],
        ['feature' => 'Team members', 'starter' => '5', 'business' => '15', 'enterprise' => 'Custom'],
        ['feature' => 'Template library', 'starter' => true, 'business' => true, 'enterprise' => true],
        ['feature' => 'Brand Kit', 'starter' => true, 'business' => true, 'enterprise' => true],
        ['feature' => 'Playlists & Schedules', 'starter' => true, 'business' => true, 'enterprise' => true],
        ['feature' => 'Locations', 'starter' => true, 'business' => true, 'enterprise' => true],
        ['feature' => 'Basic Analytics', 'starter' => true, 'business' => true, 'enterprise' => true],
        ['feature' => 'Advanced Analytics', 'starter' => false, 'business' => true, 'enterprise' => true],
        ['feature' => 'Priority support', 'starter' => 'Email', 'business' => 'Priority', 'enterprise' => 'Dedicated'],
        ['feature' => 'SLA & custom onboarding', 'starter' => false, 'business' => false, 'enterprise' => true],
    ],

];
