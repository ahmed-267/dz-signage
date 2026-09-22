<?php

namespace App\Console\Commands;

use App\Enums\PlaybackEventType;
use App\Models\PlaybackEvent;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenHeartbeat;
use App\Support\Screens\ScreenPresence;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateScreenDailyStats extends Command
{
    protected $signature = 'analytics:aggregate-daily {date? : Y-m-d (defaults to yesterday)}';

    protected $description = 'Aggregate Screen availability and playback stats for a calendar day';

    public function handle(): int
    {
        $date = $this->argument('date')
            ? Carbon::parse((string) $this->argument('date'))->startOfDay()
            : Carbon::yesterday()->startOfDay();

        $dayStart = $date->copy()->startOfDay();
        $dayEnd = $date->copy()->endOfDay();
        $interval = max(1, ScreenPresence::heartbeatIntervalSeconds());
        $daySeconds = 86400;

        $screens = Screen::query()->select(['id', 'workspace_id'])->orderBy('id')->get();
        $upserted = 0;

        foreach ($screens as $screen) {
            $heartbeatCount = ScreenHeartbeat::query()
                ->where('screen_id', $screen->id)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->count();

            $bucketCount = (int) (ScreenHeartbeat::query()
                ->where('screen_id', $screen->id)
                ->whereBetween('recorded_at', [$dayStart, $dayEnd])
                ->selectRaw(
                    'count(distinct floor(extract(epoch from recorded_at) / ?)) as buckets',
                    [$interval],
                )
                ->value('buckets') ?? 0);

            $onlineSeconds = min($daySeconds, $bucketCount * $interval);

            $playCount = PlaybackEvent::query()
                ->where('screen_id', $screen->id)
                ->whereIn('type', [
                    PlaybackEventType::ContentStarted->value,
                    PlaybackEventType::PlaylistItemStarted->value,
                ])
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->count();

            $playbackSeconds = (int) PlaybackEvent::query()
                ->where('screen_id', $screen->id)
                ->whereNotNull('duration_seconds')
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->sum('duration_seconds');

            $errorCount = PlaybackEvent::query()
                ->where('screen_id', $screen->id)
                ->where('type', PlaybackEventType::PlayerError->value)
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->count();

            ScreenDailyStat::query()->updateOrCreate(
                [
                    'screen_id' => $screen->id,
                    'stat_date' => $dayStart->toDateString(),
                ],
                [
                    'workspace_id' => $screen->workspace_id,
                    'online_seconds' => $onlineSeconds,
                    'offline_seconds' => max(0, $daySeconds - $onlineSeconds),
                    'playback_seconds' => min($daySeconds, $playbackSeconds),
                    'content_play_count' => $playCount,
                    'error_count' => $errorCount,
                    'heartbeat_count' => $heartbeatCount,
                ],
            );

            $upserted++;
        }

        $this->info("Aggregated {$upserted} screens for {$dayStart->toDateString()}.");

        return self::SUCCESS;
    }
}
