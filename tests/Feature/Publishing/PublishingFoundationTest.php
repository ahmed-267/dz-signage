<?php

use App\Actions\Deployments\PublishContentToScreens;
use App\Actions\Playlists\CreatePlaylist;
use App\Actions\Playlists\PublishPlaylist;
use App\Actions\Playlists\SavePlaylistDraft;
use App\Actions\ScreenDesigns\CreateBlankScreenDesign;
use App\Actions\ScreenDesigns\PublishScreenDesign;
use App\Actions\ScreenDesigns\SaveScreenDesignDraft;
use App\Enums\DeploymentStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\TemplateOrientation;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

function phase9User(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $workspace = Workspace::factory()->create(['timezone' => 'Europe/London']);
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'current_workspace_id' => $workspace->id,
        'email_verified_at' => now(),
    ]);
    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => $role,
    ]);

    return [$user, $workspace];
}

function phase9PublishedDesign(User $user, Workspace $workspace, string $name = 'E2E Design'): ScreenDesign
{
    $design = app(CreateBlankScreenDesign::class)
        ->handle($user, $workspace, $name, TemplateOrientation::Landscape);
    app(PublishScreenDesign::class)->handle($user, $design);

    return $design->fresh(['publishedVersion']);
}

function phase9PublishedPlaylist(User $user, Workspace $workspace, ScreenDesign $design): Playlist
{
    $playlist = app(CreatePlaylist::class)
        ->handle($user, $workspace, 'E2E Pub Playlist');
    app(SavePlaylistDraft::class)->handle($user, $playlist, [
        'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 8]],
    ]);
    app(PublishPlaylist::class)->handle($user, $playlist);

    return $playlist->fresh(['publishedVersion']);
}

test('published design can be deployed to multiple screens and pins the version', function () {
    [$user, $workspace] = phase9User();
    $design = phase9PublishedDesign($user, $workspace);
    $screens = Screen::factory()->count(2)->create(['workspace_id' => $workspace->id]);
    $versionId = $design->published_version_id;

    $result = app(PublishContentToScreens::class)->publishDesign(
        $user,
        $workspace,
        $design,
        $screens->pluck('id')->all(),
    );

    expect($result['deployments'])->toHaveCount(2)
        ->and($result['deployments']->every(fn (Deployment $d) => $d->status === DeploymentStatus::Active))->toBeTrue()
        ->and($result['deployments']->every(fn (Deployment $d) => (int) $d->screen_design_version_id === (int) $versionId))->toBeTrue();

    // Later design publish must not alter the pinned deployment version.
    app(SaveScreenDesignDraft::class)->handle($user, $design, [
        'schema' => $design->latestVersion()->schema,
    ]);
    app(PublishScreenDesign::class)->handle($user, $design->fresh());

    expect($result['deployments']->first()->fresh()->screen_design_version_id)->toBe($versionId)
        ->and($design->fresh()->published_version_id)->not->toBe($versionId);
});

test('new direct publish supersedes the previous active deployment', function () {
    [$user, $workspace] = phase9User();
    $first = phase9PublishedDesign($user, $workspace, 'First');
    $second = phase9PublishedDesign($user, $workspace, 'Second');
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);
    $publisher = app(PublishContentToScreens::class);

    $a = $publisher->publishDesign($user, $workspace, $first, [$screen->id])['deployments']->first();
    $b = $publisher->publishDesign($user, $workspace, $second, [$screen->id])['deployments']->first();

    expect($a->fresh()->status)->toBe(DeploymentStatus::Superseded)
        ->and($b->fresh()->status)->toBe(DeploymentStatus::Active)
        ->and($screen->fresh()->activeDeployment()->id)->toBe($b->id);
});

