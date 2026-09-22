<?php

namespace App\Actions\Playlists;

use App\Actions\Deployments\PublishContentToScreens;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Thin wrapper kept for existing call sites. Prefer PublishContentToScreens.
 */
class PublishPlaylistToScreens
{
    public function __construct(private readonly PublishContentToScreens $publisher) {}

    /**
     * @param  list<int>  $screenIds
     * @return Collection<int, Deployment>
     */
    public function handle(
        User $user,
        Workspace $workspace,
        Playlist $playlist,
        array $screenIds,
    ): Collection {
        return $this->publisher->publishPlaylist($user, $workspace, $playlist, $screenIds)['deployments'];
    }
}
