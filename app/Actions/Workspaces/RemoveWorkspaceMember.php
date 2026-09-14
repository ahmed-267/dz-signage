<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveWorkspaceMember
{
    public function handle(User $actor, Workspace $workspace, WorkspaceMember $member): void
    {
        if ($member->workspace_id !== $workspace->id) {
            abort(404);
        }

        if ($member->role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'member' => 'The Owner cannot be removed.',
            ]);
        }

        if ($member->user_id === $actor->id) {
            throw ValidationException::withMessages([
                'member' => 'Use leave workspace to remove yourself.',
            ]);
        }

        DB::transaction(function () use ($member) {
            $user = $member->user;
            $workspaceId = $member->workspace_id;
            $member->delete();

            if ($user && (int) $user->current_workspace_id === (int) $workspaceId) {
                $next = $user->workspaceMemberships()->first();
                $user->forceFill([
                    'current_workspace_id' => $next?->workspace_id,
                ])->save();
            }
        });
    }
}
