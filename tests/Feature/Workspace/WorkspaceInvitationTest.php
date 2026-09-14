<?php

use App\Enums\WorkspaceRole;
use App\Mail\WorkspaceInvitationMail;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function ownedWorkspace(User $user): Workspace
{
    $workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    return $workspace;
}

test('owner can invite a team member', function () {
    Mail::fake();

    $owner = User::factory()->create();
    ownedWorkspace($owner);

    $this->actingAs($owner)
        ->post(route('app.team.invite'), [
            'email' => 'colleague@example.com',
            'role' => WorkspaceRole::Designer->value,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('workspace_invitations', [
        'email' => 'colleague@example.com',
        'role' => WorkspaceRole::Designer->value,
    ]);

    Mail::assertSent(WorkspaceInvitationMail::class);
});

test('existing user can accept a valid invitation', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $plain = WorkspaceInvitation::generatePlainToken();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'role' => WorkspaceRole::Viewer,
        'token_hash' => WorkspaceInvitation::hashToken($plain),
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $plain))
        ->assertRedirect(route('app.dashboard'));

    $this->assertDatabaseHas('workspace_members', [
        'workspace_id' => $workspace->id,
        'user_id' => $invitee->id,
        'role' => WorkspaceRole::Viewer->value,
    ]);
});

test('invitation email must match authenticated user', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $other = User::factory()->create(['email' => 'other@example.com']);

    $plain = WorkspaceInvitation::generatePlainToken();
    WorkspaceInvitation::factory()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'token_hash' => WorkspaceInvitation::hashToken($plain),
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($other)
        ->post(route('invitations.accept', $plain))
        ->assertSessionHasErrors('invitation');
});

test('expired invitation cannot be accepted', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $plain = WorkspaceInvitation::generatePlainToken();
    WorkspaceInvitation::factory()->expired()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'token_hash' => WorkspaceInvitation::hashToken($plain),
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $plain))
        ->assertSessionHasErrors('invitation');
});

test('revoked invitation cannot be accepted', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $plain = WorkspaceInvitation::generatePlainToken();
    WorkspaceInvitation::factory()->revoked()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'token_hash' => WorkspaceInvitation::hashToken($plain),
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $plain))
        ->assertSessionHasErrors('invitation');
});

test('accepted invitation cannot be accepted twice', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);

    $plain = WorkspaceInvitation::generatePlainToken();
    WorkspaceInvitation::factory()->accepted()->create([
        'workspace_id' => $workspace->id,
        'email' => 'invitee@example.com',
        'token_hash' => WorkspaceInvitation::hashToken($plain),
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->post(route('invitations.accept', $plain))
        ->assertSessionHasErrors('invitation');
});

test('owner can update member role and remove member', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $memberUser = User::factory()->create();
    $membership = WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $memberUser->id,
        'role' => WorkspaceRole::Viewer,
    ]);

    $this->actingAs($owner)
        ->patch(route('app.team.members.update', $membership), [
            'role' => WorkspaceRole::Designer->value,
        ])
        ->assertRedirect();

    expect($membership->fresh()->role)->toBe(WorkspaceRole::Designer);

    $this->actingAs($owner)
        ->delete(route('app.team.members.destroy', $membership))
        ->assertRedirect();

    $this->assertDatabaseMissing('workspace_members', [
        'id' => $membership->id,
    ]);
});

test('non-owner member can leave workspace', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $member = User::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
        'role' => WorkspaceRole::Designer,
    ]);
    $member->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($member)
        ->post(route('app.team.leave'))
        ->assertRedirect(route('onboarding.show'));

    $this->assertDatabaseMissing('workspace_members', [
        'workspace_id' => $workspace->id,
        'user_id' => $member->id,
    ]);
});

test('owner can update workspace settings', function () {
    $owner = User::factory()->create();
    ownedWorkspace($owner);

    $this->actingAs($owner)
        ->post(route('app.workspace_settings.update'), [
            'name' => 'Updated Name',
            'industry' => 'education',
            'country' => 'Canada',
            'timezone' => 'America/Toronto',
        ])
        ->assertRedirect(route('app.workspace_settings.edit'));

    expect($owner->fresh()->currentWorkspace->name)->toBe('Updated Name')
        ->and($owner->fresh()->currentWorkspace->timezone)->toBe('America/Toronto');
});

test('viewer cannot update workspace settings', function () {
    $owner = User::factory()->create();
    $workspace = ownedWorkspace($owner);
    $viewer = User::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $viewer->id,
        'role' => WorkspaceRole::Viewer,
    ]);
    $viewer->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($viewer)
        ->post(route('app.workspace_settings.update'), [
            'name' => 'Hacked',
            'industry' => 'education',
            'country' => 'Canada',
            'timezone' => 'America/Toronto',
        ])
        ->assertForbidden();
});
