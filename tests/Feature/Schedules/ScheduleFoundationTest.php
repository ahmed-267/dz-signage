<?php

use App\Enums\ScheduleStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\WorkspaceRole;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Schedules\ScheduleDays;

function scheduleUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function scheduleDesign(User $user, Workspace $workspace, string $name = 'Lobby Design'): ScreenDesign
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

/**
 * A published playlist with one active item — the only kind a schedule plays.
 */
function schedulePlaylist(User $user, Workspace $workspace, string $name = 'Lobby Loop'): Playlist
{
    $design = scheduleDesign($user, $workspace, $name.' Design');

    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name]);

    test()->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 12]],
        ])
        ->assertSessionHasNoErrors();

    test()->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();

    return $playlist->fresh(['publishedVersion.items']) ?? $playlist;
}

function scheduleScreen(User $user, Workspace $workspace, string $name = 'Lobby TV'): Screen
{
    return Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create(['name' => $name, 'orientation' => 'landscape']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function schedulePayload(Playlist $playlist, Screen $screen, array $overrides = []): array
{
    return [
        'name' => 'Breakfast',
        'playlist_id' => $playlist->id,
        'screen_ids' => [$screen->id],
        'timezone' => 'Africa/Algiers',
        'start_time' => '07:00',
        'end_time' => '12:00',
        'days_of_week' => ScheduleDays::weekdays(),
        'priority' => 8,
        ...$overrides,
    ];
}

test('creating a schedule stores a draft pinned to the published playlist version', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $screen, [
            'description' => 'Morning menu',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule = Schedule::query()->where('name', 'Breakfast')->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->activated_at)->toBeNull()
        ->and($schedule->description)->toBe('Morning menu')
        ->and($schedule->playlist_id)->toBe($playlist->id)
        ->and($schedule->playlist_version_id)->toBe($playlist->published_version_id)
        ->and($schedule->timezone)->toBe('Africa/Algiers')
        ->and($schedule->start_time)->toBe('07:00:00')
        ->and($schedule->end_time)->toBe('12:00:00')
        ->and($schedule->days())->toBe(ScheduleDays::weekdays())
        ->and($schedule->priority)->toBe(8)
        ->and($schedule->screens->pluck('id')->all())->toBe([$screen->id]);
});

test('a schedule keeps its pinned version when the playlist publishes a newer one', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);
    $pinnedVersionId = $playlist->published_version_id;

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $screen))
        ->assertRedirect();

    $schedule = Schedule::query()->where('name', 'Breakfast')->firstOrFail();

    $extra = scheduleDesign($user, $workspace, 'Second Slide');
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [
                ['screen_design_id' => $playlist->publishedVersion->items[0]->screen_design_id],
                ['screen_design_id' => $extra->id],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();

    expect($playlist->fresh()->published_version_id)->not->toBe($pinnedVersionId)
        ->and($schedule->fresh()->playlist_version_id)->toBe($pinnedVersionId);
});

test('re-picking the playlist adopts its current published version', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $screen))
        ->assertRedirect();

    $schedule = Schedule::query()->where('name', 'Breakfast')->firstOrFail();
    $oldVersionId = $schedule->playlist_version_id;

    $extra = scheduleDesign($user, $workspace, 'Third Slide');
    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $extra->id]],
        ])
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('app.playlists.publish', $playlist))->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->patch(route('app.schedules.update', $schedule), ['playlist_id' => $playlist->id])
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->playlist_version_id)
        ->toBe($playlist->fresh()->published_version_id)
        ->not->toBe($oldVersionId);
});

test('schedules reject playlists that are unpublished, archived or from another workspace', function () {
    [$user, $workspace] = scheduleUser();
    [$otherUser, $otherWorkspace] = scheduleUser();
    $screen = scheduleScreen($user, $workspace);

    $draftOnly = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Unpublished Loop']);

    $foreign = schedulePlaylist($otherUser, $otherWorkspace, 'Foreign Loop');

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($draftOnly, $screen))
        ->assertSessionHasErrors('playlist_id');

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($foreign, $screen))
        ->assertSessionHasErrors('playlist_id');

    $archived = schedulePlaylist($user, $workspace, 'Archived Loop');
    $this->actingAs($user)->post(route('app.playlists.archive', $archived))->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($archived, $screen))
        ->assertSessionHasErrors('playlist_id');

    expect(Schedule::query()->count())->toBe(0);
});

