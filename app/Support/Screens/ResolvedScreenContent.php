<?php

namespace App\Support\Screens;

use App\Enums\ScreenContentSource;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Support\Schedules\ScheduleWindow;
use Illuminate\Support\Carbon;

/**
 * What a Screen should be playing right now, and why.
 *
 * Built only by `ScreenContentResolver` so precedence lives in one place.
 */
final class ResolvedScreenContent
{
    private function __construct(
        public readonly ScreenContentSource $source,
        public readonly ?Schedule $schedule = null,
        public readonly ?ScheduleWindow $window = null,
        public readonly ?Playlist $playlist = null,
        public readonly ?PlaylistVersion $playlistVersion = null,
        public readonly ?Deployment $deployment = null,
    ) {}

    public static function fromSchedule(ScheduleWindow $window): self
    {
        $schedule = $window->schedule;

        return new self(
            source: ScreenContentSource::Schedule,
            schedule: $schedule,
            window: $window,
            playlist: $schedule->playlist,
            playlistVersion: $schedule->playlistVersion,
        );
    }

    public static function fromDeployment(Deployment $deployment): self
    {
        return new self(
            source: ScreenContentSource::Deployment,
            playlist: $deployment->isPlaylist() ? $deployment->playlist : null,
            playlistVersion: $deployment->isPlaylist() ? $deployment->playlistVersion : null,
            deployment: $deployment,
        );
    }

    public static function none(): self
    {
        return new self(source: ScreenContentSource::None);
    }

    public function isSchedule(): bool
    {
        return $this->source === ScreenContentSource::Schedule;
    }

    public function isDeployment(): bool
    {
        return $this->source === ScreenContentSource::Deployment;
    }

    public function isNone(): bool
    {
        return $this->source === ScreenContentSource::None;
    }

    /**
     * Opaque token a polling player compares against what it is showing. It
     * changes when the schedule, its pinned version, or the occurrence changes,
     * so a window changeover triggers a manifest refetch.
     */
    public function versionLabel(): ?string
    {
        if ($this->isSchedule() && $this->schedule !== null && $this->window !== null) {
            return sprintf(
                'sch-%d-pv-%d-%s',
                $this->schedule->id,
                (int) $this->schedule->playlist_version_id,
                $this->window->key(),
            );
        }

        return $this->deployment?->versionLabel();
    }

    /**
     * Start of the current schedule window in UTC, or null for deployments
     * (which are always-on and have no validity window).
     */
    public function windowStartsAt(): ?Carbon
    {
        return $this->window?->startsAtUtc();
    }

    public function windowEndsAt(): ?Carbon
    {
        return $this->window?->endsAtUtc();
    }

    /**
     * Media assets the player may fetch for this content.
     *
     * @return list<int>
     */
    public function allowedMediaAssetIds(): array
    {
        if ($this->isSchedule()) {
            return $this->playlistVersion?->mediaAssetIds() ?? [];
        }

        return $this->deployment?->allowedMediaAssetIds() ?? [];
    }
}
