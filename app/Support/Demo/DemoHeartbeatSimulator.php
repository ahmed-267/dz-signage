<?php

namespace App\Support\Demo;

use App\Enums\PlayerPlaybackState;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use Illuminate\Support\Carbon;

/**
 * DEV-ONLY: seed believable recent heartbeats for demo Screens.
 * Scoped to caller-provided demo screens/devices — never used in production paths.
 */
final class DemoHeartbeatSimulator
{
    /**
     * @param  list<Screen>  $screens
     * @param  array<int, ScreenDevice>  $devices  keyed by screen_id
     */
    public static function seedRecent(array $screens, array $devices): void
    {
        if (app()->environment('production')) {
            return;
        }

        $now = Carbon::now();

        foreach ($screens as $index => $screen) {
            $device = $devices[$screen->id] ?? null;
            if ($device === null) {
                continue;
            }

            // Purge prior demo heartbeats for this screen so re-seeds stay small.
            ScreenHeartbeat::query()
                ->where('screen_id', $screen->id)
                ->where('metadata->seed', 'demo-workspace')
                ->delete();

            $profile = match ($index) {
                // Counter + Menu: Online / Healthy — fresh heartbeats.
                0, 1 => ['count' => 8, 'spacing' => 40, 'state' => PlayerPlaybackState::Rendering->value, 'error' => null],
                // Window Display: Online but slightly stale attention-friendly.
                2 => ['count' => 5, 'spacing' => 50, 'state' => PlayerPlaybackState::Rendering->value, 'error' => null],
                // Reception: Offline (last_seen already old on device).
                3 => ['count' => 3, 'spacing' => 3600, 'state' => PlayerPlaybackState::Inactive->value, 'error' => null],
                // Kitchen: Online with error playback.
                default => ['count' => 4, 'spacing' => 45, 'state' => PlayerPlaybackState::Error->value, 'error' => 'demo_playback_glitch'],
            };

            for ($i = $profile['count'] - 1; $i >= 0; $i--) {
                $recorded = $device->last_seen_at
                    ? Carbon::parse($device->last_seen_at)->copy()->subSeconds($i * $profile['spacing'])
                    : $now->copy()->subSeconds($i * $profile['spacing']);

                ScreenHeartbeat::query()->create([
                    'screen_id' => $screen->id,
                    'screen_device_id' => $device->id,
                    'recorded_at' => $recorded,
                    'player_version' => $device->player_version ?? 'demo-1.0.0',
                    'user_agent' => 'DZ-Demo-Player/1.0',
                    'viewport_width' => $device->viewport_width ?? 1920,
                    'viewport_height' => $device->viewport_height ?? 1080,
                    'orientation' => $device->reported_orientation ?? 'landscape',
                    'deployment_id' => $device->reported_deployment_id,
                    'playback_state' => $profile['state'],
                    'error_code' => $profile['error'],
                    'metadata' => ['seed' => 'demo-workspace'],
                ]);
            }
        }
    }
}