test('schedules reject screens from another workspace', function () {
    [$user, $workspace] = scheduleUser();
    [$otherUser, $otherWorkspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $foreignScreen = scheduleScreen($otherUser, $otherWorkspace, 'Foreign TV');

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $foreignScreen))
        ->assertSessionHasErrors('screen_ids');

    expect(Schedule::query()->count())->toBe(0);
});

test('schedule windows, dates, timezone and priority are validated', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);
    $max = (int) config('schedules.max_priority');

    $cases = [
        ['end_time' => '07:00', 'field' => 'end_time'],
        ['timezone' => 'Mars/Olympus', 'field' => 'timezone'],
        ['priority' => 0, 'field' => 'priority'],
        ['priority' => $max + 1, 'field' => 'priority'],
        ['start_time' => '7 oclock', 'field' => 'start_time'],
        ['start_date' => '2026-03-10', 'end_date' => '2026-03-01', 'field' => 'end_date'],
    ];

    foreach ($cases as $case) {
        $field = $case['field'];
        unset($case['field']);

        $this->actingAs($user)
            ->post(route('app.schedules.store'), schedulePayload($playlist, $screen, $case))
            ->assertSessionHasErrors($field);
    }

    expect(Schedule::query()->count())->toBe(0);
});

test('activating a schedule validates content, screens and days', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $noPlaylist = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forScreens($screen)
        ->create(['name' => 'No Playlist']);

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $noPlaylist))
        ->assertSessionHasErrors('playlist_id');

    $noScreens = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->create(['name' => 'No Screens']);

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $noScreens))
        ->assertSessionHasErrors('screen_ids');

    $noDays = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'No Days', 'days_of_week' => []]);

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $noDays))
        ->assertSessionHasErrors('days_of_week');

    $ended = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->dates(null, now()->subDay()->format('Y-m-d'))
        ->create(['name' => 'Already Over']);

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $ended))
        ->assertSessionHasErrors('end_date');

    expect(Schedule::query()->where('status', ScheduleStatus::Active)->count())->toBe(0);
});

test('activating a complete schedule records the activation time', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $screen))
        ->assertRedirect();

    $schedule = Schedule::query()->where('name', 'Breakfast')->firstOrFail();

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $schedule))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $schedule->refresh();

    expect($schedule->status)->toBe(ScheduleStatus::Active)
        ->and($schedule->activated_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $schedule))
        ->assertSessionHasErrors('schedule');
});

test('an active schedule cannot be edited into an unplayable state', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->active()
        ->create(['name' => 'Live Window']);

    $this->actingAs($user)
        ->patch(route('app.schedules.update', $schedule), ['screen_ids' => []])
        ->assertSessionHasErrors('screen_ids');

    $this->actingAs($user)
        ->patch(route('app.schedules.update', $schedule), ['playlist_id' => null])
        ->assertSessionHasErrors('playlist_id');

    expect($schedule->fresh()->screens)->toHaveCount(1)
        ->and($schedule->fresh()->playlist_id)->toBe($playlist->id);
});

test('pausing stops a live schedule and only applies to active schedules', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->active()
        ->create(['name' => 'Pause Me']);

    $this->actingAs($user)
        ->post(route('app.schedules.pause', $schedule))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Paused)
        ->and($schedule->fresh()->activated_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(route('app.schedules.pause', $schedule))
        ->assertSessionHasErrors('schedule');
});

test('duplicating a schedule copies its window and screens into a draft', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);
    $second = scheduleScreen($user, $workspace, 'Bar TV');

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen, $second)
        ->window('22:00', '02:00', [ScheduleDays::FRIDAY])
        ->priority(8)
        ->active()
        ->create(['name' => 'Late Night', 'description' => 'Weekend loop']);

    $this->actingAs($user)
        ->post(route('app.schedules.duplicate', $schedule))
        ->assertRedirect();

    $copy = Schedule::query()->where('name', 'Late Night Copy')->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(ScheduleStatus::Draft)
        ->and($copy->activated_at)->toBeNull()
        ->and($copy->description)->toBe('Weekend loop')
        ->and($copy->playlist_version_id)->toBe($schedule->playlist_version_id)
        ->and($copy->priority)->toBe(8)
        ->and($copy->start_time)->toBe('22:00:00')
        ->and($copy->end_time)->toBe('02:00:00')
        ->and($copy->days())->toBe([ScheduleDays::FRIDAY])
        ->and($copy->screens->pluck('id')->sort()->values()->all())
        ->toBe(collect([$screen->id, $second->id])->sort()->values()->all());
});

