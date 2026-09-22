<?php

use App\Enums\PlaylistStatus;
use App\Enums\PlaylistTransition;
use App\Enums\PlaylistTransitionSpeed;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\WorkspaceRole;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;

function playlistUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function draftPlaylist(User $user, Workspace $workspace, string $name = 'Lobby Loop'): Playlist
{
    return Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name]);
}

function publishedDesignForPlaylist(
    User $user,
    Workspace $workspace,
    string $name = 'Welcome Board',
    TemplateOrientation $orientation = TemplateOrientation::Landscape,
    ?array $schema = null,
): ScreenDesign {
    $factory = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user);

    $factory = $orientation === TemplateOrientation::Portrait
        ? $factory->portrait()
        : $factory->landscape();

    $design = $factory
        ->withDraftVersion($user, $schema)
        ->create(['name' => $name]);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

test('owner can create a playlist with an initial draft version', function () {
    [$user] = playlistUser();

    $this->actingAs($user)
        ->post(route('app.playlists.store'), [
            'name' => 'Lobby Loop',
            'description' => 'Rotating lobby content',
        ])
        ->assertRedirect();

    $playlist = Playlist::query()->where('name', 'Lobby Loop')->first();

    expect($playlist)->not->toBeNull()
        ->and($playlist->status)->toBe(PlaylistStatus::Draft)
        ->and($playlist->description)->toBe('Rotating lobby content')
        ->and($playlist->orientation)->toBeNull()
        ->and($playlist->versions()->count())->toBe(1)
        ->and($playlist->latestVersion()->version_number)->toBe(1)
        ->and($playlist->latestVersion()->published_at)->toBeNull();
});

test('saving a draft pins published design versions and adopts the first item orientation', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $welcome = publishedDesignForPlaylist($user, $workspace, 'Welcome Board');
    $menu = publishedDesignForPlaylist($user, $workspace, 'Menu Board');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'name' => 'Lobby Rotation',
            'items' => [
                [
                    'screen_design_id' => $welcome->id,
                    'duration_seconds' => 15,
                    'transition' => PlaylistTransition::SlideLeft->value,
                    'transition_speed' => PlaylistTransitionSpeed::Fast->value,
                ],
                ['screen_design_id' => $menu->id],
            ],
        ])
        ->assertRedirect();

    $playlist->refresh();
    $items = $playlist->latestVersion()->items;

    expect($playlist->name)->toBe('Lobby Rotation')
        ->and($playlist->orientation)->toBe(TemplateOrientation::Landscape)
        ->and($items)->toHaveCount(2)
        ->and($items[0]->position)->toBe(1)
        ->and($items[0]->screen_design_version_id)->toBe($welcome->published_version_id)
        ->and($items[0]->duration_seconds)->toBe(15)
        ->and($items[0]->transition)->toBe(PlaylistTransition::SlideLeft)
        ->and($items[0]->transition_speed)->toBe(PlaylistTransitionSpeed::Fast)
        ->and($items[1]->position)->toBe(2)
        ->and($items[1]->screen_design_version_id)->toBe($menu->published_version_id)
        ->and($items[1]->duration_seconds)->toBe(config('playlists.default_duration_seconds'))
        ->and($items[1]->transition)->toBe(PlaylistTransition::Fade)
        ->and($items[1]->transition_speed)->toBe(PlaylistTransitionSpeed::Normal);
});

test('the same screen design can appear multiple times in one playlist', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);
    $other = publishedDesignForPlaylist($user, $workspace, 'Promo Board');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $design->id, 'duration_seconds' => 5],
                ['screen_design_id' => $other->id, 'duration_seconds' => 20],
                ['screen_design_id' => $design->id, 'duration_seconds' => 5],
            ],
        ])
        ->assertRedirect();

    $items = $playlist->fresh()->latestVersion()->items;

    expect($items)->toHaveCount(3)
        ->and($items->pluck('screen_design_id')->all())->toBe([$design->id, $other->id, $design->id])
        ->and($items->pluck('position')->all())->toBe([1, 2, 3])
        ->and($playlist->fresh()->latestVersion()->totalDurationSeconds())->toBe(30);
});

test('playlist items reject designs that have no published version', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $draftOnly = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->landscape()
        ->withDraftVersion($user)
        ->create(['name' => 'Draft Only']);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $draftOnly->id]],
        ])
        ->assertSessionHasErrors('items');

    expect($playlist->fresh()->latestVersion()->items)->toHaveCount(0);
});

