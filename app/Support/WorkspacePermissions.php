<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

final class WorkspacePermissions
{
    /**
     * @return array{
     *     can_manage_workspace: bool,
     *     can_manage_team: bool,
     *     can_invite: bool,
     *     can_update_roles: bool,
     *     can_remove_members: bool,
     *     can_leave: bool
     * }
     */
    public static function for(?WorkspaceRole $role, User $user, Workspace $workspace): array
    {
        $canManageTeam = $role?->canManageTeam() ?? false;
        $isOwner = $role?->isOwner() ?? false;
        $ownerCount = $workspace->members()
            ->where('role', WorkspaceRole::Owner->value)
            ->count();

        $isSoleOwner = $isOwner && $ownerCount <= 1;

        return [
            'can_manage_workspace' => $role?->canManageWorkspace() ?? false,
            'can_manage_team' => $canManageTeam,
            'can_invite' => $canManageTeam,
            'can_update_roles' => $canManageTeam,
            'can_remove_members' => $canManageTeam,
            'can_leave' => $role !== null && ! $isSoleOwner,
        ];
    }
}
