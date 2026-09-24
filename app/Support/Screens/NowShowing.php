<?php

namespace App\Support\Screens;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Support\Schedules\ScheduleEvaluator;

/**
 * Shapes the customer-facing "Now Showing" payload for Paired TVs.
 *
 * Content identity always comes from {@see ScreenContentResolver}. Sync / live
 * acknowledgement comes from {@see ScreenPresence}. Never re-rank Schedule vs
 * Deployment here.
 */
final class NowShowing
{
    public function __construct(
        private readonly ScreenContentResolver $resolver,
        private readonly ScheduleEvaluator $evaluator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forScreen(
        Screen $screen,
        ?ScreenDevice $device = null,
        ?Deployment $activeDeployment = null,
    ): array {
        $device ??= $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $activeDeployment ??= $screen->relationLoaded('deployments')
            ? $screen->deployments->first(fn (Deployment $d) => $d->status === DeploymentStatus::Active)
            : $screen->activeDeployment();

        // Fallback when deployments relation was filtered to Active only.
        if ($activeDeployment === null && $screen->relationLoaded('deployments')) {
            $activeDeployment = $screen->deployments->first(
                fn (Deployment $d) => $d->status === DeploymentStatus::Active,
            ) ?? $screen->deployments->first();
        }

        $resolved = $this->resolver->resolve($screen);
        $sync = ScreenPresence::contentSyncState($activeDeployment, $device);
        $network = ScreenPresence::networkState($device);
        $pairing = ScreenPresence::pairingState($device);

        $ack = match (true) {
            $resolved->isNone() => 'none',
            $network === 'offline' && $pairing === 'connected' => 'waiting',
            $resolved->isSchedule() => 'scheduled',
            $activeDeployment === null => 'none',
            $sync->value === 'up_to_date' => 'live',
            $sync->value === 'out_of_sync' => 'updating',
            default => 'publishing',
        };

        $contentType = match (true) {
            $resolved->isSchedule() => 'schedule',
            $activeDeployment?->isPlaylist() === true => 'playlist',
            $activeDeployment !== null => 'screen_design',
            default => null,
        };

        $contentName = match (true) {
            $resolved->isSchedule() => $resolved->playlist !== null
                ? $resolved->playlist->name
                : $resolved->schedule?->name,
            default => $activeDeployment?->contentName(),
        };

        $itemCount = null;
        if ($resolved->isSchedule() && $resolved->playlistVersion !== null) {
            $itemCount = $resolved->playlistVersion->relationLoaded('items')
                ? $resolved->playlistVersion->items->where('is_active', true)->count()
                : null;
        } elseif ($activeDeployment?->isPlaylist()) {
            $itemCount = $activeDeployment->activePlaylistItemCount();
        }

        $schedule = $resolved->window;
        $next = $this->evaluator->findNextForScreen($screen);

        return [
            'content_source' => $resolved->source->value,
            'content_source_label' => $resolved->source->label(),
            'content_name' => $contentName,
            'content_type' => $contentType,
            'content_type_label' => match ($contentType) {
                'schedule' => 'Schedule',
                'playlist' => 'Playlist',
                'screen_design' => 'Screen',
                default => null,
            },
            'version_number' => $resolved->isSchedule()
                ? $resolved->playlistVersion?->version_number
                : $activeDeployment?->contentVersionNumber(),
            'playlist_item_count' => $itemCount,
            'schedule_id' => $resolved->schedule?->id,
            'schedule_name' => $resolved->schedule?->name,
            'schedule_priority' => $resolved->schedule?->priority,
            'window_ends_at' => $resolved->windowEndsAt()?->toIso8601String(),
            'window_ends_at_local' => $schedule === null ? null : $schedule->endsAt
                ->copy()
                ->setTimezone($schedule->schedule->timezone)
                ->format('H:i'),
            'deployment_id' => $activeDeployment?->id,
            'deployed_at' => $activeDeployment?->deployed_at?->toIso8601String(),
            'sync_state' => $sync->value,
            'sync_state_label' => $sync->label(),
            'ack_state' => $ack,
            'ack_label' => match ($ack) {
                'live' => 'Live',
                'updating' => 'Out of Sync',
                'waiting' => 'Pending',
                'publishing' => 'Pending',
                'scheduled' => 'Scheduled',
                default => 'No content',
            },
            'next_schedule' => $next === null ? null : [
                'id' => $next['schedule']->id,
                'name' => $next['schedule']->name,
                'starts_at_local' => $next['window']->startsAt
                    ->copy()
                    ->setTimezone($next['schedule']->timezone)
                    ->format('Y-m-d H:i'),
            ],
        ];
    }
}
