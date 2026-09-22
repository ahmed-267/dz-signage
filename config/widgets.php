<?php

return [
    'weather' => [
        'cache_ttl_seconds' => (int) env('WIDGET_WEATHER_CACHE_TTL', 900), // 15 min
        'geocode_cache_ttl_seconds' => (int) env('WIDGET_WEATHER_GEOCODE_TTL', 86400),
        'http_timeout' => (int) env('WIDGET_HTTP_TIMEOUT', 8),
    ],
    'rss' => [
        'cache_ttl_seconds' => (int) env('WIDGET_RSS_CACHE_TTL', 600), // 10 min
        'http_timeout' => (int) env('WIDGET_HTTP_TIMEOUT', 8),
        'max_bytes' => (int) env('WIDGET_RSS_MAX_BYTES', 524288), // 512KB
        'max_items' => 20,
    ],
    'calendar' => [
        'cache_ttl_seconds' => (int) env('WIDGET_CALENDAR_CACHE_TTL', 600),
        'http_timeout' => (int) env('WIDGET_HTTP_TIMEOUT', 8),
        'max_bytes' => (int) env('WIDGET_CALENDAR_MAX_BYTES', 524288),
        'max_events' => 20,
    ],
    'embed' => [
        /**
         * Allow generic HTTPS websites as iframe embeds (still subject to
         * X-Frame-Options / CSP frame-ancestors on the target). YouTube/Vimeo
         * / HLS / MP4 always supported. Known blocked news hosts are rejected
         * with a clear message pointing at the News widget.
         */
        'allow_generic_websites' => (bool) env('WIDGET_EMBED_ALLOW_WEBSITES', true),
        'known_blocked_hosts' => [
            'www.bbc.co.uk',
            'bbc.co.uk',
            'www.bbc.com',
            'bbc.com',
            'www.cnn.com',
            'cnn.com',
            'www.nytimes.com',
            'nytimes.com',
            'www.theguardian.com',
            'theguardian.com',
        ],
        // Legacy allowlist retained for docs; resolution is kind-based now.
        'allowed_hosts' => [
            'www.youtube.com',
            'youtube.com',
            'youtu.be',
            'www.youtube-nocookie.com',
            'm.youtube.com',
            'player.vimeo.com',
            'vimeo.com',
            'www.vimeo.com',
        ],
    ],
];
