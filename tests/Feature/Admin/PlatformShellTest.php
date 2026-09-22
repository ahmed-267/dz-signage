<?php

use App\Enums\PlatformRole;
use App\Enums\WorkspaceRole;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;

test('business admin label is Admin and stays distinct from the platform role', function () {
    expect(WorkspaceRole::Admin->label())->toBe('Admin')
        ->and(WorkspaceRole::Admin->value)->toBe('admin')
        ->and(PlatformRole::PlatformAdmin->label())->toBe('Admin')
        ->and(PlatformRole::PlatformAdmin->value)->toBe('platform_admin')
        ->and(PlatformRole::SuperAdmin->label())->toBe('Super Admin');
});

test('sidebar-driving permissions hide display tools for designer and viewer', function () {
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Designer);

    $designer = WorkspacePermissions::for(WorkspaceRole::Designer, $user, $workspace);
    expect($designer['can_manage_playlists'])->toBeFalse()
        ->and($designer['can_manage_schedules'])->toBeFalse()
        ->and($designer['can_publish_content'])->toBeFalse()
        ->and($designer['can_manage_screens'])->toBeFalse()
        ->and($designer['can_manage_locations'])->toBeFalse()
        ->and($designer['can_manage_screen_designs'])->toBeTrue();

    $viewer = WorkspacePermissions::for(WorkspaceRole::Viewer, $user, $workspace);
    expect($viewer['can_manage_playlists'])->toBeFalse()
        ->and($viewer['can_publish_content'])->toBeFalse()
        ->and($viewer['can_manage_screens'])->toBeFalse()
        ->and($viewer['can_manage_screen_designs'])->toBeFalse();

    $content = WorkspacePermissions::for(WorkspaceRole::ContentManager, $user, $workspace);
    expect($content['can_manage_playlists'])->toBeTrue()
        ->and($content['can_publish_content'])->toBeTrue()
        ->and($content['can_manage_screens'])->toBeFalse()
        ->and($content['can_manage_locations'])->toBeFalse();

    $location = WorkspacePermissions::for(WorkspaceRole::LocationManager, $user, $workspace);
    expect($location['can_manage_screens'])->toBeTrue()
        ->and($location['can_manage_locations'])->toBeTrue()
        ->and($location['can_publish_content'])->toBeFalse()
        ->and($location['can_manage_playlists'])->toBeFalse();
});

test('workspace roles have meaningfully different permissions', function () {
    $owner = WorkspaceRole::Owner;
    $admin = WorkspaceRole::Admin;
    $designer = WorkspaceRole::Designer;
    $content = WorkspaceRole::ContentManager;
    $location = WorkspaceRole::LocationManager;
    $viewer = WorkspaceRole::Viewer;

    expect($owner->canManageTeam())->toBeTrue()
        ->and($owner->canManageBilling())->toBeTrue()
        ->and($owner->canManageMedia())->toBeTrue()
        ->and($owner->canManageScreenDesigns())->toBeTrue()
        ->and($owner->canManageTemplates())->toBeFalse();

    expect($admin->canManageTeam())->toBeTrue()
        ->and($admin->canManageWorkspace())->toBeTrue()
        ->and($admin->canManageBilling())->toBeFalse()
        ->and($admin->canViewBilling())->toBeTrue()
        ->and($admin->canManageTemplates())->toBeFalse();

    expect($designer->canManageTeam())->toBeFalse()
        ->and($designer->canManageBilling())->toBeFalse()
        ->and($designer->canManageMedia())->toBeTrue()
        ->and($designer->canManageScreenDesigns())->toBeTrue()
        ->and($designer->canManagePlaylists())->toBeFalse()
        ->and($designer->canManageTemplates())->toBeFalse()
        ->and($designer->description())->toContain('Templates');

    expect($content->canManageMedia())->toBeTrue()
        ->and($content->canManagePlaylists())->toBeTrue()
        ->and($content->canPublishContent())->toBeTrue()
        ->and($content->canManageTeam())->toBeFalse()
        ->and($content->canManageTemplates())->toBeFalse();

    expect($location->canManageScreens())->toBeTrue()
        ->and($location->canManageMedia())->toBeFalse()
        ->and($location->canManageTeam())->toBeFalse()
        ->and($location->canManageBilling())->toBeFalse()
        ->and($location->canManageTemplates())->toBeFalse();

    expect($viewer->canManageMedia())->toBeFalse()
        ->and($viewer->canDeleteMedia())->toBeFalse()
        ->and($viewer->canManageTeam())->toBeFalse()
        ->and($viewer->canManageWorkspace())->toBeFalse()
        ->and($viewer->canViewTemplates())->toBeTrue()
        ->and($viewer->canManageScreenDesigns())->toBeFalse();
});

