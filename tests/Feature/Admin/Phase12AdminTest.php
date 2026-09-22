<?php

use App\Enums\PlatformRole;
use App\Enums\SupportRequestStatus;
use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\AuditLog;
use App\Models\FeatureFlag;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PlatformOpsSeeder;

beforeEach(function () {
    $this->seed(PlatformOpsSeeder::class);
});

test('workspace owner cannot access admin', function () {
    $owner = User::factory()->create();
    attachWorkspace($owner, null, WorkspaceRole::Owner);

    $this->actingAs($owner)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('platform admin can access admin users workspaces and system health', function () {
    $admin = User::factory()->platformAdmin()->create();
    attachWorkspace($admin);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($admin)->get(route('admin.users'))->assertOk();
    $this->actingAs($admin)->get(route('admin.workspaces'))->assertOk();
    $this->actingAs($admin)
        ->get(route('admin.system_health'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system-health/index')
            ->has('checks')
            ->where('overall_status', fn ($status) => in_array($status, ['healthy', 'degraded', 'unavailable'], true)));
});

test('platform admin cannot update feature flags platform roles or settings', function () {
    $admin = User::factory()->platformAdmin()->create();
    attachWorkspace($admin);

    $flag = FeatureFlag::query()->where('key', 'widgets_enabled')->firstOrFail();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.feature_flags.update', $flag), ['enabled' => false])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.platform_role', $target), [
            'platform_role' => PlatformRole::PlatformAdmin->value,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.settings.update'), [
            'key' => 'platform_name',
            'value' => 'Hacked',
        ])
        ->assertForbidden();
});

test('super admin can update feature flag and creates audit', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    $flag = FeatureFlag::query()->where('key', 'widgets_enabled')->firstOrFail();

    $this->actingAs($super)
        ->patch(route('admin.feature_flags.update', $flag), ['enabled' => false])
        ->assertRedirect();

    expect($flag->fresh()->enabled)->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'feature_flag.updated',
        'entity_type' => 'feature_flag',
        'entity_id' => $flag->id,
        'actor_user_id' => $super->id,
    ]);
});

test('customer can create support request and admin can update status', function () {
    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);

    $this->actingAs($owner)
        ->post(route('app.help.store'), [
            'subject' => 'Screen offline',
            'category' => 'screens',
            'message' => 'Lobby screen stopped updating.',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $support = SupportRequest::query()->where('workspace_id', $workspace->id)->first();
    expect($support)->not->toBeNull()
        ->and($support->status)->toBe(SupportRequestStatus::Open);

    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    $this->actingAs($super)
        ->patch(route('admin.support.update', $support), [
            'status' => SupportRequestStatus::InProgress->value,
        ])
        ->assertRedirect();

    expect($support->fresh()->status)->toBe(SupportRequestStatus::InProgress);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'support_request.status_updated',
        'entity_id' => $support->id,
    ]);
});

test('audit log lists entries', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    AuditLog::factory()->create([
        'actor_user_id' => $super->id,
        'action' => 'test.listed',
        'entity_type' => 'user',
        'entity_id' => $super->id,
    ]);

    $this->actingAs($super)
        ->get(route('admin.audit_log'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audit-log/index')
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'test.listed'));
});

test('system health returns checks', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    $this->actingAs($super)
        ->get(route('admin.system_health'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/system-health/index')
            ->has('checks')
            ->where('checks', fn ($checks) => collect($checks)->pluck('key')->contains('database')));
});

test('billing subscriptions page returns billing unavailable', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    $this->actingAs($super)
        ->get(route('admin.subscriptions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/billing/subscriptions')
            ->where('billing_unavailable', true));
});

test('user show works', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);
    $user = User::factory()->create(['name' => 'Phase12 User']);
    attachWorkspace($user);

    $this->actingAs($super)
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/show')
            ->where('user.id', $user->id)
            ->where('user.name', 'Phase12 User')
            ->has('user.email_verified_at'));
});

test('workspace search works', function () {
    $super = User::factory()->admin()->create();
    attachWorkspace($super);

    Workspace::factory()->create([
        'name' => 'Alpha Search Co',
        'industry' => WorkspaceIndustry::Corporate,
    ]);
    Workspace::factory()->create([
        'name' => 'Beta Hidden',
        'industry' => WorkspaceIndustry::Retail,
    ]);

    $this->actingAs($super)
        ->get(route('admin.workspaces', ['q' => 'Alpha Search']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/workspaces/index')
            ->has('workspaces.data', 1)
            ->where('workspaces.data.0.name', 'Alpha Search Co')
            ->where('filters.q', 'Alpha Search'));
});
