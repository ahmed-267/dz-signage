<?php

use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;

function bulkDeleteUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function bulkDeleteDesign(User $user, Workspace $workspace, string $name = 'Lobby Screen'): ScreenDesign
{
    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 't1',
        'type' => 'text',
        'name' => 'Title',
        'x' => 10,
        'y' => 10,
        'width' => 200,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'Hello'],
    ]];

    return ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create(['name' => $name]);
}

function bulkDeletePublishedDesign(User $user, Workspace $workspace, string $name = 'Published Screen'): ScreenDesign
{
    $design = bulkDeleteDesign($user, $workspace, $name);
    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

function bulkDeletePlaylist(User $user, Workspace $workspace, string $name = 'Lobby Loop'): Playlist
{
    return Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name, 'status' => PlaylistStatus::Draft]);
}

test('owner can bulk delete screen designs', function () {
    [$user, $workspace] = bulkDeleteUser();
    $a = bulkDeleteDesign($user, $workspace, 'Screen A');
    $b = bulkDeleteDesign($user, $workspace, 'Screen B');

    $this->actingAs($user)
        ->post(route('app.screen_designs.bulk_destroy'), [
            'ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('app.screen_designs'))
        ->assertSessionHas('success');

    expect(ScreenDesign::query()->whereKey([$a->id, $b->id])->count())->toBe(0);
});

test('bulk delete screen designs reports blocked playlist dependency', function () {
    [$user, $workspace] = bulkDeleteUser();
    $design = bulkDeletePublishedDesign($user, $workspace, 'Used Screen');
    $free = bulkDeleteDesign($user, $workspace, 'Free Screen');

    $playlist = bulkDeletePlaylist($user, $workspace);
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 10]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('app.screen_designs.bulk_destroy'), [
            'ids' => [$design->id, $free->id],
        ])
        ->assertRedirect(route('app.screen_designs'))
        ->assertSessionHas('success')
        ->assertSessionHas('error');

    expect(ScreenDesign::query()->find($design->id))->not->toBeNull()
        ->and(ScreenDesign::query()->find($free->id))->toBeNull();
});

test('bulk delete screen designs from another workspace returns 404', function () {
    [$userA, $workspaceA] = bulkDeleteUser();
    [$userB] = bulkDeleteUser();
    $foreign = bulkDeleteDesign($userA, $workspaceA, 'Foreign Screen');

    $this->actingAs($userB)
        ->post(route('app.screen_designs.bulk_destroy'), [
            'ids' => [$foreign->id],
        ])
        ->assertNotFound();

    expect(ScreenDesign::query()->find($foreign->id))->not->toBeNull();
});

test('owner can bulk delete playlists', function () {
    [$user, $workspace] = bulkDeleteUser();
    $a = bulkDeletePlaylist($user, $workspace, 'Playlist A');
    $b = bulkDeletePlaylist($user, $workspace, 'Playlist B');

    $this->actingAs($user)
        ->post(route('app.playlists.bulk_destroy'), [
            'ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('app.playlists'))
        ->assertSessionHas('success');

    expect(Playlist::query()->whereKey([$a->id, $b->id])->count())->toBe(0);
});

test('bulk delete playlists reports blocked schedule dependency', function () {
    [$user, $workspace] = bulkDeleteUser();
    $design = bulkDeletePublishedDesign($user, $workspace);
    $playlist = bulkDeletePlaylist($user, $workspace, 'Scheduled Playlist');
    $free = bulkDeletePlaylist($user, $workspace, 'Free Playlist');

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 8]],
        ])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();

    $playlist = $playlist->fresh(['publishedVersion']) ?? $playlist;
    $screen = Screen::factory()->forWorkspace($workspace)->createdBy($user)->create();

    Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Breakfast', 'status' => ScheduleStatus::Draft]);

    $this->actingAs($user)
        ->post(route('app.playlists.bulk_destroy'), [
            'ids' => [$playlist->id, $free->id],
        ])
        ->assertRedirect(route('app.playlists'))
        ->assertSessionHas('success')
        ->assertSessionHas('error');

    expect(Playlist::query()->find($playlist->id))->not->toBeNull()
        ->and(Playlist::query()->find($free->id))->toBeNull();
});

test('bulk delete playlists from another workspace returns 404', function () {
    [$userA, $workspaceA] = bulkDeleteUser();
    [$userB] = bulkDeleteUser();
    $foreign = bulkDeletePlaylist($userA, $workspaceA, 'Foreign Playlist');

    $this->actingAs($userB)
        ->post(route('app.playlists.bulk_destroy'), [
            'ids' => [$foreign->id],
        ])
        ->assertNotFound();

    expect(Playlist::query()->find($foreign->id))->not->toBeNull();
});

test('owner can bulk delete non-active schedules', function () {
    [$user, $workspace] = bulkDeleteUser();
    $design = bulkDeletePublishedDesign($user, $workspace);
    $playlist = bulkDeletePlaylist($user, $workspace);
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 8]],
        ])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();
    $playlist = $playlist->fresh(['publishedVersion']) ?? $playlist;
    $screen = Screen::factory()->forWorkspace($workspace)->createdBy($user)->create();

    $a = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Draft A', 'status' => ScheduleStatus::Draft]);
    $b = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->paused()
        ->create(['name' => 'Paused B']);

    $this->actingAs($user)
        ->post(route('app.schedules.bulk_destroy'), [
            'ids' => [$a->id, $b->id],
        ])
        ->assertRedirect(route('app.schedules'))
        ->assertSessionHas('success');

    expect(Schedule::query()->whereKey([$a->id, $b->id])->count())->toBe(0);
});

test('bulk delete schedules reports blocked active schedule', function () {
    [$user, $workspace] = bulkDeleteUser();
    $design = bulkDeletePublishedDesign($user, $workspace);
    $playlist = bulkDeletePlaylist($user, $workspace);
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 8]],
        ])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();
    $playlist = $playlist->fresh(['publishedVersion']) ?? $playlist;
    $screen = Screen::factory()->forWorkspace($workspace)->createdBy($user)->create();

    $active = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->active()
        ->create(['name' => 'Live Schedule']);
    $draft = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Draft Schedule', 'status' => ScheduleStatus::Draft]);

    $this->actingAs($user)
        ->post(route('app.schedules.bulk_destroy'), [
            'ids' => [$active->id, $draft->id],
        ])
        ->assertRedirect(route('app.schedules'))
        ->assertSessionHas('success')
        ->assertSessionHas('error');

    expect(Schedule::query()->find($active->id))->not->toBeNull()
        ->and(Schedule::query()->find($draft->id))->toBeNull();
});

test('bulk delete schedules from another workspace returns 404', function () {
    [$userA, $workspaceA] = bulkDeleteUser();
    [$userB] = bulkDeleteUser();
    $foreign = Schedule::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($userA)
        ->create(['name' => 'Foreign Schedule', 'status' => ScheduleStatus::Draft]);

    $this->actingAs($userB)
        ->post(route('app.schedules.bulk_destroy'), [
            'ids' => [$foreign->id],
        ])
        ->assertNotFound();

    expect(Schedule::query()->find($foreign->id))->not->toBeNull();
});
