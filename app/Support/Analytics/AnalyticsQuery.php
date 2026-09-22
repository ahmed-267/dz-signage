<?php

namespace App\Support\Analytics;

use App\Enums\DeploymentStatus;
use App\Enums\PlaybackEventType;
use App\Models\Deployment;
use App\Models\PlaybackEvent;
use App\Models\PlaylistVersion;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesignVersion;
use App\Models\Workspace;
use App\Support\Screens\ScreenPresence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Workspace analytics read model. Only uses real telemetry / operational tables.
 */
final class AnalyticsQuery
{
    /**
     * @return array{from: Carbon, to: Carbon, label: string, timezone: string}
     */
    public static function resolveRange(Workspace $workspace, string $range, ?string $from = null, ?string $to = null): array
    {
        $tz = filled($workspace->timezone) ? (string) $workspace->timezone : 'UTC';
        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            $tz = 'UTC';
            $now = Carbon::now($tz);
        }
        $maxDays = (int) config('analytics.max_date_range_days', 92);

        return match ($range) {
            '30d' => [
                'from' => $now->copy()->subDays(29)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
                'label' => 'Last 30 days',
                'timezone' => $tz,
            ],
            'this_month' => [
                'from' => $now->copy()->startOfMonth(),
                'to' => $now->copy()->endOfDay(),
                'label' => 'This month',
                'timezone' => $tz,
            ],
            'prev_month' => [
                'from' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'to' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'label' => 'Previous month',
                'timezone' => $tz,
            ],
            'custom' => self::customRange($from, $to, $tz, $maxDays),
            default => [
                'from' => $now->copy()->subDays(6)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
                'label' => 'Last 7 days',
                'timezone' => $tz,
            ],
        };
    }

    /**
     * @return array{
     *     overview: array<string, mixed>,
     *     screens: list<array<string, mixed>>,
     *     content: list<array<string, mixed>>,
     *     playlists: list<array<string, mixed>>,
     *     publishing: array<string, mixed>,
     *     series: array{
     *         availability: list<array{date: string, online_seconds: int, offline_seconds: int, availability_percent: float|null}>,
     *         playback: list<array{date: string, plays: int, playback_seconds: int}>,
     *         errors: list<array{date: string, error_count: int}>,
     *         publishing_trend: list<array{date: string, active: int, pending: int, failed: int, superseded: int, revoked: int, total: int}>
     *     },
     *     empty: bool
     * }
     */
    public static function dashboard(
        Workspace $workspace,
        Carbon $from,
        Carbon $to,
        ?int $screenId = null,
        ?int $locationId = null,
    ): array {
        $screensQuery = Screen::query()->where('workspace_id', $workspace->id);
        if ($screenId !== null) {
            $screensQuery->whereKey($screenId);
        }
        if ($locationId !== null) {
            $screensQuery->where('location_id', $locationId);
        }
        /** @var Collection<int, Screen> $screens */
        $screens = $screensQuery->with(['devices' => fn ($q) => $q->whereNull('revoked_at')->orderByDesc('paired_at')->orderByDesc('id')])
            ->orderBy('name')
            ->get();

        $onlineNow = 0;
        $offlineNow = 0;
        foreach ($screens as $screen) {
            $device = $screen->devices->first();
            if (ScreenPresence::networkState($device) === 'online') {
                $onlineNow++;
            } elseif ($device !== null) {
                $offlineNow++;
            }
        }

        /** @var list<int> $screenIds */
        $screenIds = $screens->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
        $scopedToScreens = $screenId !== null || $locationId !== null;

        $stats = ScreenDailyStat::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->when($scopedToScreens, function ($q) use ($screenIds) {
                if ($screenIds === []) {
                    $q->whereRaw('0 = 1');
                } else {
                    $q->whereIn('screen_id', $screenIds);
                }
            })
            ->get();

        $onlineSeconds = (int) $stats->sum('online_seconds');
        $offlineSeconds = (int) $stats->sum('offline_seconds');
        $playbackSeconds = (int) $stats->sum('playback_seconds');
        $playCount = (int) $stats->sum('content_play_count');
        $errorCount = (int) $stats->sum('error_count');

        // Fall back to raw events for playback when daily stats are empty (first day).
        if ($playbackSeconds === 0 && $playCount === 0 && $screenIds !== []) {
            $playCount = (int) PlaybackEvent::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('type', [
                    PlaybackEventType::ContentStarted->value,
                    PlaybackEventType::PlaylistItemStarted->value,
                ])
                ->whereBetween('occurred_at', [$from, $to])
                ->when($scopedToScreens, fn ($q) => $q->whereIn('screen_id', $screenIds))
                ->count();

            $playbackSeconds = (int) PlaybackEvent::query()
                ->where('workspace_id', $workspace->id)
                ->whereNotNull('duration_seconds')
                ->whereBetween('occurred_at', [$from, $to])
                ->when($scopedToScreens, fn ($q) => $q->whereIn('screen_id', $screenIds))
                ->sum('duration_seconds');
        }

        $deployments = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$from, $to])
            ->when($scopedToScreens, function ($q) use ($screenIds) {
                if ($screenIds === []) {
                    $q->whereRaw('0 = 1');
                } else {
                    $q->whereIn('screen_id', $screenIds);
                }
            })
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $screenRows = [];
        foreach ($screens as $screen) {
            $rowStats = $stats->where('screen_id', $screen->id);
            $online = (int) $rowStats->sum('online_seconds');
            $offline = (int) $rowStats->sum('offline_seconds');
            $total = $online + $offline;
            $screenRows[] = [
                'id' => $screen->id,
                'name' => $screen->name,
                'network_state' => ScreenPresence::networkState($screen->devices->first()),
                'last_seen_at' => $screen->devices->first()?->last_seen_at?->toIso8601String(),
                'online_seconds' => $online,
                'offline_seconds' => $offline,
                'availability_percent' => $total > 0
                    ? round(($online / $total) * 100, 1)
                    : null,
                'playback_seconds' => (int) $rowStats->sum('playback_seconds'),
                'content_play_count' => (int) $rowStats->sum('content_play_count'),
                'error_count' => (int) $rowStats->sum('error_count'),
            ];
        }

        $content = self::contentLeaderboard(
            $workspace,
            $from,
            $to,
            $screenId,
            10,
            $scopedToScreens ? $screenIds : null,
        );
        $playlists = self::playlistLeaderboard(
            $workspace,
            $from,
            $to,
            $screenId,
            $scopedToScreens ? $screenIds : null,
        );
        $series = self::series(
            $workspace,
            $from,
            $to,
            $screenId,
            $stats->isEmpty(),
            $scopedToScreens ? $screenIds : null,
        );

        $observedSeconds = $onlineSeconds + $offlineSeconds;
        $availabilityPercent = $observedSeconds > 0
            ? round(($onlineSeconds / $observedSeconds) * 100, 1)
            : null;

        $empty = $stats->isEmpty()
            && $playCount === 0
            && (int) $deployments->sum() === 0;

        return [
            'overview' => [
                'total_screens' => $screens->count(),
                'online_now' => $onlineNow,
                'offline_now' => $offlineNow,
                'unpaired_now' => max(0, $screens->count() - $onlineNow - $offlineNow),
                'online_seconds' => $onlineSeconds,
                'offline_seconds' => $offlineSeconds,
                'playback_seconds' => $playbackSeconds,
                'content_play_count' => $playCount,
                'error_count' => $errorCount,
                'availability_percent' => $availabilityPercent,
                'availability_note' => 'Availability is based on Player connectivity (heartbeats), not TV hardware power.',
            ],
            'screens' => $screenRows,
            'content' => $content,
            'playlists' => $playlists,
            'publishing' => [
                'total' => (int) $deployments->sum(),
                'active' => (int) ($deployments[DeploymentStatus::Active->value] ?? 0),
                'pending' => (int) ($deployments[DeploymentStatus::Pending->value] ?? 0),
                'failed' => (int) ($deployments[DeploymentStatus::Failed->value] ?? 0),
                'superseded' => (int) ($deployments[DeploymentStatus::Superseded->value] ?? 0),
                'revoked' => (int) ($deployments[DeploymentStatus::Revoked->value] ?? 0),
                'note' => 'Pending includes Screens that have not yet applied a Deployment (including Offline Screens). Pending is not a failure.',
            ],
            'series' => $series,
            'empty' => $empty,
        ];
    }

    /**
     * Top Screen Design versions by content starts in the range.
     *
     * @param  list<int>|null  $screenIds
     * @return list<array<string, mixed>>
     */
    public static function contentLeaderboard(
        Workspace $workspace,
        Carbon $from,
        Carbon $to,
        ?int $screenId = null,
        int $limit = 15,
        ?array $screenIds = null,
    ): array {
        $query = DB::table('playback_events')
            ->where('workspace_id', $workspace->id)
            ->whereIn('type', [
                PlaybackEventType::ContentStarted->value,
                PlaybackEventType::PlaylistItemStarted->value,
            ])
            ->whereNotNull('screen_design_version_id')
            ->whereBetween('occurred_at', [$from, $to]);

        if ($screenId !== null) {
            $query->where('screen_id', $screenId);
        } elseif ($screenIds !== null) {
            if ($screenIds === []) {
                return [];
            }
            $query->whereIn('screen_id', $screenIds);
        }

        $rows = $query
            ->select(
                'screen_design_version_id',
                DB::raw('count(*) as plays'),
                DB::raw('coalesce(sum(duration_seconds), 0) as playback_seconds'),
                DB::raw('count(distinct screen_id) as screen_count'),
                DB::raw('max(occurred_at) as last_played_at'),
            )
            ->groupBy('screen_design_version_id')
            ->orderByDesc('plays')
            ->limit(max(1, $limit))
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $versions = ScreenDesignVersion::query()
            ->with('screenDesign:id,name')
            ->whereIn('id', $rows->pluck('screen_design_version_id'))
            ->get()
            ->keyBy('id');

        return array_values($rows->map(function ($row) use ($versions) {
            $version = $versions->get($row->screen_design_version_id);

            return [
                'screen_design_version_id' => (int) $row->screen_design_version_id,
                'screen_design_name' => $version?->screenDesign->name ?? 'Unknown design',
                'version_number' => $version?->version_number,
                'plays' => (int) $row->plays,
                'playback_seconds' => (int) $row->playback_seconds,
                'screen_count' => (int) $row->screen_count,
                'last_played_at' => $row->last_played_at
                    ? Carbon::parse($row->last_played_at)->toIso8601String()
                    : null,
            ];
        })->all());
    }

    /**
     * @param  list<int>|null  $screenIds
     * @return list<array<string, mixed>>
     */
    private static function playlistLeaderboard(
        Workspace $workspace,
        Carbon $from,
        Carbon $to,
        ?int $screenId,
        ?array $screenIds = null,
    ): array {
        $query = DB::table('playback_events')
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('playlist_version_id')
            ->whereIn('type', [
                PlaybackEventType::ContentStarted->value,
                PlaybackEventType::PlaylistItemStarted->value,
            ])
            ->whereBetween('occurred_at', [$from, $to]);

        if ($screenId !== null) {
            $query->where('screen_id', $screenId);
        } elseif ($screenIds !== null) {
            if ($screenIds === []) {
                return [];
            }
            $query->whereIn('screen_id', $screenIds);
        }

        $rows = $query
            ->select(
                'playlist_version_id',
                DB::raw('count(*) as plays'),
                DB::raw('coalesce(sum(duration_seconds), 0) as playback_seconds'),
                DB::raw('count(distinct screen_id) as screen_count'),
                DB::raw('max(occurred_at) as last_played_at'),
            )
            ->groupBy('playlist_version_id')
            ->orderByDesc('plays')
            ->limit(10)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $versions = PlaylistVersion::query()
            ->with('playlist:id,name')
            ->whereIn('id', $rows->pluck('playlist_version_id'))
            ->get()
            ->keyBy('id');

        return array_values($rows->map(function ($row) use ($versions) {
            $version = $versions->get($row->playlist_version_id);

            return [
                'playlist_version_id' => (int) $row->playlist_version_id,
                'playlist_name' => $version?->playlist->name ?? 'Unknown playlist',
                'version_number' => $version?->version_number,
                'plays' => (int) $row->plays,
                'playback_seconds' => (int) $row->playback_seconds,
                'screen_count' => (int) $row->screen_count,
                'last_played_at' => $row->last_played_at
                    ? Carbon::parse($row->last_played_at)->toIso8601String()
                    : null,
            ];
        })->all());
    }

    /**
     * @param  list<int>|null  $screenIds
     * @return array{
     *     availability: list<array{date: string, online_seconds: int, offline_seconds: int, availability_percent: float|null}>,
     *     playback: list<array{date: string, plays: int, playback_seconds: int}>,
     *     errors: list<array{date: string, error_count: int}>,
     *     publishing_trend: list<array{date: string, active: int, pending: int, failed: int, superseded: int, revoked: int, total: int}>
     * }
     */
    private static function series(
        Workspace $workspace,
        Carbon $from,
        Carbon $to,
        ?int $screenId,
        bool $preferRawPlayback = false,
        ?array $screenIds = null,
    ): array {
        $scopeScreens = function ($q) use ($screenId, $screenIds): void {
            if ($screenId !== null) {
                $q->where('screen_id', $screenId);
            } elseif ($screenIds !== null) {
                if ($screenIds === []) {
                    $q->whereRaw('0 = 1');
                } else {
                    $q->whereIn('screen_id', $screenIds);
                }
            }
        };

        $stats = ScreenDailyStat::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->tap($scopeScreens)
            ->select(
                'stat_date',
                DB::raw('sum(online_seconds) as online_seconds'),
                DB::raw('sum(offline_seconds) as offline_seconds'),
                DB::raw('sum(playback_seconds) as playback_seconds'),
                DB::raw('sum(content_play_count) as plays'),
                DB::raw('sum(error_count) as error_count'),
            )
            ->groupBy('stat_date')
            ->orderBy('stat_date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->stat_date)->toDateString());

        $rawPlays = collect();
        if ($preferRawPlayback || $stats->isEmpty()) {
            $rawQuery = DB::table('playback_events')
                ->where('workspace_id', $workspace->id)
                ->whereIn('type', [
                    PlaybackEventType::ContentStarted->value,
                    PlaybackEventType::PlaylistItemStarted->value,
                ])
                ->whereBetween('occurred_at', [$from, $to]);

            $scopeScreens($rawQuery);

            $rawPlays = $rawQuery
                ->select(
                    DB::raw('date(occurred_at) as day'),
                    DB::raw('count(*) as plays'),
                    DB::raw('coalesce(sum(duration_seconds), 0) as playback_seconds'),
                )
                ->groupBy(DB::raw('date(occurred_at)'))
                ->get()
                ->keyBy(fn ($row) => (string) $row->day);
        }

        $deployRows = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('created_at', [$from, $to])
            ->tap($scopeScreens)
            ->select(
                DB::raw('date(created_at) as day'),
                'status',
                DB::raw('count(*) as aggregate'),
            )
            ->groupBy(DB::raw('date(created_at)'), 'status')
            ->get()
            ->groupBy(fn ($row) => (string) $row->getAttribute('day'));

        $availability = [];
        $playback = [];
        $errors = [];
        $publishingTrend = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $row = $stats->get($key);
            $raw = $rawPlays->get($key);
            $online = (int) ($row->online_seconds ?? 0);
            $offline = (int) ($row->offline_seconds ?? 0);
            $observed = $online + $offline;

            $availability[] = [
                'date' => $key,
                'online_seconds' => $online,
                'offline_seconds' => $offline,
                'availability_percent' => $observed > 0
                    ? round(($online / $observed) * 100, 1)
                    : null,
            ];
            $playback[] = [
                'date' => $key,
                'plays' => (int) ($row->plays ?? $raw->plays ?? 0),
                'playback_seconds' => (int) ($row->playback_seconds ?? $raw->playback_seconds ?? 0),
            ];
            $errors[] = [
                'date' => $key,
                'error_count' => (int) ($row->error_count ?? 0),
            ];

            $dayDeploys = $deployRows->get($key, collect());
            $statusCount = static function (Collection $day, DeploymentStatus $status): int {
                foreach ($day as $row) {
                    $value = $row->status instanceof DeploymentStatus
                        ? $row->status->value
                        : (string) $row->status;

                    if ($value === $status->value) {
                        return (int) $row->aggregate;
                    }
                }

                return 0;
            };

            $active = $statusCount($dayDeploys, DeploymentStatus::Active);
            $pending = $statusCount($dayDeploys, DeploymentStatus::Pending);
            $failed = $statusCount($dayDeploys, DeploymentStatus::Failed);
            $superseded = $statusCount($dayDeploys, DeploymentStatus::Superseded);
            $revoked = $statusCount($dayDeploys, DeploymentStatus::Revoked);

            $publishingTrend[] = [
                'date' => $key,
                'active' => $active,
                'pending' => $pending,
                'failed' => $failed,
                'superseded' => $superseded,
                'revoked' => $revoked,
                'total' => $active + $pending + $failed + $superseded + $revoked,
            ];

            $cursor->addDay();
        }

        return [
            'availability' => $availability,
            'playback' => $playback,
            'errors' => $errors,
            'publishing_trend' => $publishingTrend,
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon, label: string, timezone: string}
     */
    private static function customRange(?string $from, ?string $to, string $tz, int $maxDays): array
    {
        try {
            $start = $from ? Carbon::parse($from, $tz)->startOfDay() : Carbon::now($tz)->subDays(6)->startOfDay();
            $end = $to ? Carbon::parse($to, $tz)->endOfDay() : Carbon::now($tz)->endOfDay();
        } catch (\Throwable) {
            $start = Carbon::now($tz)->subDays(6)->startOfDay();
            $end = Carbon::now($tz)->endOfDay();
        }

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($start->diffInDays($end) > $maxDays) {
            $start = $end->copy()->subDays($maxDays)->startOfDay();
        }

        return [
            'from' => $start,
            'to' => $end,
            'label' => 'Custom range',
            'timezone' => $tz,
        ];
    }
}