test('archiving retires a schedule and blocks further edits', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->active()
        ->create(['name' => 'Retire Me']);

    $this->actingAs($user)
        ->post(route('app.schedules.archive', $schedule))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($schedule->fresh()->status)->toBe(ScheduleStatus::Archived);

    $this->actingAs($user)
        ->post(route('app.schedules.archive', $schedule))
        ->assertSessionHasErrors('schedule');

    $this->actingAs($user)
        ->patch(route('app.schedules.update', $schedule), ['name' => 'Renamed'])
        ->assertSessionHasErrors('schedule');

    $this->actingAs($user)
        ->post(route('app.schedules.activate', $schedule))
        ->assertSessionHasErrors('schedule');

    expect($schedule->fresh()->name)->toBe('Retire Me');
});

test('a live schedule must be paused or archived before it can be deleted', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->active()
        ->create(['name' => 'Live Window']);

    $this->actingAs($user)
        ->delete(route('app.schedules.destroy', $schedule))
        ->assertSessionHasErrors('schedule');

    expect(Schedule::query()->whereKey($schedule->id)->exists())->toBeTrue();

    $this->actingAs($user)->post(route('app.schedules.pause', $schedule))->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.schedules.destroy', $schedule))
        ->assertRedirect(route('app.schedules'));

    expect(Schedule::query()->whereKey($schedule->id)->exists())->toBeFalse()
        ->and(Screen::query()->whereKey($screen->id)->exists())->toBeTrue()
        ->and(Playlist::query()->whereKey($playlist->id)->exists())->toBeTrue();
});

test('a playlist used by a schedule cannot be deleted', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Holds Playlist']);

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertSessionHasErrors('playlist');

    expect(Playlist::query()->whereKey($playlist->id)->exists())->toBeTrue();

    $this->actingAs($user)->delete(route('app.schedules.destroy', $schedule))->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.playlists.destroy', $playlist))
        ->assertRedirect(route('app.playlists'));

    expect(Playlist::query()->whereKey($playlist->id)->exists())->toBeFalse();
});

test('workspace isolation blocks foreign schedules', function () {
    [$userA, $workspaceA] = scheduleUser();
    [$userB] = scheduleUser();
    $playlist = schedulePlaylist($userA, $workspaceA);
    $screen = scheduleScreen($userA, $workspaceA);

    $schedule = Schedule::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($userA)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Private A']);

    $this->actingAs($userB)->get(route('app.schedules.edit', $schedule))->assertNotFound();
    $this->actingAs($userB)->getJson(route('app.schedules.preview', $schedule))->assertNotFound();
    $this->actingAs($userB)->patch(route('app.schedules.update', $schedule), ['name' => 'Hacked'])->assertNotFound();
    $this->actingAs($userB)->post(route('app.schedules.activate', $schedule))->assertNotFound();
    $this->actingAs($userB)->post(route('app.schedules.duplicate', $schedule))->assertNotFound();
    $this->actingAs($userB)->delete(route('app.schedules.destroy', $schedule))->assertNotFound();

    expect($schedule->fresh()->name)->toBe('Private A');
});

