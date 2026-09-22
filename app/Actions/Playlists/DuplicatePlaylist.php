<?php

namespace App\Actions\Playlists;

use App\Enums\PlaylistStatus;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DuplicatePlaylist
{
    public function handle(User $user, Playlist $playlist): Playlist
    {
        $source = $playlist->latestVersion() ?? $playlist->publishedVersion;
        $items = $source !== null ? $source->items()->get() : collect();

        return DB::transaction(function () use ($user, $playlist, $items) {
            $copy = Playlist::query()->create([
                'workspace_id' => $playlist->workspace_id,
                'name' => $playlist->name.' Copy',
                'description' => $playlist->description,
                'orientation' => $playlist->orientation,
                'status' => PlaylistStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $version = PlaylistVersion::query()->create([
                'playlist_id' => $copy->id,
                'version_number' => 1,
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            $position = 1;
            foreach ($items as $item) {
                PlaylistItem::query()->create([
                    'playlist_version_id' => $version->id,
                    'screen_design_id' => $item->screen_design_id,
                    'screen_design_version_id' => $item->screen_design_version_id,
                    'position' => $position++,
                    'duration_seconds' => $item->duration_seconds,
                    'loop_count' => $item->loop_count,
                    'transition' => $item->transition,
                    'transition_speed' => $item->transition_speed,
                    'is_active' => $item->is_active,
                ]);
            }

            return $copy->fresh(['versions.items']) ?? $copy;
        });
    }
}
