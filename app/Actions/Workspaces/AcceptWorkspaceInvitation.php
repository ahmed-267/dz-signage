<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptWorkspaceInvitation
{
    public function handle(User $user, string $plainToken): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->where('token_hash', WorkspaceInvitation::hashToken($plainToken))
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is invalid.',
            ]);
        }

        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has already been accepted.',
            ]);
        }

        if ($invitation->isRevoked()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has been revoked.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation has expired.',
            ]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation was sent to a different email address.',
            ]);
        }

        if ($user->belongsToWorkspace($invitation->workspace_id)) {
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $invitation;
        }

        return DB::transaction(function () use ($user, $invitation) {
            WorkspaceMember::query()->create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            if (! $user->current_workspace_id) {
                $user->forceFill([
                    'current_workspace_id' => $invitation->workspace_id,
                ])->save();
            }

            return $invitation->fresh();
        });
    }
}
