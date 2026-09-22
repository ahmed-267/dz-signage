<?php

namespace App\Support\Player;

use App\Enums\ScheduleStatus;
use App\Enums\ScreenOperationalStatus;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Models\Screen;
use App\Support\Schedules\ScheduleEvaluator;
use App\Support\Screens\ScreenContentResolver;
use App\Support\Widgets\WidgetDataCollector;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the versioned offline sync package for a Screen.
 *
 * Precedence offline matches online: matching Schedule window → Deployment → none.
 * Schedule windows for the horizon are precomputed by ScheduleEvaluator so the
 * Player does not invent a second timing model — it only picks among server windows.
 */
final class PlayerOfflinePackageBuilder
{
    public function __construct(
        private readonly ScreenContentResolver $resolver,
        private readonly ScheduleEvaluator $evaluator,
        private readonly ManifestContentBuilder $content,
        private readonly WidgetDataCollector $widgets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Screen $screen, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $at = Carbon::instance($at);
        $horizonHours = max(1, (int) config('player.offline_horizon_hours', 24));
        $horizonEnd = $at->copy()->addHours($horizonHours);

        if ($screen->operational_status === ScreenOperationalStatus::Inactive) {
            $current = $this->content->emptyPayload($screen, 'inactive');

            return $this->package($screen, $at, $horizonHours, $current, [], null, [$current]);
        }

        $resolved = $this->resolver->resolve($screen, $at);
        $current = match (true) {
            $resolved->isSchedule() => $this->content->schedulePayload($screen, $resolved),
            $resolved->deployment !== null => $this->content->deploymentPayload($screen, $resolved->deployment),
            default => $this->content->emptyPayload($screen, 'no_content'),
        };

        $fallbackDeployment = $screen->activeDeployment();
        $fallback = $fallbackDeployment !== null
            ? $this->content->deploymentPayload($screen, $fallbackDeployment)
            : null;

        $entries = $this->scheduleEntries($screen, $at, $horizonEnd);
        $payloads = [$current];
        foreach ($entries as $entry) {
            $payloads[] = $entry['content'];
        }
        if ($fallback !== null) {
            $payloads[] = $fallback;
        }

        return $this->package($screen, $at, $horizonHours, $current, $entries, $fallback, $payloads);
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>|null  $fallback
     * @param  list<array<string, mixed>>  $payloads
     * @return array<string, mixed>
     */
    private function package(
        Screen $screen,
        Carbon $at,
        int $horizonHours,
        array $current,
        array $entries,
        ?array $fallback,
        array $payloads,
    ): array {
        $assets = $this->content->collectAssets($screen, $payloads);
        $widgetData = $this->widgets->collectFromSchemas(
            $this->widgets->schemasFromPayloads($payloads),
        );
        $packageVersion = $this->versionFor($screen, $current, $entries, $fallback, $assets, $widgetData);

        return [
            'packageVersion' => $packageVersion,
            'manifestVersion' => 1,
            'horizonHours' => $horizonHours,
            'generatedAt' => $at->toIso8601String(),
            'screen' => [
                'id' => $screen->id,
                'name' => $screen->name,
                'orientation' => $screen->orientation,
                'operationalStatus' => $screen->operational_status->value,
            ],
            'current' => $current,
            'scheduleEntries' => $entries,
            'fallbackDeployment' => $fallback,
            'assets' => $assets,
            'widgetData' => $widgetData,
        ];
    }

    /**
     * Precompute every Active schedule occurrence overlapping [at, horizonEnd).
     *
     * @return list<array<string, mixed>>
     */
    private function scheduleEntries(Screen $screen, Carbon $at, Carbon $horizonEnd): array
    {
        $entries = [];

        foreach ($this->evaluator->activeSchedulesForScreen($screen) as $schedule) {
            /** @var Schedule $schedule */
            if ($schedule->status !== ScheduleStatus::Active || $schedule->playlist_version_id === null) {
                continue;
            }

            $playlist = $schedule->playlist;
            $version = $schedule->playlistVersion;
            if ($playlist === null || $version === null) {
                continue;
            }

            $body = $this->playlistContent($screen, $schedule, $playlist, $version);
            if ($body === null) {
                continue;
            }

            $append = function ($window) use (&$entries, $schedule, $version, $body, $at, $horizonEnd): void {
                if ($window === null) {
                    return;
                }

                $starts = $window->startsAtUtc();
                $ends = $window->endsAtUtc();

                if ($ends->lte($at) || $starts->gte($horizonEnd)) {
                    return;
                }

                $entries[] = [
                    'scheduleId' => $schedule->id,
                    'scheduleName' => $schedule->name,
                    'priority' => $schedule->priority,
                    'activatedAt' => $schedule->activated_at?->toIso8601String(),
                    'timezone' => $schedule->timezone,
                    'window' => [
                        'startsAt' => $starts->toIso8601String(),
                        'endsAt' => $ends->toIso8601String(),
                        'key' => $window->key(),
                    ],
                    'content' => [
                        ...$body,
                        'deploymentVersion' => sprintf(
                            'sch-%d-pv-%d-%s',
                            $schedule->id,
                            $version->id,
                            $window->key(),
                        ),
                        'validity' => [
                            'timezone' => $schedule->timezone,
                            'startsAt' => $starts->toIso8601String(),
                            'endsAt' => $ends->toIso8601String(),
                        ],
                    ],
                ];
            };

            $append($this->evaluator->window($schedule, $at));

            $cursor = $at->copy();
            $seen = 0;
            while ($seen < 48) {
                $window = $this->evaluator->nextWindow($schedule, $cursor);
                if ($window === null) {
                    break;
                }

                if ($window->startsAtUtc()->gte($horizonEnd)) {
                    break;
                }

                $append($window);
                $cursor = $window->startsAt;
                $seen++;
            }
        }

        usort($entries, function (array $a, array $b): int {
            $priority = $b['priority'] <=> $a['priority'];
            if ($priority !== 0) {
                return $priority;
            }

            $aAct = $a['activatedAt'] ?? '';
            $bAct = $b['activatedAt'] ?? '';
            if ($aAct !== $bAct) {
                return $bAct <=> $aAct;
            }

            return $b['scheduleId'] <=> $a['scheduleId'];
        });

        return $entries;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function playlistContent(
        Screen $screen,
        Schedule $schedule,
        Playlist $playlist,
        PlaylistVersion $version,
    ): ?array {
        $body = $this->content->playlistBody($screen, $playlist, $version);
        if ($body === null) {
            return null;
        }

        return [
            'manifestVersion' => 1,
            'screenId' => $screen->id,
            'contentSource' => 'schedule',
            'scheduleId' => $schedule->id,
            'scheduleName' => $schedule->name,
            'schedulePriority' => $schedule->priority,
            'deploymentId' => null,
            'contentType' => 'playlist',
            ...$body,
            'generatedAt' => now()->toIso8601String(),
            'status' => 'ready',
        ];
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  list<array<string, mixed>>  $entries
     * @param  array<string, mixed>|null  $fallback
     * @param  list<array<string, mixed>>  $assets
     * @param  array<string, mixed|null>  $widgetData
     */
    private function versionFor(
        Screen $screen,
        array $current,
        array $entries,
        ?array $fallback,
        array $assets,
        array $widgetData = [],
    ): string {
        $parts = [
            's'.$screen->id,
            (string) ($current['deploymentVersion'] ?? $current['status'] ?? ''),
            'e'.count($entries),
            'f'.(string) ($fallback['deploymentVersion'] ?? 'none'),
        ];
        foreach ($entries as $entry) {
            $parts[] = ($entry['scheduleId'] ?? 0).':'.($entry['window']['key'] ?? '');
        }
        foreach ($assets as $asset) {
            $parts[] = $asset['cacheKey'];
        }
        foreach ($widgetData as $key => $payload) {
            $parts[] = $key.':'.(is_array($payload) ? ($payload['fetchedAt'] ?? '1') : '0');
        }

        return 'pkg-'.substr(hash('sha256', implode('|', $parts)), 0, 24);
    }
}
