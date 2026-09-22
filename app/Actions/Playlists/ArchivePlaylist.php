<?php

namespace App\Actions\Playlists;

use App\Enums\PlaylistStatus;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ArchivePlaylist
{
    public function handle(User $user, Playlist $playlist): Playlist
    {
        if ($playlist->status === PlaylistStatus::Archived) {
            throw ValidationException::withMessages([
                'playlist' => 'This playlist is already archived.',
            ]);
        }

        $playlist->forceFill([
            'status' => PlaylistStatus::Archived,
            'updated_by' => $user->id,
        ])->save();

        return $playlist;
    }
}
