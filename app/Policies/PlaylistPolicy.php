<?php

namespace App\Policies;

use App\Models\Playlist;
use App\Models\User;

class PlaylistPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Playlist $playlist): bool
    {
        return $this->inCurrentWorkspace($user, $playlist);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Playlist $playlist): bool
    {
        return $this->inCurrentWorkspace($user, $playlist)
            && $this->canManage($user);
    }

    public function delete(User $user, Playlist $playlist): bool
    {
        return $this->update($user, $playlist);
    }

    public function publish(User $user, Playlist $playlist): bool
    {
        return $this->update($user, $playlist);
    }

    public function duplicate(User $user, Playlist $playlist): bool
    {
        return $this->update($user, $playlist);
    }

    public function archive(User $user, Playlist $playlist): bool
    {
        return $this->update($user, $playlist);
    }

    /**
     * Deploying a Playlist to Screens is Publishing, not playlist management.
     */
    public function publishToScreens(User $user, Playlist $playlist): bool
    {
        $workspace = $user->currentWorkspace;

        return $this->inCurrentWorkspace($user, $playlist)
            && ($user->roleIn($workspace)?->canPublishContent() ?? false);
    }

    private function canManage(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManagePlaylists() ?? false);
    }

    private function inCurrentWorkspace(User $user, Playlist $playlist): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $playlist->workspace_id === (int) $workspace->id;
    }
}
