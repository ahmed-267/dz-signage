<?php

namespace App\Actions\Analytics;

use App\Enums\PlaybackEventType;
use App\Models\Deployment;
use App\Models\PlaybackEvent;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesignVersion;
use App\Models\ScreenDevice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordPlaybackEvents
{
    /**
     * @param  list<array<string, mixed>>  $events
     * @return array{accepted: int, skipped: int}
     */
    public function handle(ScreenDevice $device, Screen $screen, array $events): array
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

        $max = (int) config('analytics.max_events_per_request', 20);
        $events = array_slice($events, 0, $max);
        $accepted = 0;
        $skipped = 0;

        DB::transaction(function () use ($device, $screen, $events, &$accepted, &$skipped) {
            foreach ($events as $raw) {
                $type = PlaybackEventType::tryFrom((string) ($raw['type'] ?? ''));
                if ($type === null) {
                    $skipped++;

                    continue;
                }

                $idempotency = isset($raw['idempotency_key'])
                    ? mb_substr((string) $raw['idempotency_key'], 0, 64)
                    : null;

                if ($idempotency !== null && $idempotency !== '') {
                    $exists = PlaybackEvent::query()
                        ->where('workspace_id', $screen->workspace_id)
                        ->where('idempotency_key', $idempotency)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }
                }

                $occurredAt = $this->parseOccurredAt($raw['occurred_at'] ?? null);
                $deploymentId = $this->nullableInt($raw['deployment_id'] ?? null);
                $designVersionId = $this->nullableInt($raw['screen_design_version_id'] ?? null);
                $playlistVersionId = $this->nullableInt($raw['playlist_version_id'] ?? null);
                $scheduleId = $this->nullableInt($raw['schedule_id'] ?? null);

                if ($deploymentId !== null && ! $this->deploymentBelongs($screen, $deploymentId)) {
                    $skipped++;

                    continue;
                }

                if ($designVersionId !== null && ! $this->designVersionBelongs($screen, $designVersionId)) {
                    $skipped++;

                    continue;
                }

                if ($playlistVersionId !== null && ! $this->playlistVersionBelongs($screen, $playlistVersionId)) {
                    $skipped++;

                    continue;
                }

                if ($scheduleId !== null && ! $this->scheduleBelongs($screen, $scheduleId)) {
                    $skipped++;

                    continue;
                }

                $duration = isset($raw['duration_seconds'])
                    ? max(0, min(86400, (int) $raw['duration_seconds']))
                    : null;

                PlaybackEvent::query()->create([
                    'workspace_id' => $screen->workspace_id,
                    'screen_id' => $screen->id,
                    'screen_device_id' => $device->id,
                    'type' => $type,
                    'occurred_at' => $occurredAt,
                    'deployment_id' => $deploymentId,
                    'screen_design_version_id' => $designVersionId,
                    'playlist_version_id' => $playlistVersionId,
                    'schedule_id' => $scheduleId,
                    'duration_seconds' => $duration,
                    'error_code' => isset($raw['error_code'])
                        ? mb_substr((string) $raw['error_code'], 0, 64)
                        : null,
                    'idempotency_key' => ($idempotency !== null && $idempotency !== '') ? $idempotency : null,
                    'meta' => is_array($raw['meta'] ?? null) ? $raw['meta'] : null,
                ]);

                $accepted++;
            }
        });

        return ['accepted' => $accepted, 'skipped' => $skipped];
    }

    private function parseOccurredAt(mixed $value): Carbon
    {
        if (is_string($value) && $value !== '') {
            try {
                $parsed = Carbon::parse($value);
                // Reject far-future or ancient timestamps.
                if ($parsed->between(now()->subDays(7), now()->addMinutes(5))) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        return Carbon::now();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function deploymentBelongs(Screen $screen, int $id): bool
    {
        return Deployment::query()
            ->where('id', $id)
            ->where('workspace_id', $screen->workspace_id)
            ->where('screen_id', $screen->id)
            ->exists();
    }

    private function designVersionBelongs(Screen $screen, int $id): bool
    {
        return ScreenDesignVersion::query()
            ->whereKey($id)
            ->whereHas('screenDesign', fn ($q) => $q->where('workspace_id', $screen->workspace_id))
            ->exists();
    }

    private function playlistVersionBelongs(Screen $screen, int $id): bool
    {
        return PlaylistVersion::query()
            ->whereKey($id)
            ->whereHas('playlist', fn ($q) => $q->where('workspace_id', $screen->workspace_id))
            ->exists();
    }

    private function scheduleBelongs(Screen $screen, int $id): bool
    {
        return Schedule::query()
            ->whereKey($id)
            ->where('workspace_id', $screen->workspace_id)
            ->exists();
    }
}
