<?php

namespace App\Console\Commands;

use App\Models\PlaybackEvent;
use App\Models\ScreenDailyStat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneAnalyticsData extends Command
{
    protected $signature = 'analytics:prune';

    protected $description = 'Prune raw playback events and old daily analytics aggregates';

    public function handle(): int
    {
        $eventDays = (int) config('analytics.playback_events_retention_days', 90);
        $statDays = (int) config('analytics.daily_stats_retention_days', 400);

        $eventsDeleted = PlaybackEvent::query()
            ->where('occurred_at', '<', Carbon::now()->subDays($eventDays))
            ->limit(5000)
            ->delete();

        // Delete in a loop-friendly way if huge.
        $more = true;
        while ($more && $eventsDeleted < 50_000) {
            $batch = PlaybackEvent::query()
                ->where('occurred_at', '<', Carbon::now()->subDays($eventDays))
                ->limit(5000)
                ->delete();
            $eventsDeleted += $batch;
            $more = $batch > 0;
        }

        $statsDeleted = ScreenDailyStat::query()
            ->where('stat_date', '<', Carbon::now()->subDays($statDays)->toDateString())
            ->delete();

        $this->info("Deleted {$eventsDeleted} playback events and {$statsDeleted} daily stats.");

        return self::SUCCESS;
    }
}
