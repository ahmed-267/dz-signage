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

];
