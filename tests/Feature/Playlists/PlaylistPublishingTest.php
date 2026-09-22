<?php

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\MediaType;
use App\Enums\PlaylistStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function publishingUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function publishingDesign(
    User $user,
    Workspace $workspace,
    string $name = 'Lobby Design',
    TemplateOrientation $orientation = TemplateOrientation::Landscape,
    ?array $schema = null,
): ScreenDesign {
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create([
            'name' => $name,
            'orientation' => $orientation,
            'canvas_width' => $orientation->canvasWidth(),
            'canvas_height' => $orientation->canvasHeight(),
        ]);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

/**
 * Playlist with a single published item, published and ready to deploy.
 */
function publishedPlaylist(User $user, Workspace $workspace, ScreenDesign $design, string $name = 'Lobby Loop'): Playlist
{
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name]);

    test()->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 7]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    test()->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    return $playlist->fresh(['publishedVersion']) ?? $playlist;
}

/**
 * @return array{0: Screen, 1: string}
 */
function playerScreenWithToken(User $user, Workspace $workspace, string $name = 'Lobby TV'): array
{
    $screen = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => $name, 'orientation' => 'landscape']);

    $token = Str::random(64);
    ScreenDevice::factory()->forScreen($screen)->withToken($token)->create();

    return [$screen, $token];
}

test('publishing a playlist requires at least one active item', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace);
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasErrors('playlist');

    // Only inactive items is still not publishable.
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'is_active' => false]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasErrors('playlist');

    expect($playlist->fresh()->status)->toBe(PlaylistStatus::Draft);
});

test('published playlist versions are immutable and later edits open a new draft version', function () {
    [$user, $workspace] = publishingUser();
    $first = publishingDesign($user, $workspace, 'Slide One');
    $second = publishingDesign($user, $workspace, 'Slide Two');
    $playlist = publishedPlaylist($user, $workspace, $first);

    $published = $playlist->publishedVersion;

    expect($playlist->status)->toBe(PlaylistStatus::Published)
        ->and($published)->not->toBeNull()
        ->and($published->published_at)->not->toBeNull()
        ->and($published->items)->toHaveCount(1)
        ->and($playlist->published_at)->not->toBeNull();

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $first->id, 'duration_seconds' => 7],
                ['screen_design_id' => $second->id, 'duration_seconds' => 30],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $playlist->refresh();
    $latest = $playlist->latestVersion();

    expect($published->fresh()->items)->toHaveCount(1)
        ->and($playlist->published_version_id)->toBe($published->id)
        ->and($latest->id)->not->toBe($published->id)
        ->and($latest->version_number)->toBe(2)
        ->and($latest->published_at)->toBeNull()
        ->and($latest->items)->toHaveCount(2);
});

test('publishing rejects items pinned to an unpublished design version', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace, 'Evolving');
    $draftVersion = $design->versions()->create([
        'version_number' => 2,
        'schema' => $design->publishedVersion->schema,
        'created_by' => $user->id,
        'published_at' => null,
    ]);

    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    PlaylistItem::query()->create([
        'playlist_version_id' => $playlist->latestVersion()->id,
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $draftVersion->id,
        'position' => 1,
        'duration_seconds' => 10,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasErrors('playlist');

    expect($playlist->fresh()->status)->toBe(PlaylistStatus::Draft);
});

test('only published playlists can be deployed and deployments pin the published version', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace);
    [$screen] = playerScreenWithToken($user, $workspace);

    $draft = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Draft Loop']);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $draft), ['screen_ids' => [$screen->id]])
        ->assertSessionHasErrors('playlist');

    $playlist = publishedPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $deployment = $screen->fresh()->activeDeployment();

    expect($deployment)->not->toBeNull()
        ->and($deployment->content_type)->toBe(DeploymentContentType::Playlist)
        ->and($deployment->playlist_id)->toBe($playlist->id)
        ->and($deployment->playlist_version_id)->toBe($playlist->published_version_id)
        ->and($deployment->screen_design_id)->toBeNull()
        ->and($deployment->screen_design_version_id)->toBeNull()
        ->and($deployment->status)->toBe(DeploymentStatus::Active);
});

test('publishing a playlist supersedes an existing screen design deployment', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace);
    [$screen] = playerScreenWithToken($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $design), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $designDeployment = $screen->fresh()->activeDeployment();

    expect($designDeployment->content_type)->toBe(DeploymentContentType::ScreenDesign)
        ->and($designDeployment->playlist_id)->toBeNull()
        ->and($designDeployment->playlist_version_id)->toBeNull();

    $playlist = publishedPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    expect($designDeployment->fresh()->status)->toBe(DeploymentStatus::Superseded)
        ->and($screen->fresh()->activeDeployment()->playlist_id)->toBe($playlist->id);
});

test('designer cannot deploy a playlist to screens', function () {
    [$owner, $workspace] = publishingUser();
    $designer = User::factory()->create();
    attachWorkspace($designer, $workspace, WorkspaceRole::Designer);

    $design = publishingDesign($owner, $workspace);
    $playlist = publishedPlaylist($owner, $workspace, $design);
    [$screen] = playerScreenWithToken($owner, $workspace);

    $this->actingAs($designer)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertForbidden();

    expect($screen->fresh()->activeDeployment())->toBeNull();
});

test('a playlist cannot be deleted while it is live on a screen', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace);
    $playlist = publishedPlaylist($user, $workspace, $design);
    [$screen] = playerScreenWithToken($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertSessionHasErrors('playlist');

    expect(Playlist::query()->find($playlist->id))->not->toBeNull();

    // Archiving is always available instead.
    $this->actingAs($user)
        ->post(route('app.playlists.archive', $playlist))
        ->assertRedirect();

    expect($playlist->fresh()->status)->toBe(PlaylistStatus::Archived);

    // Once the screen shows something else the playlist can be deleted.
    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $design), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertRedirect(route('app.playlists'));

    expect(Playlist::query()->find($playlist->id))->toBeNull()
        ->and(PlaylistItem::query()->count())->toBe(0);
});

