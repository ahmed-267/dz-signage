<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Location;
use App\Models\User;
use App\Support\Locations\LocationAccess;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Location $location): bool
    {
        return LocationAccess::canAccessLocation($user, $location);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Location $location): bool
    {
        return $this->canManageLocation($user, $location);
    }

    public function archive(User $user, Location $location): bool
    {
        return $this->canManageLocation($user, $location);
    }

    public function delete(User $user, Location $location): bool
    {
        return $this->canManageLocation($user, $location);
    }

    public function assignManagers(User $user, Location $location): bool
    {
        $workspace = $user->currentWorkspace;

        if ($workspace === null
            || ! $user->belongsToWorkspace($workspace)
            || (int) $location->workspace_id !== (int) $workspace->id) {
            return false;
        }

        $role = $user->roleIn($workspace);

        return $role === WorkspaceRole::Owner || $role === WorkspaceRole::Admin;
    }

    public function assignScreen(User $user, Location $location): bool
    {
        return $this->canManageLocation($user, $location);
    }

    private function canManage(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManageLocations() ?? false);
    }

    private function canManageLocation(User $user, Location $location): bool
    {
        if (! $this->canManage($user)) {
            return false;
        }

        return LocationAccess::canAccessLocation($user, $location);
    }
}
