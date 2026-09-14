<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Validation\ValidationException;

class UpdateWorkspaceMemberRole
{
    public function handle(User $actor, Workspace $workspace, WorkspaceMember $member, WorkspaceRole $role): WorkspaceMember
    {
        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Ownership transfer is not supported in Phase 1.',
            ]);
        }

        if ($member->role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'The Owner role cannot be changed.',
            ]);
        }

        if ($member->user_id === $actor->id) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own role.',
            ]);
        }

        $actorRole = $actor->roleIn($workspace);

        if ($actorRole === WorkspaceRole::Admin && $role === WorkspaceRole::Admin) {
            // Admin may assign admin — OK
        }

        $member->forceFill(['role' => $role])->save();

        return $member->fresh();
    }
}
