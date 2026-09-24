<?php

use App\Enums\DeploymentStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Onboarding\ProductOnboarding;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create();
    WorkspaceMember::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'role' => WorkspaceRole::Owner,
    ]);
    $this->owner->forceFill(['current_workspace_id' => $this->workspace->id])->save();
});

test('paired tvs list exposes resolver-backed now showing for a published screen', function () {
    $design = ScreenDesign::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Morning Board',
        'status' => ScreenDesignStatus::Published,
    ]);
    $version = ScreenDesignVersion::factory()->create([
        'screen_design_id' => $design->id,
        'version_number' => 1,
    ]);
    $design->forceFill(['published_version_id' => $version->id])->save();

    $tv = Screen::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Counter TV',
    ]);

    Deployment::factory()->create([
        'workspace_id' => $this->workspace->id,
        'screen_id' => $tv->id,
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $version->id,
        'status' => DeploymentStatus::Active,
        'deployed_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->get(route('app.screens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/screens/index')
            ->has('screens', 1)
            ->where('screens.0.now_showing.content_name', 'Morning Board')
            ->where('screens.0.now_showing.content_type', 'screen_design')
            ->where('screens.0.now_showing.content_source', 'deployment'));

    $this->actingAs($this->owner)
        ->getJson(route('app.screens.now_showing_preview', $tv))
        ->assertOk()
        ->assertJsonPath('kind', 'screen')
        ->assertJsonPath('content_name', 'Morning Board')
        ->assertJsonPath('source_label', 'Source: Direct Publish (Screen)')
        ->assertJsonPath('items.0.name', 'Morning Board');
});

test('product onboarding can start skip and restart for a new owner', function () {
    $onboarding = app(ProductOnboarding::class);

    expect($onboarding->sharedPayload(
        $this->owner->fresh(),
        $this->workspace,
        WorkspaceRole::Owner,
    )['auto_start'])->toBeTrue();

    $this->actingAs($this->owner)
        ->post(route('app.product_onboarding.update'), ['action' => 'start', 'step' => 1])
        ->assertRedirect();

    expect($this->owner->fresh()->onboarding_started_at)->not->toBeNull();

    $this->actingAs($this->owner)
        ->post(route('app.product_onboarding.update'), ['action' => 'skip'])
        ->assertRedirect();

    expect($this->owner->fresh()->onboarding_skipped_at)->not->toBeNull()
        ->and($onboarding->sharedPayload(
            $this->owner->fresh(),
            $this->workspace,
            WorkspaceRole::Owner,
        )['auto_start'])->toBeFalse();

    $this->actingAs($this->owner)
        ->post(route('app.product_onboarding.update'), ['action' => 'restart'])
        ->assertRedirect();

    $fresh = $this->owner->fresh();
    expect($fresh->onboarding_skipped_at)->toBeNull()
        ->and((int) $fresh->onboarding_step)->toBe(1)
        ->and($onboarding->sharedPayload(
            $fresh,
            $this->workspace,
            WorkspaceRole::Owner,
        )['active'])->toBeTrue();

    $this->actingAs($this->owner)
        ->post(route('app.product_onboarding.update'), ['action' => 'complete'])
        ->assertRedirect();

    $completedAt = $this->owner->fresh()->onboarding_completed_at;
    expect($completedAt)->not->toBeNull();

    $this->actingAs($this->owner)
        ->post(route('app.product_onboarding.update'), ['action' => 'restart'])
        ->assertRedirect();

    $replay = $this->owner->fresh();
    expect($replay->onboarding_completed_at?->equalTo($completedAt))->toBeTrue()
        ->and($onboarding->sharedPayload(
            $replay,
            $this->workspace,
            WorkspaceRole::Owner,
        )['active'])->toBeTrue();
});

test('admin customers hub redirects to businesses tab destination', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
    ]);
    $admin->forceFill(['platform_role' => 'super_admin', 'is_admin' => true])->save();

    $this->actingAs($admin)
        ->get('/admin/customers')
        ->assertRedirect('/admin/workspaces');

    $this->actingAs($admin)
        ->get('/admin/customers/users')
        ->assertRedirect('/admin/users');
});
