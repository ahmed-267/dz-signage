<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;
use App\Support\Locations\LocationAccess;

class ScreenPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Screen $screen): bool
    {
        return $this->inCurrentWorkspace($user, $screen)
            && LocationAccess::canAccessScreen($user, $screen);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function pair(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Screen $screen): bool
    {
        return $this->inCurrentWorkspace($user, $screen)
            && $this->canManage($user)
            && LocationAccess::canAccessScreen($user, $screen);
    }

    public function delete(User $user, Screen $screen): bool
    {
        return $this->update($user, $screen);
    }

    public function revoke(User $user, Screen $screen): bool
    {
        return $this->update($user, $screen);
    }

    public function publish(User $user, Screen $screen): bool
    {
        return $this->inCurrentWorkspace($user, $screen)
            && $this->canPublish($user)
            && LocationAccess::canAccessScreen($user, $screen);
    }

    private function canManage(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManageScreens() ?? false);
    }

    private function canPublish(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canPublishContent() ?? false);
    }

    private function inCurrentWorkspace(User $user, Screen $screen): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $screen->workspace_id === (int) $workspace->id;
    }
}