test('the schedule index only lists schedules from the current workspace', function () {
    [$userA, $workspaceA] = scheduleUser();
    [$userB, $workspaceB] = scheduleUser();

    Schedule::factory()->forWorkspace($workspaceA)->createdBy($userA)->create(['name' => 'Mine']);
    Schedule::factory()->forWorkspace($workspaceB)->createdBy($userB)->create(['name' => 'Theirs']);

    $this->actingAs($userA)
        ->get(route('app.schedules'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('schedules.data', 1)
            ->where('schedules.data.0.name', 'Mine')
            ->has('calendar.days', 7)
            ->where('calendar.timezone', $workspaceA->timezone)
        );
});

test('the schedule index paginates sorts and filters server-side', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace, 'Filter Loop');
    $screen = scheduleScreen($user, $workspace, 'Filter TV');

    foreach (range(1, 12) as $i) {
        Schedule::factory()
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->forPlaylist($playlist)
            ->forScreens($screen)
            ->create([
                'name' => sprintf('Paged Schedule %02d', $i),
                'priority' => min($i, 10),
                'status' => ScheduleStatus::Active,
            ]);
    }

    Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create([
            'name' => 'Draft Only',
            'status' => ScheduleStatus::Draft,
        ]);

    $this->actingAs($user)
        ->get(route('app.schedules', [
            'per_page' => 10,
            'page' => 1,
            'sort' => 'name',
            'direction' => 'asc',
            'status' => 'active',
            'q' => 'Paged',
            'screen' => $screen->id,
            'playlist' => $playlist->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/schedules/index')
            ->has('schedules.data', 10)
            ->where('schedules.meta.per_page', 10)
            ->where('schedules.meta.total', 12)
            ->where('schedules.meta.current_page', 1)
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->where('filters.per_page', 10)
            ->where('schedules.data.0.name', 'Paged Schedule 01')
        );

    $this->actingAs($user)
        ->get(route('app.schedules', [
            'per_page' => 10,
            'page' => 2,
            'sort' => 'name',
            'direction' => 'asc',
            'status' => 'active',
            'q' => 'Paged',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('schedules.data', 2)
            ->where('schedules.meta.current_page', 2)
            ->where('schedules.data.0.name', 'Paged Schedule 11')
        );
});

test('content manager can manage schedules while designer and viewer cannot', function () {
    [$owner, $workspace] = scheduleUser();
    $contentManager = User::factory()->create();
    $designer = User::factory()->create();
    $viewer = User::factory()->create();
    attachWorkspace($contentManager, $workspace, WorkspaceRole::ContentManager);
    attachWorkspace($designer, $workspace, WorkspaceRole::Designer);
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $playlist = schedulePlaylist($owner, $workspace);
    $screen = scheduleScreen($owner, $workspace);
    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($owner)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->create(['name' => 'Owned']);

    $this->actingAs($contentManager)
        ->post(route('app.schedules.store'), schedulePayload($playlist, $screen, ['name' => 'CM Schedule']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    foreach ([$designer, $viewer] as $readOnly) {
        $this->actingAs($readOnly)->get(route('app.schedules'))->assertOk();
        $this->actingAs($readOnly)->get(route('app.schedules.edit', $schedule))->assertOk();
        $this->actingAs($readOnly)->getJson(route('app.schedules.preview', $schedule))->assertOk();

        $this->actingAs($readOnly)->get(route('app.schedules.create'))->assertForbidden();
        $this->actingAs($readOnly)
            ->post(route('app.schedules.store'), schedulePayload($playlist, $screen, ['name' => 'Nope']))
            ->assertForbidden();
        $this->actingAs($readOnly)
            ->patch(route('app.schedules.update', $schedule), ['name' => 'Nope'])
            ->assertForbidden();
        $this->actingAs($readOnly)->post(route('app.schedules.activate', $schedule))->assertForbidden();
        $this->actingAs($readOnly)->post(route('app.schedules.pause', $schedule))->assertForbidden();
        $this->actingAs($readOnly)->delete(route('app.schedules.destroy', $schedule))->assertForbidden();
    }

    expect(Schedule::query()->where('name', 'CM Schedule')->exists())->toBeTrue()
        ->and(Schedule::query()->where('name', 'Nope')->exists())->toBeFalse();
});

test('the create form offers only published playlists from the workspace', function () {
    [$user, $workspace] = scheduleUser();
    [$otherUser, $otherWorkspace] = scheduleUser();

    $published = schedulePlaylist($user, $workspace, 'Ready Loop');
    schedulePlaylist($otherUser, $otherWorkspace, 'Foreign Loop');
    Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Draft Loop']);

    $this->actingAs($user)
        ->get(route('app.schedules.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('playlists', 1)
            ->where('playlists.0.id', $published->id)
            ->where('playlists.0.published_version_id', $published->published_version_id)
            ->where('playlists.0.total_duration_seconds', 12)
            ->where('playlists.0.version_scope', 'published')
            ->where('playlists.0.draft_total_duration_seconds', null)
            ->where('workspace_timezone', $workspace->timezone)
            ->where('config.default_priority', (int) config('schedules.default_priority'))
            ->has('timezones')
            ->has('day_labels')
        );
});

test('schedule playlist picker shows draft runtime when it differs from published', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace, 'Runtime Loop');
    $designId = $playlist->publishedVersion->items[0]->screen_design_id;

    $this->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [[
                'screen_design_id' => $designId,
                'duration_seconds' => 12,
                'loop_count' => 3,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->get(route('app.schedules.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('playlists.0.id', $playlist->id)
            ->where('playlists.0.total_duration_seconds', 12)
            ->where('playlists.0.draft_total_duration_seconds', 36)
            ->where('playlists.0.version_scope', 'published')
        );
});

test('overlapping live schedules are reported as warnings, never blocked', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace);
    $screen = scheduleScreen($user, $workspace);

    $breakfast = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('07:00', '12:00')
        ->priority(10)
        ->active()
        ->create(['name' => 'Breakfast']);

    $brunch = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('11:00', '14:00')
        ->priority(5)
        ->active()
        ->create(['name' => 'Brunch']);

    $lunch = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('12:00', '15:00')
        ->active()
        ->create(['name' => 'Lunch']);

    $payload = $this->actingAs($user)
        ->post(route('app.schedules.conflicts', $breakfast))
        ->assertOk()
        ->json();

    expect($payload['conflicts'])->toHaveCount(1)
        ->and($payload['conflicts'][0]['id'])->toBe($brunch->id)
        ->and($payload['conflicts'][0]['screen_names'])->toBe([$screen->name])
        ->and($payload['conflicts'][0]['other_wins'])->toBeFalse();

    // Back-to-back windows share only the exclusive end instant.
    $lunchConflicts = $this->actingAs($user)
        ->post(route('app.schedules.conflicts', $lunch))
        ->assertOk()
        ->json('conflicts');

    expect(collect($lunchConflicts)->pluck('id')->all())->toBe([$brunch->id]);
});

test('the preview endpoint reports what plays, when it plays next, and overlaps', function () {
    [$user, $workspace] = scheduleUser();
    $playlist = schedulePlaylist($user, $workspace, 'Preview Loop');
    $screen = scheduleScreen($user, $workspace);

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('07:00', '12:00')
        ->active()
        ->create(['name' => 'Breakfast']);

    $payload = $this->actingAs($user)
        ->getJson(route('app.schedules.preview', $schedule))
        ->assertOk()
        ->json();

    expect($payload['schedule']['name'])->toBe('Breakfast')
        ->and($payload['schedule']['playlist_name'])->toBe('Preview Loop')
        ->and($payload['schedule']['screen_ids'])->toBe([$screen->id])
        ->and($payload['items'])->toHaveCount(1)
        ->and($payload['items'][0]['duration_seconds'])->toBe(12)
        ->and($payload['items'][0]['loop_count'])->toBe(1)
        ->and($payload['items'][0]['effective_duration_seconds'])->toBe(12)
        ->and($payload['items'][0]['schema'])->toBeArray()
        ->and($payload['total_duration_seconds'])->toBe(12)
        ->and($payload['version_scope'])->toBe('pinned')
        ->and($payload['upcoming'])->not->toBeEmpty()
        ->and($payload['upcoming'][0])->toHaveKeys(['starts_at', 'ends_at', 'timezone'])
        ->and($payload['conflicts'])->toBe([]);
});

test('previewing a draft that has not picked a playlist yet reports no items', function () {
    [$user, $workspace] = scheduleUser();

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->window('07:00', '12:00')
        ->create(['name' => 'Untitled Schedule']);

    $payload = $this->actingAs($user)
        ->getJson(route('app.schedules.preview', $schedule))
        ->assertOk()
        ->json();

    expect($payload['schedule']['playlist_id'])->toBeNull()
        ->and($payload['items'])->toBe([])
        ->and($payload['total_duration_seconds'])->toBe(0)
        ->and($payload['conflicts'])->toBe([]);
});
