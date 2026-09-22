<?php

namespace App\Actions\Playlists;

use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePlaylist
{
    public function handle(Playlist $playlist): void
    {
        $activeDeployments = Deployment::query()
            ->where('playlist_id', $playlist->id)
            ->where('status', DeploymentStatus::Active)
            ->count();

        if ($activeDeployments > 0) {
            throw ValidationException::withMessages([
                'playlist' => "This playlist is live on {$activeDeployments} screen(s) and cannot be deleted. Publish other content to those screens, or archive this playlist instead.",
            ]);
        }

        // Schedules pin a playlist version with a restricting FK, so a
        // referenced playlist cannot be deleted without breaking them.
        $schedules = Schedule::query()
            ->where('playlist_id', $playlist->id)
            ->count();

        if ($schedules > 0) {
            throw ValidationException::withMessages([
                'playlist' => "This playlist is used by {$schedules} schedule(s) and cannot be deleted. Remove it from those schedules, or archive this playlist instead.",
            ]);
        }

        DB::transaction(function () use ($playlist) {
            // Clear historical deployments so restrict FKs allow the delete.
            Deployment::query()
                ->where('playlist_id', $playlist->id)
                ->delete();

            $playlist->forceFill(['published_version_id' => null])->save();

            PlaylistItem::query()
                ->whereIn('playlist_version_id', $playlist->versions()->select('id'))
                ->delete();

            $playlist->versions()->delete();
            $playlist->delete();
        });
    }
}
