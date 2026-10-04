<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline sync horizon
    |--------------------------------------------------------------------------
    |
    | How far ahead the Player offline package precomputes Schedule windows and
    | related Playlist / Media payloads. Keep this modest — TVs have limited
    | storage and long horizons waste bandwidth.
    |
    */
    'offline_horizon_hours' => (int) env('PLAYER_OFFLINE_HORIZON_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Packaged Android / Fire TV PWA (PWABuilder / Bubblewrap)
    |--------------------------------------------------------------------------
    |
    | The APK wraps the hosted `/player` PWA. It is not a native Player rewrite.
    | Digital Asset Links (`/.well-known/assetlinks.json`) must list the
    | signing-cert SHA-256 fingerprints from the Play / sideload keystore.
    |
    */
    'android' => [
        'package_name' => env('PLAYER_ANDROID_PACKAGE', 'com.rmsignage.player'),
        'sha256_cert_fingerprints' => array_values(array_filter(array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', (string) env('PLAYER_ANDROID_SHA256_FINGERPRINTS', '')),
        ))),
    ],

];
