<?php

/**
 * AI content generation (Phase 14 + premium quality pipeline).
 *
 * Keys stay server-side. Frontend only receives availability + public options
 * via Inertia shared props / controller payloads — never API secrets.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Master switch (also gated by FeatureFlag `ai_content_generation`)
    |--------------------------------------------------------------------------
    */
    'enabled' => (bool) env('AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Quality mode: premium | standard
    |--------------------------------------------------------------------------
    */
    'quality_mode' => env('AI_QUALITY_MODE', 'premium'),

    /*
    |--------------------------------------------------------------------------
    | Provider: fake | openai_compatible
    |--------------------------------------------------------------------------
    |
    | `fake` returns deterministic local results (tests + local without keys).
    | `openai_compatible` talks to OpenAI or any OpenAI-compatible HTTP API.
    |
    */
    'provider' => env('AI_PROVIDER', 'fake'),

    'openai' => [
        'api_key' => env('AI_OPENAI_API_KEY', env('OPENAI_API_KEY')),
        'base_url' => rtrim((string) env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'organization' => env('AI_OPENAI_ORGANIZATION'),
        'timeout' => (int) env('AI_HTTP_TIMEOUT', 60),
        'text_model' => env('AI_TEXT_MODEL', 'gpt-4o'),
        'image_model' => env('AI_IMAGE_MODEL', 'gpt-image-1'),
        'design_model' => env('AI_DESIGN_MODEL', env('AI_TEXT_MODEL', 'gpt-4o')),
    ],

    'limits' => [
        'prompt_max' => (int) env('AI_PROMPT_MAX', 2000),
        'text_max_chars' => (int) env('AI_TEXT_MAX_CHARS', 400),
        'per_user_per_minute' => (int) env('AI_RATE_PER_USER', 10),
        'per_workspace_per_hour' => (int) env('AI_RATE_PER_WORKSPACE', 60),
        'image_max_bytes' => (int) env('AI_IMAGE_MAX_BYTES', 10 * 1024 * 1024),
        'text_variants_max' => 3,
        'image_variants_max' => 3,
        'design_concepts_max' => 3,
    ],

    'temp' => [
        'disk' => env('AI_TEMP_DISK', env('MEDIA_DISK', 'public')),
        'retention_hours' => (int) env('AI_TEMP_RETENTION_HOURS', 24),
        'history_days' => (int) env('AI_HISTORY_DAYS', 30),
    ],

    'image' => [
        'default_aspect' => 'landscape',
        'default_variants' => (int) env('AI_IMAGE_VARIANTS', 2),
        'no_text_instruction' => 'Absolutely no text, letters, words, numbers, logos, watermarks, signatures, or typography of any kind in the image. Leave clean negative space suitable for overlaying signage copy.',
        'aspects' => [
            'landscape' => ['width' => 1920, 'height' => 1080, 'label' => 'Landscape 16:9'],
            'portrait' => ['width' => 1080, 'height' => 1920, 'label' => 'Portrait 9:16'],
            'square' => ['width' => 1080, 'height' => 1080, 'label' => 'Square 1:1'],
        ],
        'styles' => [
            'professional' => 'Professional digital signage, clean composition, high contrast',
            'minimal' => 'Minimal, lots of negative space, restrained palette',
            'vibrant' => 'Vibrant colours, energetic, bold shapes',
            'elegant' => 'Elegant, premium lighting, refined atmosphere',
            'photographic' => 'Photorealistic photography, natural lighting',
            'illustration' => 'Flat illustration, modern graphic design',
        ],
        'refinements' => [
            'more_minimal' => 'Simplify further: more negative space, fewer visual elements, quieter palette.',
            'brighter' => 'Brighten the scene: higher key lighting, fresher colours, airy atmosphere.',
            'darker' => 'Darker mood: deeper shadows, richer contrast, cinematic low-key lighting.',
            'more_contrast' => 'Increase contrast and visual punch for distant TV readability.',
            'warmer' => 'Warmer colour temperature and inviting ambience.',
            'cooler' => 'Cooler, crisp colour temperature and modern feel.',
        ],
    ],

    'text' => [
        // Premium modes (preferred). Legacy purposes still accepted and mapped.
        'modes' => [
            'headline',
            'promotion',
            'cta',
            'announcement',
            'event',
            'menu_product',
            'welcome',
            'information',
        ],
        'purposes' => [
            'promotion',
            'announcement',
            'event',
            'product',
            'welcome',
            'general',
        ],
        'purpose_to_mode' => [
            'promotion' => 'promotion',
            'announcement' => 'announcement',
            'event' => 'event',
            'product' => 'menu_product',
            'welcome' => 'welcome',
            'general' => 'information',
        ],
        'tones' => [
            'professional',
            'friendly',
            'bold',
            'minimal',
            'elegant',
        ],
        'lengths' => [
            'short',
            'medium',
            'detailed',
        ],
        'rewrite_actions' => [
            'shorten',
            'expand',
            'make_professional',
            'make_friendlier',
            'make_promotional',
            'simplify',
            'fix_grammar',
        ],
        'default_variants' => 3,
    ],

    'design' => [
        'purposes' => [
            'promotion',
            'menu',
            'announcement',
            'welcome',
            'event',
            'information',
        ],
        'styles' => [
            'minimal',
            'professional',
            'bold',
            'elegant',
            'vibrant',
        ],
        'archetypes' => [
            'hero_product',
            'split_layout',
            'editorial',
            'information_board',
            'event',
            'menu',
            'full_bleed',
            'welcome',
        ],
        'purpose_to_archetype' => [
            'promotion' => 'hero_product',
            'menu' => 'menu',
            'announcement' => 'information_board',
            'welcome' => 'welcome',
            'event' => 'event',
            'information' => 'editorial',
        ],
        // Widgets AI may place — no external URL widgets.
        'allowed_widgets' => [
            'clock',
            'countdown',
            'alert',
            'info_card',
        ],
        'default_concepts' => 3,
        'min_headline_font' => 48,
        'min_body_font' => 28,
        'min_margin' => 48,
        'max_text_chars' => 120,
        'min_elements' => 3,
        'max_elements' => 14,
        'max_overlap_ratio' => 0.15,
        // Soft warning when total element area / canvas is below this (optional sparse check).
        'min_fill_ratio' => 0.08,
        'safe_fonts' => [
            'Outfit',
            'Georgia',
            'system-ui',
            'ui-sans-serif',
            'ui-serif',
            'ui-monospace',
            'JetBrains Mono',
            'Inter',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Signage agent — propose / confirm multi-step plans (no auto-publish)
    |--------------------------------------------------------------------------
    */
    'agent' => [
        'media_min_score' => 0.25,
        'template_min_score' => 0.35,
        'template_confidence_min' => 0.55,
        'default_orientation' => 'landscape',
        'default_purpose' => 'promotion',
        'default_style' => 'professional',
        'default_playlist_item_duration' => 10,
        'intents' => [
            'design',
            'playlist',
            'schedule',
            'compound',
        ],
        // Keyword hints for the deterministic classifier (tests + reliability).
        'intent_keywords' => [
            'playlist' => ['playlist', 'loop', 'rotate', 'sequence', 'carousel', 'slideshow'],
            'schedule' => [
                'schedule', 'scheduled', 'scheduling', 'weekdays', 'weekday',
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
                'saturday', 'sunday', 'daily', 'every day', 'time window',
            ],
            'design' => [
                'design', 'screen', 'poster', 'promo', 'banner', 'menu', 'welcome',
                'announcement', 'event', 'board', 'layout',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Video — architecture reserved; not implemented in Phase 14
    |--------------------------------------------------------------------------
    */
    'video' => [
        'enabled' => false,
    ],
];
