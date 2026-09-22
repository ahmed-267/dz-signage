<?php

namespace App\Actions\Screens;

use App\Enums\PlayerErrorCode;
use App\Enums\PlayerPlaybackState;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordScreenHeartbeat
{
    /**
     * @param  array{
     *     player_version?: string|null,
     *     viewport_width?: int|null,
     *     viewport_height?: int|null,
     *     orientation?: string|null,
     *     deployment_id?: int|null,
     *     screen_design_version_id?: int|null,
     *     playback_state?: string|null,
     *     error_code?: string|null,
     *     user_agent?: string|null,
     *     metadata?: array<string, mixed>|null
     * }  $payload
     */
    public function handle(ScreenDevice $device, Screen $screen, array $payload): ScreenHeartbeat
    {
        if ($device->isRevoked()) {
            throw ValidationException::withMessages([
                'device' => 'This device credential has been revoked.',
            ]);
        }

        if ((int) $device->screen_id !== (int) $screen->id) {
            throw ValidationException::withMessages([
                'device' => 'Device does not belong to this screen.',
            ]);
        }

        $playback = isset($payload['playback_state'])
            ? PlayerPlaybackState::tryFrom((string) $payload['playback_state'])
            : null;

        $error = isset($payload['error_code']) && $payload['error_code'] !== ''
            ? PlayerErrorCode::tryFrom((string) $payload['error_code'])
            : null;

        $orientation = $payload['orientation'] ?? null;
        if ($orientation !== null && ! in_array($orientation, ['landscape', 'portrait'], true)) {
            throw ValidationException::withMessages([
                'orientation' => 'Orientation must be landscape or portrait.',
            ]);
        }

        return DB::transaction(function () use ($device, $screen, $payload, $playback, $error, $orientation) {
            $now = now();

            $heartbeat = ScreenHeartbeat::query()->create([
                'screen_id' => $screen->id,
                'screen_device_id' => $device->id,
                'recorded_at' => $now,
                'player_version' => isset($payload['player_version'])
                    ? mb_substr((string) $payload['player_version'], 0, 64)
                    : null,
                'user_agent' => isset($payload['user_agent'])
                    ? mb_substr((string) $payload['user_agent'], 0, 512)
                    : null,
                'viewport_width' => $payload['viewport_width'] ?? null,
                'viewport_height' => $payload['viewport_height'] ?? null,
                'orientation' => $orientation,
                'deployment_id' => $payload['deployment_id'] ?? null,
                'screen_design_version_id' => $payload['screen_design_version_id'] ?? null,
                'playback_state' => $playback?->value,
                'error_code' => $error?->value,
                'metadata' => is_array($payload['metadata'] ?? null)
                    ? $payload['metadata']
                    : null,
            ]);

            $device->forceFill([
                'last_seen_at' => $now,
                'player_version' => $heartbeat->player_version,
                'viewport_width' => $heartbeat->viewport_width,
                'viewport_height' => $heartbeat->viewport_height,
                'reported_orientation' => $heartbeat->orientation,
                'playback_state' => $heartbeat->playback_state,
                'last_error_code' => $heartbeat->error_code,
                'reported_deployment_id' => $heartbeat->deployment_id,
                'platform_meta' => $this->mergeOfflineMeta(
                    is_array($device->platform_meta) ? $device->platform_meta : [],
                    is_array($payload['metadata'] ?? null) ? $payload['metadata'] : null,
                ),
            ])->save();

            return $heartbeat;
        });
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    private function mergeOfflineMeta(array $existing, ?array $metadata): array
    {
        if ($metadata === null || ! isset($metadata['offline']) || ! is_array($metadata['offline'])) {
            return $existing;
        }

        $offline = $metadata['offline'];
        $existing['offline'] = [
            'cache_ready' => (bool) ($offline['cache_ready'] ?? false),
            'package_version' => isset($offline['package_version'])
                ? mb_substr((string) $offline['package_version'], 0, 64)
                : null,
            'last_sync_at' => isset($offline['last_sync_at'])
                ? mb_substr((string) $offline['last_sync_at'], 0, 64)
                : null,
            'last_offline_at' => isset($offline['last_offline_at'])
                ? mb_substr((string) $offline['last_offline_at'], 0, 64)
                : null,
            'sync_error' => isset($offline['sync_error'])
                ? mb_substr((string) $offline['sync_error'], 0, 255)
                : null,
            'reported_at' => now()->toIso8601String(),
        ];

        return $existing;
    }
}
