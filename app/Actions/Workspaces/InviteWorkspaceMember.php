<?php

namespace App\Actions\Workspaces;

use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class InviteWorkspaceMember
{
    public function handle(User $actor, Workspace $workspace, string $email, WorkspaceRole $role): WorkspaceInvitation
    {
        if ($role === WorkspaceRole::Owner) {
            throw ValidationException::withMessages([
                'role' => 'Cannot invite someone as Owner.',
            ]);
        }

        $email = strtolower(trim($email));

        if ($workspace->members()->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [$email]))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }

        $pending = $workspace->invitations()
            ->pending()
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($pending) {
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email.',
            ]);
        }

        $plainToken = WorkspaceInvitation::generatePlainToken();

        $invitation = WorkspaceInvitation::query()->create([
            'workspace_id' => $workspace->id,
            'email' => $email,
            'role' => $role,
            'token_hash' => WorkspaceInvitation::hashToken($plainToken),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new WorkspaceInvitationMail($invitation, $plainToken));

        return $invitation;
    }
}