test('draft design cannot be published and foreign screens are rejected', function () {
    [$user, $workspace] = phase9User();
    $draft = app(CreateBlankScreenDesign::class)
        ->handle($user, $workspace, 'Draft Only', TemplateOrientation::Landscape);
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);
    $foreign = Screen::factory()->create();

    expect(fn () => app(PublishContentToScreens::class)->publishDesign($user, $workspace, $draft, [$screen->id]))
        ->toThrow(ValidationException::class);

    $published = phase9PublishedDesign($user, $workspace, 'Ok');
    expect(fn () => app(PublishContentToScreens::class)->publishDesign($user, $workspace, $published, [$foreign->id]))
        ->toThrow(ValidationException::class);
});

test('viewer cannot publish and content manager can', function () {
    [$viewer, $workspace] = phase9User(WorkspaceRole::Viewer);
    [$manager] = phase9User(WorkspaceRole::ContentManager);
    WorkspaceMember::query()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $manager->id,
        'role' => WorkspaceRole::ContentManager,
    ]);
    $manager->forceFill(['current_workspace_id' => $workspace->id])->save();

    $design = phase9PublishedDesign($manager, $workspace);
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);

    expect(fn () => app(PublishContentToScreens::class)->publishDesign($viewer, $workspace, $design, [$screen->id]))
        ->toThrow(ValidationException::class);

    $result = app(PublishContentToScreens::class)->publishDesign($manager, $workspace, $design, [$screen->id]);
    expect($result['deployments'])->toHaveCount(1);
});

test('republish creates a new deployment without mutating history', function () {
    [$user, $workspace] = phase9User();
    $design = phase9PublishedDesign($user, $workspace);
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);
    $publisher = app(PublishContentToScreens::class);

    $original = $publisher->publishDesign($user, $workspace, $design, [$screen->id])['deployments']->first();
    $pinnedVersion = $original->screen_design_version_id;
    $replacement = phase9PublishedDesign($user, $workspace, 'Replacement');
    $publisher->publishDesign($user, $workspace, $replacement, [$screen->id]);

    expect($original->fresh()->status)->toBe(DeploymentStatus::Superseded);

    $republished = $publisher->republish($user, $workspace, $original->fresh())['deployments']->first();

    expect($original->fresh()->status)->toBe(DeploymentStatus::Superseded)
        ->and($republished->id)->not->toBe($original->id)
        ->and($republished->status)->toBe(DeploymentStatus::Active)
        ->and($republished->screen_design_version_id)->toBe($pinnedVersion)
        ->and($original->fresh()->screen_design_version_id)->toBe($pinnedVersion);
});

test('publishing page is available to workspace members', function () {
    [$user, $workspace] = phase9User();
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);
    $design = phase9PublishedDesign($user, $workspace);
    app(PublishContentToScreens::class)->publishDesign($user, $workspace, $design, [$screen->id]);

    $this->actingAs($user)
        ->get(route('app.publishing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/publishing/index')
            ->has('live', 1)
            ->has('history.data', 1)
            ->where('can_publish', true)
        );
});

test('playlist publish pins playlist version via unified action', function () {
    [$user, $workspace] = phase9User();
    $design = phase9PublishedDesign($user, $workspace);
    $playlist = phase9PublishedPlaylist($user, $workspace, $design);
    $screen = Screen::factory()->create(['workspace_id' => $workspace->id]);
    $versionId = $playlist->published_version_id;

    $deployment = app(PublishContentToScreens::class)
        ->publishPlaylist($user, $workspace, $playlist, [$screen->id])['deployments']
        ->first();

    expect($deployment->playlist_version_id)->toBe($versionId)
        ->and($deployment->isPlaylist())->toBeTrue();
});

test('inactive screen publish returns a warning but still deploys', function () {
    [$user, $workspace] = phase9User();
    $design = phase9PublishedDesign($user, $workspace);
    $screen = Screen::factory()->create([
        'workspace_id' => $workspace->id,
        'operational_status' => ScreenOperationalStatus::Inactive,
    ]);

    $result = app(PublishContentToScreens::class)->publishDesign($user, $workspace, $design, [$screen->id]);

    expect($result['deployments'])->toHaveCount(1)
        ->and($result['warnings'])->not->toBeEmpty();
});
