<?php

namespace App\Support\Screens;

use App\Enums\ContentSyncState;
use App\Enums\PlayerPlaybackState;
use App\Enums\ScreenHealthStatus;
use App\Enums\ScreenOperationalStatus;
use App\Models\Deployment;
use App\Models\Screen;
use App\Models\ScreenDevice;
use Carbon\CarbonInterface;

/**
 * Central Screen presence, health, and sync evaluation.
 * Do not duplicate Online/Offline thresholds in React or controllers.
 */
final class ScreenPresence
{
    public static function heartbeatIntervalSeconds(): int
    {
        return max(15, (int) config('screens.heartbeat_interval_seconds', 45));
    }

    public static function onlineThresholdSeconds(): int
    {
        return max(
            self::heartbeatIntervalSeconds(),
            (int) config('screens.online_threshold_seconds', 90),
        );
    }

    public static function onlineCutoff(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->subSeconds(self::onlineThresholdSeconds());
    }

    /**
     * @return 'connected'|'disconnected'
     */
    public static function pairingState(?ScreenDevice $device): string
    {
        if ($device === null || $device->isRevoked()) {
            return 'disconnected';
        }

        return 'connected';
    }

    /**
     * @return 'online'|'offline'
     */
    public static function networkState(?ScreenDevice $device, ?CarbonInterface $now = null): string
    {
        if ($device === null || $device->isRevoked() || $device->last_seen_at === null) {
            return 'offline';
        }

        return $device->last_seen_at->gte(self::onlineCutoff($now))
            ? 'online'
            : 'offline';
    }

    public static function health(
        Screen $screen,
        ?ScreenDevice $device = null,
        ?Deployment $activeDeployment = null,
        ?CarbonInterface $now = null,
    ): ScreenHealthStatus {
        $device ??= $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $pairing = self::pairingState($device);
        $network = self::networkState($device, $now);

        if ($pairing === 'connected' && $network === 'offline') {
            return ScreenHealthStatus::Offline;
        }

        if ($pairing === 'disconnected') {
            return ScreenHealthStatus::Attention;
        }

        $deployment = $activeDeployment ?? $screen->activeDeployment();
        $playback = $device?->playback_state;

        if (
            $screen->operational_status === ScreenOperationalStatus::Active
            && $deployment === null
        ) {
            return ScreenHealthStatus::Attention;
        }

        if ($playback === PlayerPlaybackState::Error->value) {
            return ScreenHealthStatus::Attention;
        }

        if (self::hasOrientationMismatch($screen, $device, $deployment)) {
            return ScreenHealthStatus::Attention;
        }

        // Connected and online: Active screens have content, and being
        // Inactive on purpose is not an alert.
        return ScreenHealthStatus::Healthy;
    }

    public static function contentSyncState(
        ?Deployment $activeDeployment,
        ?ScreenDevice $device,
    ): ContentSyncState {
        if ($activeDeployment === null || $device === null || $device->reported_deployment_id === null) {
            return ContentSyncState::Unknown;
        }

        return (int) $device->reported_deployment_id === (int) $activeDeployment->id
            ? ContentSyncState::UpToDate
            : ContentSyncState::OutOfSync;
    }

    public static function hasOrientationMismatch(
        Screen $screen,
        ?ScreenDevice $device,
        ?Deployment $deployment = null,
    ): bool {
        $reported = $device?->reported_orientation;
        if ($reported !== 'landscape' && $reported !== 'portrait') {
            return false;
        }

        $contentOrientation = $deployment?->contentOrientation()
            ?? $screen->orientation;

        if ($contentOrientation !== 'landscape' && $contentOrientation !== 'portrait') {
            return false;
        }

        return $reported !== $contentOrientation;
    }

    /**
     * @return array{
     *     operational_status: string,
     *     pairing_state: string,
     *     network_state: string,
     *     health: string,
     *     health_label: string,
     *     content_sync: string,
     *     content_sync_label: string,
     *     orientation_mismatch: bool
     * }
     */
    public static function snapshot(
        Screen $screen,
        ?ScreenDevice $device = null,
        ?Deployment $activeDeployment = null,
        ?CarbonInterface $now = null,
    ): array {
        $device ??= $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $deployment = $activeDeployment;
        if ($deployment === null && $screen->relationLoaded('deployments')) {
            $deployment = $screen->deployments->first();
        }
        $deployment ??= $screen->activeDeployment();

        $health = self::health($screen, $device, $deployment, $now);
        $sync = self::contentSyncState($deployment, $device);

        return [
            'operational_status' => $screen->operational_status->value,
            'pairing_state' => self::pairingState($device),
            'network_state' => self::networkState($device, $now),
            'health' => $health->value,
            'health_label' => $health->label(),
            'content_sync' => $sync->value,
            'content_sync_label' => $sync->label(),
            'orientation_mismatch' => self::hasOrientationMismatch($screen, $device, $deployment),
        ];
    }
}
