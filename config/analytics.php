<?php

/**
 * Phase 15 analytics + telemetry retention.
 */
return [

    'playback_events_retention_days' => (int) env('ANALYTICS_PLAYBACK_RETENTION_DAYS', 90),

    'daily_stats_retention_days' => (int) env('ANALYTICS_DAILY_STATS_RETENTION_DAYS', 400),

    /*
    |--------------------------------------------------------------------------
    | Heartbeat sampling for availability aggregates
    |--------------------------------------------------------------------------
    |
    | Online seconds for a day are estimated as: distinct heartbeat buckets
    | (interval-sized) × heartbeat interval. This matches connectivity known
    | to RMSignage, not hardware power state.
    |
    */
    'availability_uses_heartbeat_interval' => true,

    'max_events_per_request' => 20,

    'max_date_range_days' => 92,
];
