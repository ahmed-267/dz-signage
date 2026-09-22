<?php

namespace App\Support\Demo;

use App\Enums\PlayerPlaybackState;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use Illuminate\Support\Carbon;

/**
 * Seed believable recent heartbeats for designated demo Screens only.
 *
 * Local demo (DemoWorkspaceSeeder) refuses production.
 * Production demo may opt in via $allowInProduction — still scoped to caller screens.
 */
final class DemoHeartbeatSimulator
{
    /**
     * @param  array<int, Screen>  $screens
     * @param  array<int, ScreenDevice>  $devices  keyed by screen_id
     * @param  list<array{count: int, spacing: int, state: string, error: string|null}>|null  $profiles
     */
    public static function seedRecent(
        array $screens,
        array $devices,
        bool $allowInProduction = false,
        string $seedTag = 'demo-workspace',
        ?array $profiles = null,
    ): void {
        if (app()->environment('production') && ! $allowInProduction) {
            return;
        }

        $now = Carbon::now();

        foreach ($screens as $index => $screen) {
            $device = $devices[$screen->id] ?? null;
            if ($device === null) {
                continue;
            }

            ScreenHeartbeat::query()
                ->where('screen_id', $screen->id)
                ->where('metadata->seed', $seedTag)
                ->delete();

            $profile = $profiles[$index] ?? self::defaultLocalProfile($index);

            for ($i = $profile['count'] - 1; $i >= 0; $i--) {
                $recorded = $device->last_seen_at
                    ? Carbon::parse($device->last_seen_at)->copy()->subSeconds($i * $profile['spacing'])
                    : $now->copy()->subSeconds($i * $profile['spacing']);

                ScreenHeartbeat::query()->create([
                    'screen_id' => $screen->id,
                    'screen_device_id' => $device->id,
                    'recorded_at' => $recorded,
                    'player_version' => $device->player_version ?? 'demo-1.0.0',
                    'user_agent' => 'RMSignage-Demo-Player/1.0',
                    'viewport_width' => $device->viewport_width ?? 1920,
                    'viewport_height' => $device->viewport_height ?? 1080,
                    'orientation' => $device->reported_orientation ?? 'landscape',
                    'deployment_id' => $device->reported_deployment_id,
                    'playback_state' => $profile['state'],
                    'error_code' => $profile['error'],
                    'metadata' => ['seed' => $seedTag],
                ]);
            }
        }
    }

    /**
     * Production demo TV mix:
     * Counter / Menu / Reception / Kitchen → Online + Healthy
     * Window Display → Offline / Attention
     *
     * @return list<array{count: int, spacing: int, state: string, error: string|null}>
     */
    public static function productionDemoProfiles(): array
    {
        $online = [
            'count' => 6,
            'spacing' => 40,
            'state' => PlayerPlaybackState::Rendering->value,
            'error' => null,
        ];

        $offline = [
            'count' => 3,
            'spacing' => 3600,
            'state' => PlayerPlaybackState::Inactive->value,
            'error' => null,
        ];

        // Index order matches ProductionDemoAccountProvisioner TV seed order:
        // 0 Counter, 1 Menu Board, 2 Window Display, 3 Reception, 4 Kitchen
        return [$online, $online, $offline, $online, $online];
    }

    /**
     * @return array{count: int, spacing: int, state: string, error: string|null}
     */
    private static function defaultLocalProfile(int $index): array
    {
        return match ($index) {
            0, 1 => ['count' => 8, 'spacing' => 40, 'state' => PlayerPlaybackState::Rendering->value, 'error' => null],
            2 => ['count' => 5, 'spacing' => 50, 'state' => PlayerPlaybackState::Rendering->value, 'error' => null],
            3 => ['count' => 3, 'spacing' => 3600, 'state' => PlayerPlaybackState::Inactive->value, 'error' => null],
            default => ['count' => 4, 'spacing' => 45, 'state' => PlayerPlaybackState::Error->value, 'error' => 'demo_playback_glitch'],
        };
    }
}
