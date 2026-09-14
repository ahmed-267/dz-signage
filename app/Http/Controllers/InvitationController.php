<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\AcceptWorkspaceInvitation;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace:id,name')
            ->where('token_hash', WorkspaceInvitation::hashToken($token))
            ->first();

        if (! $invitation) {
            return Inertia::render('invitations/invalid', [
                'reason' => 'invalid',
            ]);
        }

        if ($invitation->isAccepted()) {
            return Inertia::render('invitations/invalid', [
                'reason' => 'accepted',
            ]);
        }

        if ($invitation->isRevoked()) {
            return Inertia::render('invitations/invalid', [
                'reason' => 'revoked',
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('invitations/invalid', [
                'reason' => 'expired',
            ]);
        }

        return Inertia::render('invitations/show', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role->label(),
                'workspace' => $invitation->workspace?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'authenticated' => $request->user() !== null,
            'email_matches' => $request->user()
                ? strtolower($request->user()->email) === strtolower($invitation->email)
                : false,
        ]);
    }

    public function accept(Request $request, string $token, AcceptWorkspaceInvitation $accept): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $accept->handle($user, $token);

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Invitation accepted. Welcome to the workspace.');
    }
}
