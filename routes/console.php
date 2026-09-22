<?php

use App\Console\Commands\AggregateScreenDailyStats;
use App\Console\Commands\PruneAiGenerations;
use App\Console\Commands\PruneAnalyticsData;
use App\Console\Commands\PruneScreenHeartbeats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(PruneScreenHeartbeats::class)->daily()->at('03:20');
Schedule::command(PruneAiGenerations::class)->daily()->at('03:40');
Schedule::command(AggregateScreenDailyStats::class)->daily()->at('03:50');
Schedule::command(PruneAnalyticsData::class)->daily()->at('04:10');

// Lightweight scheduler heartbeat for Admin System Health.
Schedule::call(function () {
    cache()->forever('platform:scheduler_heartbeat_at', now()->toIso8601String());
})->everyMinute()->name('platform-scheduler-heartbeat');
