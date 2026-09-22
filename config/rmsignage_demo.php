<?php

/**
 * Production-safe RMSignage demo account (North & Bean Café).
 *
 * Credentials come from environment — never hardcode the password.
 * Seeding: php artisan rmsignage:seed-demo-account --allow-production
 */

return [

    'email' => env('RMSIGNAGE_DEMO_EMAIL', 'demo@rmsignage.com'),

    /**
     * Password is read only by the seed command (never logged).
     * Use env('RMSIGNAGE_DEMO_PASSWORD') at call sites — do not put it here.
     */
    'password_env' => 'RMSIGNAGE_DEMO_PASSWORD',

    'workspace_slug' => 'rmsignage-demo-north-bean',

    'workspace_name' => 'North & Bean Café',

    'seed_tag' => 'rmsignage-production-demo',

    /**
     * How often the demo-only telemetry refresher may run (scheduler).
     * Only touches the workspace identified by workspace_slug.
     */
    'telemetry_refresh_minutes' => 2,

];
