<?php

use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createWorkspaceFor(User $user, array $attrs = [], WorkspaceRole $role = WorkspaceRole::Owner): Workspace
{
    $workspace = Workspace::factory()->create($attrs);

    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    $user->forceFill(['current_workspace_id' => $workspace->id])->save();

    return $workspace;
}

test('verified user without workspace is redirected to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

test('user can create a workspace and become owner', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('onboarding.store'), [
        'name' => 'Community Hub',
        'industry' => WorkspaceIndustry::Community->value,
        'country' => 'United Kingdom',
        'timezone' => 'Europe/London',
    ]);

    $response->assertRedirect(route('app.dashboard'));

    $workspace = Workspace::query()->where('name', 'Community Hub')->first();
    expect($workspace)->not->toBeNull()
        ->and($workspace->timezone)->toBe('Europe/London')
        ->and($workspace->industry)->toBe(WorkspaceIndustry::Community);

    $this->assertDatabaseHas('workspace_members', [
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner->value,
    ]);

    expect($user->fresh()->current_workspace_id)->toBe($workspace->id);
});

test('user can create multiple workspaces and switch between them', function () {
    $user = User::factory()->create();
    $a = createWorkspaceFor($user, ['name' => 'Workspace A']);
    $b = Workspace::factory()->create(['name' => 'Workspace B']);
    WorkspaceMember::factory()->create([
        'workspace_id' => $b->id,
        'user_id' => $user->id,
        'role' => WorkspaceRole::Owner,
    ]);

    $this->actingAs($user)
        ->post(route('app.workspaces.switch', $b))
        ->assertRedirect();

    expect($user->fresh()->current_workspace_id)->toBe($b->id);

    $this->actingAs($user)
        ->post(route('app.workspaces.switch', $a))
        ->assertRedirect();

    expect($user->fresh()->current_workspace_id)->toBe($a->id);
});

test('user cannot switch to a workspace they do not belong to', function () {
    $user = User::factory()->create();
    createWorkspaceFor($user);
    $other = Workspace::factory()->create();

    $this->actingAs($user)
        ->post(route('app.workspaces.switch', $other))
        ->assertForbidden();
});

test('user cannot access another workspace team page via current workspace isolation', function () {
    $ownerA = User::factory()->create();
    $workspaceA = createWorkspaceFor($ownerA, ['name' => 'A']);

    $ownerB = User::factory()->create();
    createWorkspaceFor($ownerB, ['name' => 'B']);

    $this->actingAs($ownerB)
        ->get(route('app.team'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/team')
            ->where('members.0.email', $ownerB->email)
            ->etc()
        );

    expect($ownerB->fresh()->current_workspace_id)->not->toBe($workspaceA->id);
});

test('viewer cannot invite team members', function () {
    $owner = User::factory()->create();
    $workspace = createWorkspaceFor($owner);

    $viewer = User::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $viewer->id,
        'role' => WorkspaceRole::Viewer,
    ]);
    $viewer->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($viewer)
        ->post(route('app.team.invite'), [
            'email' => 'new@example.com',
            'role' => WorkspaceRole::Designer->value,
        ])
        ->assertForbidden();
});

test('admin cannot change or remove the owner', function () {
    $owner = User::factory()->create();
    $workspace = createWorkspaceFor($owner);
    $ownerMembership = $owner->membershipFor($workspace);

    $admin = User::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $admin->id,
        'role' => WorkspaceRole::Admin,
    ]);
    $admin->forceFill(['current_workspace_id' => $workspace->id])->save();

    $this->actingAs($admin)
        ->patch(route('app.team.members.update', $ownerMembership), [
            'role' => WorkspaceRole::Admin->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->delete(route('app.team.members.destroy', $ownerMembership))
        ->assertForbidden();
});

test('sole owner cannot leave workspace', function () {
    $owner = User::factory()->create();
    createWorkspaceFor($owner);

    $this->actingAs($owner)
        ->post(route('app.team.leave'))
        ->assertForbidden();
});

test('platform super admin can view admin workspaces and users', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    createWorkspaceFor($owner, ['name' => 'Visible Co']);

    $this->actingAs($admin)
        ->get(route('admin.workspaces'))
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk();
});

test('workspace owner without platform admin cannot access super admin', function () {
    $owner = User::factory()->create();
    createWorkspaceFor($owner);

    $this->actingAs($owner)
        ->get(route('admin.workspaces'))
        ->assertForbidden();
});
