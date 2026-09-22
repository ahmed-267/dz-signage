<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Player heartbeat
    |--------------------------------------------------------------------------
    |
    | The Player posts an authenticated heartbeat on this interval. Screens are
    | considered Online when last_seen_at is within online_threshold_seconds.
    |
    */
    'heartbeat_interval_seconds' => (int) env('SCREEN_HEARTBEAT_INTERVAL', 45),

    'online_threshold_seconds' => (int) env('SCREEN_ONLINE_THRESHOLD', 90),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat history retention
    |--------------------------------------------------------------------------
    |
    | Phase 6 keeps recent heartbeat rows for troubleshooting, then prunes via
    | `screens:prune-heartbeats` (scheduled daily). Current Online/Offline uses
    | screen_devices.last_seen_at — not a scan of the history table.
    |
    */
    'heartbeat_retention_days' => (int) env('SCREEN_HEARTBEAT_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Player build identifier
    |--------------------------------------------------------------------------
    */
    'player_version' => (string) env('DZ_PLAYER_VERSION', '1.0.0'),
];