test('workspace permissions payload reflects role differences', function () {
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Designer);
    $perms = WorkspacePermissions::for(WorkspaceRole::Designer, $user, $workspace);

    expect($perms['can_manage_media'])->toBeTrue()
        ->and($perms['can_manage_team'])->toBeFalse()
        ->and($perms['can_view_billing'])->toBeFalse()
        ->and($perms['can_manage_templates'])->toBeFalse()
        ->and($perms['can_manage_screen_designs'])->toBeTrue()
        ->and($perms['can_manage_playlists'])->toBeFalse();

    $viewerPerms = WorkspacePermissions::for(WorkspaceRole::Viewer, $user, $workspace);
    expect($viewerPerms['can_manage_media'])->toBeFalse()
        ->and($viewerPerms['can_delete_media'])->toBeFalse()
        ->and($viewerPerms['can_invite'])->toBeFalse();
});

test('platform staff can access admin overview with real metrics', function () {
    $admin = User::factory()->admin()->create();
    attachWorkspace($admin);

    Workspace::factory()->count(2)->create();
    Template::factory()->platform()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('metrics.workspaces', Workspace::query()->count())
            ->where('metrics.users', User::query()->count())
            ->where('metrics.templates', Template::query()->platform()->count())
            ->has('platform_role_label'));
});

test('platform admin can access admin shell routes', function () {
    $platformAdmin = User::factory()->platformAdmin()->create();
    attachWorkspace($platformAdmin);

    expect($platformAdmin->isPlatformStaff())->toBeTrue()
        ->and($platformAdmin->isSuperAdmin())->toBeFalse();

    $this->actingAs($platformAdmin)
        ->get(route('admin.home'))
        ->assertOk();

    $this->actingAs($platformAdmin)
        ->get(route('admin.support'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/support/index'));

    $this->actingAs($platformAdmin)
        ->get(route('admin.feature_flags'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/feature-flags/index'));
});

test('workspace-only roles cannot access admin routes', function (WorkspaceRole $role) {
    $user = User::factory()->create();
    attachWorkspace($user, null, $role);

    expect($user->isPlatformStaff())->toBeFalse();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('admin.templates'))
        ->assertForbidden();
})->with([
    WorkspaceRole::Owner,
    WorkspaceRole::Admin,
    WorkspaceRole::Designer,
    WorkspaceRole::ContentManager,
    WorkspaceRole::LocationManager,
    WorkspaceRole::Viewer,
]);

test('assigning platform role does not depend on workspace admin role', function () {
    $user = User::factory()->create();
    attachWorkspace($user, null, WorkspaceRole::Admin);

    expect($user->fresh()->canManagePlatformTemplates())->toBeFalse();

    $user->assignPlatformRole(PlatformRole::PlatformAdmin);

    expect($user->fresh()->canManagePlatformTemplates())->toBeTrue()
        ->and($user->fresh()->isSuperAdmin())->toBeFalse()
        ->and($user->fresh()->is_admin)->toBeFalse();
});