test('playlist items reject an explicitly pinned unpublished design version', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);

    $draftVersion = $design->versions()->create([
        'version_number' => 2,
        'schema' => $design->publishedVersion->schema,
        'created_by' => $user->id,
        'published_at' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [[
                'screen_design_id' => $design->id,
                'screen_design_version_id' => $draftVersion->id,
            ]],
        ])
        ->assertSessionHasErrors('items');
});

test('playlist items reject designs from another workspace', function () {
    [$user, $workspace] = playlistUser();
    [$otherUser, $otherWorkspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $foreign = publishedDesignForPlaylist($otherUser, $otherWorkspace, 'Foreign Board');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $foreign->id]],
        ])
        ->assertSessionHasErrors('items');
});

test('mixing landscape and portrait designs in one playlist is rejected', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $landscape = publishedDesignForPlaylist($user, $workspace, 'Wide Board');
    $portrait = publishedDesignForPlaylist($user, $workspace, 'Tall Board', TemplateOrientation::Portrait);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $landscape->id],
                ['screen_design_id' => $portrait->id],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect($playlist->fresh()->latestVersion()->items)->toHaveCount(0)
        ->and($playlist->fresh()->orientation)->toBeNull();
});

test('replacing every item with the other orientation re-derives the playlist orientation', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $landscape = publishedDesignForPlaylist($user, $workspace, 'Wide Board');
    $portrait = publishedDesignForPlaylist($user, $workspace, 'Tall Board', TemplateOrientation::Portrait);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $landscape->id]],
        ])
        ->assertRedirect();

    expect($playlist->fresh()->orientation)->toBe(TemplateOrientation::Landscape);

    // The item list is replaced wholesale, so the landscape lock must not
    // outlive the landscape design that set it.
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $portrait->id]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($playlist->fresh()->orientation)->toBe(TemplateOrientation::Portrait);

    // Mixing is still rejected when both orientations are present.
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $portrait->id],
                ['screen_design_id' => $landscape->id],
            ],
        ])
        ->assertSessionHasErrors('items');
});

test('emptying a playlist keeps its orientation until new items redefine it', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $landscape = publishedDesignForPlaylist($user, $workspace, 'Wide Board');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $landscape->id]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), ['items' => []])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($playlist->fresh()->orientation)->toBe(TemplateOrientation::Landscape)
        ->and($playlist->fresh()->latestVersion()->items)->toHaveCount(0);
});

test('a portrait playlist cannot switch orientation while it holds landscape designs', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $landscape = publishedDesignForPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $landscape->id]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'orientation' => TemplateOrientation::Portrait->value,
        ])
        ->assertSessionHasErrors('orientation');

    expect($playlist->fresh()->orientation)->toBe(TemplateOrientation::Landscape);
});

test('item durations outside the configured range are rejected', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);
    $max = (int) config('playlists.max_duration_seconds');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 0]],
        ])
        ->assertSessionHasErrors('items');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => $max + 1]],
        ])
        ->assertSessionHasErrors('items');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => $max]],
        ])
        ->assertRedirect();

    expect($playlist->fresh()->latestVersion()->items[0]->duration_seconds)->toBe($max);
});

