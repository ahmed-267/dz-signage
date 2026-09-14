<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveWorkspace
{
    public function handle(User $user, Workspace $workspace): void
    {
        $membership = $user->membershipFor($workspace);

        if (! $membership) {
            throw ValidationException::withMessages([
                'workspace' => 'You are not a member of this workspace.',
            ]);
        }

        if ($membership->role === WorkspaceRole::Owner) {
            $ownerCount = $workspace->members()
                ->where('role', WorkspaceRole::Owner->value)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'workspace' => 'The sole Owner cannot leave the workspace.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $membership, $workspace) {
            $membership->delete();

            if ((int) $user->current_workspace_id === (int) $workspace->id) {
                $next = $user->workspaceMemberships()->first();
                $user->forceFill([
                    'current_workspace_id' => $next?->workspace_id,
                ])->save();
            }
        });
    }
}