test('a screen design used by a playlist cannot be deleted', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace, 'Shared Slide');
    $playlist = publishedPlaylist($user, $workspace, $design);

    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertSessionHasErrors('design');

    expect(ScreenDesign::query()->find($design->id))->not->toBeNull();

    // Removing it from the playlist releases the dependency.
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), ['items' => []])
        ->assertRedirect();

    // The published version still pins it, so deletion is still blocked.
    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertSessionHasErrors('design');

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertRedirect(route('app.screen_designs'));

    expect(ScreenDesign::query()->find($design->id))->toBeNull();
});

test('player manifest returns the pinned playlist items', function () {
    [$user, $workspace] = publishingUser();
    $first = publishingDesign($user, $workspace, 'Slide One');
    $second = publishingDesign($user, $workspace, 'Slide Two');
    [$screen, $token] = playerScreenWithToken($user, $workspace);

    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Lobby Loop']);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $first->id, 'duration_seconds' => 9, 'transition' => 'slide_left', 'transition_speed' => 'fast'],
                ['screen_design_id' => $second->id, 'duration_seconds' => 4],
                ['screen_design_id' => $first->id, 'duration_seconds' => 3, 'is_active' => false],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('app.playlists.publish', $playlist))->assertRedirect();
    $playlist->refresh();

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $manifest = $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->json();

    expect($manifest['status'])->toBe('ready')
        ->and($manifest['contentType'])->toBe('playlist')
        ->and($manifest['playlistId'])->toBe($playlist->id)
        ->and($manifest['playlistVersionId'])->toBe($playlist->published_version_id)
        ->and($manifest['orientation'])->toBe('landscape')
        ->and($manifest['items'])->toHaveCount(2)
        ->and($manifest['items'][0]['position'])->toBe(1)
        ->and($manifest['items'][0]['durationSeconds'])->toBe(9)
        ->and($manifest['items'][0]['loopCount'])->toBe(1)
        ->and($manifest['items'][0]['transition'])->toBe('slide_left')
        ->and($manifest['items'][0]['transitionSpeed'])->toBe('fast')
        ->and($manifest['items'][0]['screenDesignId'])->toBe($first->id)
        ->and($manifest['items'][0]['screenDesignVersionId'])->toBe($first->published_version_id)
        ->and($manifest['items'][0]['name'])->toBe('Slide One')
        ->and($manifest['items'][0]['canvas']['width'])->toBe(1920)
        ->and($manifest['items'][0]['schema'])->toBeArray()
        ->and($manifest['items'][1]['screenDesignId'])->toBe($second->id);

    // Republishing the design later must not change the live playlist deployment.
    expect($manifest['deploymentVersion'])->toStartWith('dep-');
});

test('screen design manifests still work and declare their content type', function () {
    [$user, $workspace] = publishingUser();
    $design = publishingDesign($user, $workspace);
    [$screen, $token] = playerScreenWithToken($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish_to_screens', $design), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $manifest = $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->json();

    expect($manifest['status'])->toBe('ready')
        ->and($manifest['contentType'])->toBe('screen_design')
        ->and($manifest['screenDesignId'])->toBe($design->id)
        ->and($manifest['schema'])->toBeArray()
        ->and($manifest)->not->toHaveKey('items');
});

test('player media access covers every design in the deployed playlist', function () {
    Storage::fake('public');
    [$user, $workspace] = publishingUser();
    [$otherUser, $otherWorkspace] = publishingUser();
    [$screen, $token] = playerScreenWithToken($user, $workspace);

    $file = UploadedFile::fake()->image('slide.jpg');

    $media = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Image,
        'name' => 'Slide Art',
        'storage_disk' => 'public',
        'storage_path' => $file->store('workspaces/'.$workspace->id.'/media', 'public'),
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'created_by' => $user->id,
    ]);

    $foreign = MediaAsset::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'type' => MediaType::Image,
        'name' => 'Foreign Art',
        'storage_disk' => 'public',
        'storage_path' => $file->store('workspaces/'.$otherWorkspace->id.'/media', 'public'),
        'mime_type' => 'image/jpeg',
        'created_by' => $otherUser->id,
    ]);

    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 'image-1',
        'type' => 'image',
        'name' => 'Hero',
        'x' => 0,
        'y' => 0,
        'width' => 400,
        'height' => 300,
        'zIndex' => 1,
        'props' => ['mediaAssetId' => $media->id],
    ]];

    $plain = publishingDesign($user, $workspace, 'Text Slide');
    $withMedia = publishingDesign($user, $workspace, 'Media Slide', TemplateOrientation::Landscape, $schema);

    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Media Loop']);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $plain->id],
                ['screen_design_id' => $withMedia->id],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($user)->post(route('app.playlists.publish', $playlist))->assertRedirect();
    $playlist->refresh();

    $this->actingAs($user)
        ->post(route('app.playlists.publish_to_screens', $playlist), ['screen_ids' => [$screen->id]])
        ->assertRedirect();

    $manifest = $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->json();

    expect($manifest['items'][0]['media'])->toBe([])
        ->and($manifest['items'][1]['media'])->toHaveKey((string) $media->id);

    $this->withToken($token)
        ->get(route('player.api.media.show', $media))
        ->assertOk();

    $this->withToken($token)
        ->get(route('player.api.media.show', $foreign))
        ->assertForbidden();
});