test('workspace isolation blocks foreign playlists', function () {
    [$userA, $workspaceA] = playlistUser();
    [$userB] = playlistUser();
    $playlist = draftPlaylist($userA, $workspaceA, 'Private A');

    $this->actingAs($userB)
        ->get(route('app.playlists.edit', $playlist))
        ->assertNotFound();

    $this->actingAs($userB)
        ->get(route('app.playlists.preview', $playlist))
        ->assertNotFound();

    $this->actingAs($userB)
        ->patch(route('app.playlists.update', $playlist), ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($userB)
        ->post(route('app.playlists.publish', $playlist))
        ->assertNotFound();

    $this->actingAs($userB)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertNotFound();

    expect($playlist->fresh()->name)->toBe('Private A');
});

test('the playlist index only lists playlists from the current workspace', function () {
    [$userA, $workspaceA] = playlistUser();
    [$userB, $workspaceB] = playlistUser();
    draftPlaylist($userA, $workspaceA, 'Mine');
    draftPlaylist($userB, $workspaceB, 'Theirs');

    $this->actingAs($userA)
        ->get(route('app.playlists'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/playlists/index')
            ->has('playlists.data', 1)
            ->where('playlists.data.0.name', 'Mine'));
});

test('the playlist index includes a preview schema for published items', function () {
    [$user, $workspace] = playlistUser();
    $design = publishedDesignForPlaylist($user, $workspace, 'Preview Design');
    $playlist = draftPlaylist($user, $workspace, 'Preview Loop');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 12]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('app.playlists'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/playlists/index')
            ->where('playlists.data.0.name', 'Preview Loop')
            ->where('playlists.data.0.preview_item.name', 'Preview Design')
            ->has('playlists.data.0.preview_item.schema.elements')
            ->has('playlists.data.0.preview_items')
            ->where('playlists.data.0.preview_items.0.name', 'Preview Design')
            ->where('playlists.data.0.assigned_tv_count', 0)
            ->where('playlists.data.0.schedule_count', 0)
        );
});

test('content manager can manage playlists while designer and viewer cannot', function () {
    [$owner, $workspace] = playlistUser();
    $contentManager = User::factory()->create();
    $designer = User::factory()->create();
    $viewer = User::factory()->create();
    attachWorkspace($contentManager, $workspace, WorkspaceRole::ContentManager);
    attachWorkspace($designer, $workspace, WorkspaceRole::Designer);
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $playlist = draftPlaylist($owner, $workspace);

    $this->actingAs($contentManager)
        ->post(route('app.playlists.store'), ['name' => 'CM Playlist'])
        ->assertRedirect();

    $this->actingAs($designer)
        ->post(route('app.playlists.store'), ['name' => 'Designer Playlist'])
        ->assertForbidden();

    $this->actingAs($designer)
        ->patch(route('app.playlists.update', $playlist), ['name' => 'Nope'])
        ->assertForbidden();

    foreach ([$designer, $viewer] as $readOnly) {
        $this->actingAs($readOnly)
            ->get(route('app.playlists'))
            ->assertOk();

        $this->actingAs($readOnly)
            ->get(route('app.playlists.edit', $playlist))
            ->assertOk();

        $this->actingAs($readOnly)
            ->post(route('app.playlists.publish', $playlist))
            ->assertForbidden();

        $this->actingAs($readOnly)
            ->delete(route('app.playlists.destroy', $playlist))
            ->assertForbidden();
    }

    expect(Playlist::query()->where('name', 'Designer Playlist')->exists())->toBeFalse()
        ->and(Playlist::query()->where('name', 'CM Playlist')->exists())->toBeTrue();
});

test('duplicating a playlist copies items into a fresh draft', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace, 'Summer Loop');
    $design = publishedDesignForPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 25]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('app.playlists.duplicate', $playlist))
        ->assertRedirect();

    $copy = Playlist::query()->where('name', 'Summer Loop Copy')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(PlaylistStatus::Draft)
        ->and($copy->published_version_id)->toBeNull()
        ->and($copy->orientation)->toBe(TemplateOrientation::Landscape)
        ->and($copy->versions()->count())->toBe(1)
        ->and($copy->latestVersion()->published_at)->toBeNull()
        ->and($copy->latestVersion()->items)->toHaveCount(1)
        ->and($copy->latestVersion()->items[0]->duration_seconds)->toBe(25)
        ->and($copy->latestVersion()->items[0]->screen_design_version_id)
        ->toBe($design->published_version_id);
});

test('archiving a playlist twice reports a clear error', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.playlists.archive', $playlist))
        ->assertRedirect();

    expect($playlist->fresh()->status)->toBe(PlaylistStatus::Archived);

    $this->actingAs($user)
        ->post(route('app.playlists.archive', $playlist))
        ->assertSessionHasErrors('playlist');
});

test('a draft playlist that never deployed can be deleted with its versions and items', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id]],
        ])
        ->assertRedirect();

    $versionId = $playlist->fresh()->latestVersion()->id;

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertRedirect(route('app.playlists'));

    expect(Playlist::query()->whereKey($playlist->id)->exists())->toBeFalse()
        ->and(PlaylistVersion::query()->whereKey($versionId)->exists())->toBeFalse()
        ->and(PlaylistItem::query()->where('playlist_version_id', $versionId)->exists())->toBeFalse()
        ->and(ScreenDesign::query()->whereKey($design->id)->exists())->toBeTrue();
});

