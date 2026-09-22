<?php

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\WorkspaceRole;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

function plDeployUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

/**
 * @return array{0: Screen, 1: string}
 */
function plDeployScreen(User $user, Workspace $workspace, string $name = 'Lobby TV'): array
{
    $screen = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create([
            'name' => $name,
            'orientation' => 'landscape',
            'operational_status' => ScreenOperationalStatus::Active,
        ]);

    $token = Str::random(64);
    ScreenDevice::factory()->forScreen($screen)->withToken($token)->create();

    return [$screen, $token];
}

function plDeployDesign(User $user, Workspace $workspace, string $name = 'Lobby Design'): ScreenDesign
{
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->landscape()
        ->withDraftVersion($user)
        ->create(['name' => $name]);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

function plDeployPlaylist(User $user, Workspace $workspace, ScreenDesign $design, string $name = 'Lobby Loop'): Playlist
{
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name]);

    test()->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 11]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    test()->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $playlist->fresh(['publishedVersion.items']) ?? $playlist;
}

test('a screen design deployment supersedes a live playlist deployment', function () {
    [$user, $workspace] = plDeployUser();
    [$screen] = plDeployScreen($user, $workspace);
    $design = plDeployDesign($user, $workspace);
    $playlist = plDeployPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $playlistDeployment = $screen->fresh()->activeDeployment();

    expect($playlistDeployment->content_type)->toBe(DeploymentContentType::Playlist)
        ->and($playlistDeployment->contentOrientation())->toBe('landscape');

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $design), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $designDeployment = $screen->fresh()->activeDeployment();

    expect($playlistDeployment->fresh()->status)->toBe(DeploymentStatus::Superseded)
        ->and($playlistDeployment->fresh()->superseded_at)->not->toBeNull()
        ->and($designDeployment->content_type)->toBe(DeploymentContentType::ScreenDesign)
        ->and($designDeployment->screen_design_id)->toBe($design->id)
        ->and($designDeployment->playlist_id)->toBeNull()
        ->and($designDeployment->playlist_version_id)->toBeNull();
});

test('a playlist cannot be deployed to a screen from another workspace', function () {
    [$user, $workspace] = plDeployUser();
    [$otherUser, $otherWorkspace] = plDeployUser();
    [$foreignScreen] = plDeployScreen($otherUser, $otherWorkspace, 'Foreign TV');

    $design = plDeployDesign($user, $workspace);
    $playlist = plDeployPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$foreignScreen->id]])
        ->assertSessionHasErrors('screen_ids');

    expect($foreignScreen->fresh()->activeDeployment())->toBeNull();
});

test('content manager can deploy a playlist while location manager cannot', function () {
    [$owner, $workspace] = plDeployUser();
    $contentManager = User::factory()->create();
    $locationManager = User::factory()->create();
    attachWorkspace($contentManager, $workspace, WorkspaceRole::ContentManager);
    attachWorkspace($locationManager, $workspace, WorkspaceRole::LocationManager);

    [$screen] = plDeployScreen($owner, $workspace);
    $design = plDeployDesign($owner, $workspace);
    $playlist = plDeployPlaylist($owner, $workspace, $design);

    $this->actingAs($locationManager)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertForbidden();

    expect($screen->fresh()->activeDeployment())->toBeNull();

    $this->actingAs($contentManager)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    expect($screen->fresh()->activeDeployment()->content_type)
        ->toBe(DeploymentContentType::Playlist);
});

test('a deployed playlist whose items all went inactive reports no_content', function () {
    [$user, $workspace] = plDeployUser();
    [$screen, $token] = plDeployScreen($user, $workspace);
    $design = plDeployDesign($user, $workspace);
    $playlist = plDeployPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'ready']);

    $playlist->publishedVersion->items()->update(['is_active' => false]);

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'no_content']);
});

test('the screens surfaces name the playlist that is live on a screen', function () {
    [$user, $workspace] = plDeployUser();
    [$screen] = plDeployScreen($user, $workspace);
    $design = plDeployDesign($user, $workspace);
    $playlist = plDeployPlaylist($user, $workspace, $design, 'Lobby Loop');

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('app.screens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('screens.0.content_type', 'playlist')
            ->where('screens.0.content_name', 'Lobby Loop')
            ->where('screens.0.current_design_name', 'Lobby Loop')
        );

    $this->actingAs($user)
        ->get(route('app.screens.show', $screen))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('screen.current_deployment.content_type', 'playlist')
            ->where('screen.current_deployment.content_name', 'Lobby Loop')
            ->where('screen.current_deployment.playlist_item_count', 1)
            ->where('screen.deployments.0.content_name', 'Lobby Loop')
        );
});

test('an inactive screen returns an inactive manifest even with a playlist deployed', function () {
    [$user, $workspace] = plDeployUser();
    [$screen, $token] = plDeployScreen($user, $workspace);
    $design = plDeployDesign($user, $workspace);
    $playlist = plDeployPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $screen->forceFill(['operational_status' => ScreenOperationalStatus::Inactive])->save();

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'inactive']);

    $this->withToken($token)
        ->getJson(route('player.api.manifest.check'))
        ->assertOk()
        ->assertJson(['screen_active' => false]);
});
