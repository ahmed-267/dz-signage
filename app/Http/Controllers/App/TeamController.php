<?php

namespace App\Http\Controllers\App;

use App\Actions\Workspaces\InviteWorkspaceMember;
use App\Actions\Workspaces\LeaveWorkspace;
use App\Actions\Workspaces\RemoveWorkspaceMember;
use App\Actions\Workspaces\UpdateWorkspaceMemberRole;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\InviteWorkspaceMemberRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceMemberRoleRequest;
use App\Mail\WorkspaceInvitationMail;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Support\Billing\BillingEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('view', $workspace);

        $members = $workspace->members()
            ->with('user:id,name,email')
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->orderBy('created_at')
            ->get()
            ->map(fn (WorkspaceMember $member) => [
                'id' => $member->id,
                'type' => 'member',
                'user_id' => $member->user_id,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value,
                'role_label' => $member->role->label(),
                'status' => 'active',
                'joined_at' => $member->created_at?->toIso8601String(),
                'is_self' => $member->user_id === $request->user()->id,
            ]);

        $invitations = $workspace->invitations()
            ->pending()
            ->with('inviter:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WorkspaceInvitation $invitation) => [
                'id' => $invitation->id,
                'type' => 'invitation',
                'user_id' => null,
                'name' => null,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'status' => 'pending',
                'joined_at' => $invitation->created_at?->toIso8601String(),
                'is_self' => false,
            ]);

        $billing = BillingEntitlement::summary($workspace);
        $rows = $members->concat($invitations)->values();

        return Inertia::render('app/team', [
            'members' => $rows,
            'member_count' => $rows->count(),
            'member_limit' => $billing['team_limit'],
            'plan_name' => $billing['plan_name'],
            'assignableRoles' => collect(WorkspaceRole::assignable())->map(fn ($role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ])->values(),
            'roleDescriptions' => collect([WorkspaceRole::Owner, ...WorkspaceRole::assignable()])->map(fn ($role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
            ])->values(),
        ]);
    }

    public function invite(InviteWorkspaceMemberRequest $request, InviteWorkspaceMember $invite): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);

        $invite->handle(
            $request->user(),
            $workspace,
            $request->validated('email'),
            WorkspaceRole::from($request->validated('role')),
        );

        return back()->with('success', 'Invitation sent.');
    }

    public function updateRole(
        UpdateWorkspaceMemberRoleRequest $request,
        WorkspaceMember $member,
        UpdateWorkspaceMemberRole $updateRole,
    ): RedirectResponse {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('updateMember', [$workspace, $member]);

        $updateRole->handle(
            $request->user(),
            $workspace,
            $member,
            WorkspaceRole::from($request->validated('role')),
        );

        return back()->with('success', 'Member role updated.');
    }

    public function destroy(Request $request, WorkspaceMember $member, RemoveWorkspaceMember $remove): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('removeMember', [$workspace, $member]);

        $remove->handle($request->user(), $workspace, $member);

        return back()->with('success', 'Member removed.');
    }

    public function leave(Request $request, LeaveWorkspace $leave): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('leave', $workspace);

        $leave->handle($request->user(), $workspace);

        if (! $request->user()->fresh()->hasWorkspaceMemberships()) {
            return redirect()->route('onboarding.show');
        }

        return redirect()->route('app.dashboard')->with('success', 'You left the workspace.');
    }

    public function resendInvitation(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('invite', $workspace);

        abort_unless($invitation->workspace_id === $workspace->id, 404);
        abort_unless($invitation->isPending() || $invitation->isExpired(), 422);

        $plainToken = WorkspaceInvitation::generatePlainToken();
        $invitation->forceFill([
            'token_hash' => WorkspaceInvitation::hashToken($plainToken),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'accepted_at' => null,
        ])->save();

        Mail::to($invitation->email)->send(new WorkspaceInvitationMail($invitation, $plainToken));

        return back()->with('success', 'Invitation resent.');
    }

    public function revokeInvitation(Request $request, WorkspaceInvitation $invitation): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('invite', $workspace);

        abort_unless($invitation->workspace_id === $workspace->id, 404);

        if ($invitation->isAccepted()) {
            return back()->with('error', 'Invitation already accepted.');
        }

        $invitation->forceFill(['revoked_at' => now()])->save();

        return back()->with('success', 'Invitation cancelled.');
    }
}
