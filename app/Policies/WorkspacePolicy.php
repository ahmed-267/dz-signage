<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->belongsToWorkspace($workspace);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canManageWorkspace() ?? false;
    }

    public function manageTeam(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace)?->canManageTeam() ?? false;
    }

    public function invite(User $user, Workspace $workspace): bool
    {
        return $this->manageTeam($user, $workspace);
    }

    public function updateMember(User $user, Workspace $workspace, WorkspaceMember $member): bool
    {
        if (! $this->manageTeam($user, $workspace)) {
            return false;
        }

        if ($member->workspace_id !== $workspace->id) {
            return false;
        }

        if ($member->role === WorkspaceRole::Owner) {
            return false;
        }

        if ($member->user_id === $user->id) {
            return false;
        }

        return true;
    }

    public function removeMember(User $user, Workspace $workspace, WorkspaceMember $member): bool
    {
        return $this->updateMember($user, $workspace, $member);
    }

    public function leave(User $user, Workspace $workspace): bool
    {
        $role = $user->roleIn($workspace);

        if ($role === null) {
            return false;
        }

        if ($role !== WorkspaceRole::Owner) {
            return true;
        }

        return $workspace->members()
            ->where('role', WorkspaceRole::Owner->value)
            ->count() > 1;
    }
}
