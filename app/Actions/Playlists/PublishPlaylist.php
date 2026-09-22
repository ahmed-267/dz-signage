<?php

namespace App\Actions\Playlists;

use App\Enums\PlaylistStatus;
use App\Enums\TemplateOrientation;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\User;
use App\Support\Playlists\PlaylistDefaults;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublishPlaylist
{
    public function handle(User $user, Playlist $playlist): Playlist
    {
        return DB::transaction(function () use ($user, $playlist) {
            $latest = $playlist->versions()
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            if ($latest === null) {
                throw ValidationException::withMessages([
                    'playlist' => 'Nothing to publish.',
                ]);
            }

            $latest->load(['items.screenDesign', 'items.screenDesignVersion']);
            $orientation = $this->validate($playlist, $latest);

            if (! $latest->isPublished()) {
                $latest->forceFill([
                    'published_at' => now(),
                    'created_by' => $latest->created_by ?? $user->id,
                ])->save();
            }

            $playlist->forceFill([
                'orientation' => $orientation,
                'status' => PlaylistStatus::Published,
                'published_version_id' => $latest->id,
                'published_at' => now(),
                'updated_by' => $user->id,
            ])->save();

            return $playlist->fresh(['publishedVersion.items', 'versions']) ?? $playlist;
        });
    }

    private function validate(Playlist $playlist, PlaylistVersion $version): TemplateOrientation
    {
        $active = $version->items->filter(fn (PlaylistItem $item) => $item->is_active)->values();

        if ($active->isEmpty()) {
            throw ValidationException::withMessages([
                'playlist' => 'Add at least one active screen design before publishing this playlist.',
            ]);
        }

        $min = PlaylistDefaults::minDurationSeconds();
        $max = PlaylistDefaults::maxDurationSeconds();
        $minLoops = PlaylistDefaults::minLoopCount();
        $maxLoops = PlaylistDefaults::maxLoopCount();

        $orientation = $playlist->orientation ?? $active->first()->screenDesign?->orientation;

        if ($orientation === null) {
            throw ValidationException::withMessages([
                'playlist' => 'This playlist has no orientation. Add a screen design to set it.',
            ]);
        }

        foreach ($active as $item) {
            $design = $item->screenDesign;
            $designVersion = $item->screenDesignVersion;

            if ($design === null || $designVersion === null) {
                throw ValidationException::withMessages([
                    'playlist' => 'One or more playlist items reference content that no longer exists.',
                ]);
            }

            if ((int) $design->workspace_id !== (int) $playlist->workspace_id) {
                throw ValidationException::withMessages([
                    'playlist' => 'One or more playlist items reference a screen design from another workspace.',
                ]);
            }

            if ((int) $designVersion->screen_design_id !== (int) $design->id) {
                throw ValidationException::withMessages([
                    'playlist' => "\"{$design->name}\" is pinned to a version of a different design.",
                ]);
            }

            if (! $designVersion->isPublished()) {
                throw ValidationException::withMessages([
                    'playlist' => "\"{$design->name}\" is pinned to an unpublished version. Publish that design version first.",
                ]);
            }

            if ($item->duration_seconds < $min || $item->duration_seconds > $max) {
                throw ValidationException::withMessages([
                    'playlist' => "\"{$design->name}\" has an invalid duration. Durations must be between {$min} and {$max} seconds.",
                ]);
            }

            if ($item->loop_count < $minLoops || $item->loop_count > $maxLoops) {
                throw ValidationException::withMessages([
                    'playlist' => "\"{$design->name}\" has an invalid loop count. Loop counts must be between {$minLoops} and {$maxLoops}.",
                ]);
            }

            if ($design->orientation !== $orientation) {
                throw ValidationException::withMessages([
                    'playlist' => "\"{$design->name}\" is {$design->orientation->label()} but this playlist is {$orientation->label()}.",
                ]);
            }
        }

        return $orientation;
    }
}
