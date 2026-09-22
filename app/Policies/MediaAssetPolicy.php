<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canViewMedia() ?? false);
    }

    public function view(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->sameCurrentWorkspace($user, $mediaAsset)
            && ($user->roleIn($user->currentWorkspace)?->canViewMedia() ?? false);
    }

    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManageMedia() ?? false);
    }

    public function update(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->sameCurrentWorkspace($user, $mediaAsset)
            && ($user->roleIn($user->currentWorkspace)?->canManageMedia() ?? false);
    }

    public function replace(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->update($user, $mediaAsset)
            && $mediaAsset->type->isFileBased();
    }

    public function duplicate(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->sameCurrentWorkspace($user, $mediaAsset)
            && ($user->roleIn($user->currentWorkspace)?->canManageMedia() ?? false);
    }

    public function delete(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->sameCurrentWorkspace($user, $mediaAsset)
            && ($user->roleIn($user->currentWorkspace)?->canDeleteMedia() ?? false);
    }

    public function download(User $user, MediaAsset $mediaAsset): bool
    {
        return $this->view($user, $mediaAsset)
            && $mediaAsset->hasStoredFile();
    }

    private function sameCurrentWorkspace(User $user, MediaAsset $mediaAsset): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $mediaAsset->workspace_id === (int) $workspace->id;
    }
}
