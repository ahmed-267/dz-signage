<?php

namespace App\Http\Controllers\App;

use App\Enums\DeploymentStatus;
use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ScreenHealthStatus;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\Workspace;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Billing\BillingEntitlement;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $role = $workspace ? $user->roleIn($workspace) : null;

        if ($workspace === null) {
            return Inertia::render('app/dashboard', [
                'workspaceSummary' => null,
                'metrics' => null,
                'recentDeployments' => [],
                'recentScreens' => [],
                'availabilitySeries' => [],
                'contentActivity' => [],
                'empty' => true,
            ]);
        }

        $screens = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'location:id,name',
                'devices' => fn ($q) => $q->whereNull('revoked_at')->orderByDesc('paired_at')->orderByDesc('id'),
                'deployments' => fn ($q) => $q
                    ->where('status', DeploymentStatus::Active)
                    ->latest('id'),
            ])
            ->orderBy('name')
            ->get();

        $designsCount = ScreenDesign::query()->where('workspace_id', $workspace->id)->count();
        $playlistsActive = Playlist::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', PlaylistStatus::Published)
            ->count();
        $schedulesActive = Schedule::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', ScheduleStatus::Active)
            ->count();

        $online = 0;
        $offline = 0;
        $attention = 0;
        $healthy = 0;
        $healthOffline = 0;

        foreach ($screens as $screen) {
            $device = $screen->devices->first();
            $deployment = $screen->deployments->first();
            $network = ScreenPresence::networkState($device);
            if ($network === 'online') {
                $online++;
            } elseif ($device !== null) {
                $offline++;
            }

            $health = ScreenPresence::health($screen, $device, $deployment);
            match ($health) {
                ScreenHealthStatus::Healthy => $healthy++,
                ScreenHealthStatus::Attention => $attention++,
                ScreenHealthStatus::Offline => $healthOffline++,
            };
        }

        $billing = BillingEntitlement::summary($workspace);

        $recentDeployments = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->with(['screen:id,name', 'screenDesign:id,name', 'playlist:id,name'])
            ->latest('deployed_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (Deployment $d) => [
                'id' => $d->id,
                'screen_name' => $d->screen?->name,
                'content_name' => $d->contentName(),
                'content_type' => $d->content_type->value,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'deployed_at' => $d->deployed_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $recentScreens = $screens
            ->map(function (Screen $screen) {
                $device = $screen->devices->first();
                $deployment = $screen->deployments->first();
                $snapshot = ScreenPresence::snapshot($screen, $device, $deployment);

                return [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'location_name' => $screen->location?->name,
                    'network_state' => $snapshot['network_state'],
                    'health' => $snapshot['health'],
                    'health_label' => $snapshot['health_label'],
                    'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
                    '_health_rank' => match ($snapshot['health']) {
                        ScreenHealthStatus::Attention->value => 0,
                        ScreenHealthStatus::Offline->value => 1,
                        default => 2,
                    },
                    '_last_seen_ts' => $device?->last_seen_at?->getTimestamp() ?? 0,
                ];
            })
            ->sort(function (array $a, array $b): int {
                if ($a['_health_rank'] !== $b['_health_rank']) {
                    return $a['_health_rank'] <=> $b['_health_rank'];
                }

                return $b['_last_seen_ts'] <=> $a['_last_seen_ts'];
            })
            ->take(5)
            ->values()
            ->map(fn (array $row) => collect($row)->except(['_health_rank', '_last_seen_ts'])->all())
            ->all();

        $availabilitySeries = $this->availabilitySeries($workspace, 30);
        $periodStats = $this->periodStats($workspace, 30);
        $range = AnalyticsQuery::resolveRange($workspace, '30d');
        $contentActivity = AnalyticsQuery::contentLeaderboard(
            $workspace,
            $range['from'],
            $range['to'],
            null,
            5,
        );

        $empty = $screens->isEmpty() && $designsCount === 0;

        return Inertia::render('app/dashboard', [
            'workspaceSummary' => [
                'name' => $workspace->name,
                'industry' => $workspace->industry->label(),
                'role' => $role?->label(),
                'country' => $workspace->country,
                'timezone' => $workspace->timezone,
            ],
            'metrics' => [
                'screens' => [
                    'total' => $screens->count(),
                    'online' => $online,
                    'offline' => $offline,
                    'attention' => $attention,
                    'healthy' => $healthy,
                    'health_offline' => $healthOffline,
                ],
                'designs' => $designsCount,
                'playlists_active' => $playlistsActive,
                'schedules_active' => $schedulesActive,
                'availability_percent' => $periodStats['availability_percent'],
                'playback_seconds' => $periodStats['playback_seconds'],
                'licences' => [
                    'used' => $billing['used'],
                    'licensed' => $billing['licensed'],
                    'remaining' => $billing['remaining'],
                    'has_subscription' => $billing['has_subscription'],
                ],
            ],
            'recentDeployments' => $recentDeployments,
            'recentScreens' => $recentScreens,
            'availabilitySeries' => $availabilitySeries,
            'contentActivity' => $contentActivity,
            'empty' => $empty,
        ]);
    }

    /**
     * @return list<array{date: string, online_seconds: int, offline_seconds: int, availability_percent: float|null}>
     */
    private function availabilitySeries(Workspace $workspace, int $days = 30): array
    {
        $days = max(1, $days);
        $tz = filled($workspace->timezone) ? (string) $workspace->timezone : 'UTC';
        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            $now = Carbon::now('UTC');
        }

        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $to = $now->copy()->endOfDay();

        $stats = ScreenDailyStat::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn (ScreenDailyStat $row) => $row->stat_date->toDateString());

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->toDateString();
            $day = $stats->get($date, collect());
            $online = (int) $day->sum('online_seconds');
            $offline = (int) $day->sum('offline_seconds');
            $total = $online + $offline;

            $series[] = [
                'date' => $date,
                'online_seconds' => $online,
                'offline_seconds' => $offline,
                'availability_percent' => $total > 0
                    ? round(($online / $total) * 100, 1)
                    : null,
            ];
        }

        return $series;
    }

    /**
     * @return array{availability_percent: float|null, playback_seconds: int}
     */
    private function periodStats(Workspace $workspace, int $days = 30): array
    {
        $days = max(1, $days);
        $tz = filled($workspace->timezone) ? (string) $workspace->timezone : 'UTC';
        try {
            $now = Carbon::now($tz);
        } catch (\Throwable) {
            $now = Carbon::now('UTC');
        }

        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $to = $now->copy()->endOfDay();

        $aggregate = ScreenDailyStat::query()
            ->where('workspace_id', $workspace->id)
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'coalesce(sum(online_seconds), 0) as online_seconds, '.
                'coalesce(sum(offline_seconds), 0) as offline_seconds, '.
                'coalesce(sum(playback_seconds), 0) as playback_seconds',
            )
            ->first();

        $online = (int) ($aggregate->online_seconds ?? 0);
        $offline = (int) ($aggregate->offline_seconds ?? 0);
        $observed = $online + $offline;

        return [
            'availability_percent' => $observed > 0
                ? round(($online / $observed) * 100, 1)
                : null,
            'playback_seconds' => (int) ($aggregate->playback_seconds ?? 0),
        ];
    }
}
