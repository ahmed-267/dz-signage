<?php

namespace App\Actions\Playlists;

use App\Enums\PlaylistStatus;
use App\Enums\TemplateOrientation;
use App\Models\Playlist;
use App\Models\PlaylistVersion;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreatePlaylist
{
    public function handle(
        User $user,
        Workspace $workspace,
        string $name,
        ?string $description = null,
        ?TemplateOrientation $orientation = null,
    ): Playlist {
        return DB::transaction(function () use ($user, $workspace, $name, $description, $orientation) {
            $playlist = Playlist::query()->create([
                'workspace_id' => $workspace->id,
                'name' => trim($name) !== '' ? trim($name) : 'Untitled Playlist',
                'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
                'orientation' => $orientation,
                'status' => PlaylistStatus::Draft,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            PlaylistVersion::query()->create([
                'playlist_id' => $playlist->id,
                'version_number' => 1,
                'created_by' => $user->id,
                'published_at' => null,
            ]);

            return $playlist->fresh(['versions']) ?? $playlist;
        });
    }
}
