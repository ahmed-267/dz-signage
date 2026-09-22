<?php

return [
    // Priority 1–10; 10 = highest. New schedules default to mid-range 5.
    'default_priority' => 5,
    'min_priority' => 1,
    'max_priority' => 10,

    // Draft defaults for a new schedule. Read them through
    // App\Support\Schedules\ScheduleDefaults — never duplicate the values.
    'default_start_time' => '09:00',
    'default_end_time' => '17:00',
    'default_days_of_week' => [1, 2, 3, 4, 5, 6, 7],
];