test('the published designs picker only returns published designs from the workspace', function () {
    [$user, $workspace] = playlistUser();
    [$otherUser, $otherWorkspace] = playlistUser();

    $published = publishedDesignForPlaylist($user, $workspace, 'Ready Board');
    publishedDesignForPlaylist($otherUser, $otherWorkspace, 'Foreign Board');
    ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->landscape()
        ->withDraftVersion($user)
        ->create(['name' => 'Unfinished Board']);

    $payload = $this->actingAs($user)
        ->getJson(route('app.playlists.published_designs'))
        ->assertOk()
        ->json();

    expect($payload['data'])->toHaveCount(1)
        ->and($payload['data'][0]['id'])->toBe($published->id)
        ->and($payload['data'][0]['published_version_id'])->toBe($published->published_version_id)
        ->and($payload['data'][0]['schema'])->toBeArray();

    $filtered = $this->actingAs($user)
        ->getJson(route('app.playlists.published_designs', ['q' => 'Foreign']))
        ->assertOk()
        ->json();

    expect($filtered['data'])->toHaveCount(0);
});

test('the preview endpoint returns only active items with their pinned schemas', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $active = publishedDesignForPlaylist($user, $workspace, 'Active Board');
    $hidden = publishedDesignForPlaylist($user, $workspace, 'Hidden Board');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $active->id, 'duration_seconds' => 8],
                ['screen_design_id' => $hidden->id, 'is_active' => false],
            ],
        ])
        ->assertRedirect();

    $payload = $this->actingAs($user)
        ->getJson(route('app.playlists.preview', $playlist))
        ->assertOk()
        ->json();

    expect($payload['orientation'])->toBe('landscape')
        ->and($payload['total_duration_seconds'])->toBe(8)
        ->and($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['screen_design_id'])->toBe($active->id)
        ->and($payload['items'][0]['screen_design_version_id'])->toBe($active->published_version_id)
        ->and($payload['items'][0]['schema'])->toBeArray();
});

test('loop count is persisted and multiplies draft total duration', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [[
                'screen_design_id' => $design->id,
                'duration_seconds' => 10,
                'loop_count' => 4,
            ]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $version = $playlist->fresh()->latestVersion();
    $item = $version->items->first();

    expect($item->loop_count)->toBe(4)
        ->and($item->duration_seconds)->toBe(10)
        ->and($version->totalDurationSeconds())->toBe(40);

    $preview = $this->actingAs($user)
        ->getJson(route('app.playlists.preview', $playlist))
        ->assertOk()
        ->json();

    expect($preview['total_duration_seconds'])->toBe(40)
        ->and($preview['items'][0]['loop_count'])->toBe(4)
        ->and($preview['items'][0]['effective_duration_seconds'])->toBe(40)
        ->and($preview['version_scope'])->toBe('latest');

    $this->actingAs($user)
        ->get(route('app.playlists.edit', $playlist))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('playlist.total_duration_seconds', 40)
            ->where('playlist.items.0.loop_count', 4)
            ->where('playlist.items.0.effective_duration_seconds', 40)
            ->where('playlist.version_scope', 'latest')
        );
});

test('loop counts outside 1..99 are rejected', function () {
    [$user, $workspace] = playlistUser();
    $playlist = draftPlaylist($user, $workspace);
    $design = publishedDesignForPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'loop_count' => 0]],
        ])
        ->assertSessionHasErrors();

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'loop_count' => 100]],
        ])
        ->assertSessionHasErrors();
});

test('changing draft loop count does not mutate a schedule pinned published runtime', function () {
    [$user, $workspace] = playlistUser();
    $design = publishedDesignForPlaylist($user, $workspace);
    $playlist = draftPlaylist($user, $workspace);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [[
                'screen_design_id' => $design->id,
                'duration_seconds' => 10,
                'loop_count' => 1,
            ]],
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertRedirect();

    $playlist->refresh();
    $pinnedVersionId = $playlist->published_version_id;
    $pinnedRuntime = $playlist->publishedVersion->totalDurationSeconds();

    expect($pinnedRuntime)->toBe(10);

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [[
                'screen_design_id' => $design->id,
                'duration_seconds' => 10,
                'loop_count' => 5,
            ]],
        ])
        ->assertRedirect();

    $playlist->refresh();
    $draft = $playlist->latestVersion();

    expect($draft->id)->not->toBe($pinnedVersionId)
        ->and($draft->totalDurationSeconds())->toBe(50)
        ->and($playlist->publishedVersion->fresh()->totalDurationSeconds())->toBe(10)
        ->and($playlist->published_version_id)->toBe($pinnedVersionId);
});
