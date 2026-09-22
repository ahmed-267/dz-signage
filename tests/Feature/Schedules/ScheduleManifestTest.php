<?php

use App\Enums\MediaType;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use App\Support\Schedules\ScheduleDays;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function manifestUser(): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Owner);

    return [$user, $workspace];
}

/**
 * @return array{0: Screen, 1: string}
 */
function manifestScreen(User $user, Workspace $workspace, string $name = 'Lobby TV'): array
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

function manifestDesign(User $user, Workspace $workspace, string $name, ?array $schema = null): ScreenDesign
{
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create([
            'name' => $name,
            'orientation' => TemplateOrientation::Landscape,
            'canvas_width' => 1920,
            'canvas_height' => 1080,
        ]);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

function manifestPlaylist(User $user, Workspace $workspace, ScreenDesign $design, string $name): Playlist
{
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name]);

    test()->actingAs($user)
        ->patch(route('app.playlists.update', $playlist), [
            'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 9]],
        ])
        ->assertSessionHasNoErrors();

    test()->actingAs($user)
        ->post(route('app.playlists.publish', $playlist))
        ->assertSessionHasNoErrors();

    return $playlist->fresh(['publishedVersion.items']) ?? $playlist;
}

function manifestSchedule(
    User $user,
    Workspace $workspace,
    Playlist $playlist,
    Screen $screen,
    string $name,
    string $start,
    string $end,
    int $priority = 10,
): Schedule {
    return Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window($start, $end)
        ->priority($priority)
        ->active()
        ->create(['name' => $name]);
}

afterEach(function () {
    Carbon::setTestNow();
});

test('a matching schedule drives the player manifest', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);
    $design = manifestDesign($user, $workspace, 'Breakfast Slide');
    $playlist = manifestPlaylist($user, $workspace, $design, 'Breakfast Loop');
    $schedule = manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $manifest = $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->json();

    expect($manifest['status'])->toBe('ready')
        ->and($manifest['contentSource'])->toBe('schedule')
        ->and($manifest['scheduleId'])->toBe($schedule->id)
        ->and($manifest['scheduleName'])->toBe('Breakfast')
        ->and($manifest['contentType'])->toBe('playlist')
        ->and($manifest['playlistId'])->toBe($playlist->id)
        ->and($manifest['playlistVersionId'])->toBe($playlist->published_version_id)
        ->and($manifest['deploymentId'])->toBeNull()
        ->and($manifest['items'])->toHaveCount(1)
        ->and($manifest['items'][0]['durationSeconds'])->toBe(9)
        ->and($manifest['items'][0]['schema'])->toBeArray()
        ->and($manifest['validity']['timezone'])->toBe('UTC')
        ->and($manifest['validity']['startsAt'])->toStartWith('2026-09-14T07:00:00')
        ->and($manifest['validity']['endsAt'])->toStartWith('2026-09-14T12:00:00')
        ->and($manifest['deploymentVersion'])
        ->toBe('sch-'.$schedule->id.'-pv-'.$playlist->published_version_id.'-20260914T070000Z');

    $check = $this->withToken($token)
        ->getJson(route('player.api.manifest.check'))
        ->assertOk()
        ->json();

    expect($check['version'])->toBe($manifest['deploymentVersion'])
        ->and($check['content_source'])->toBe('schedule')
        ->and($check['schedule_id'])->toBe($schedule->id)
        ->and($check['deployment_id'])->toBeNull()
        ->and($check['screen_active'])->toBeTrue()
        ->and($check['valid_until'])->toStartWith('2026-09-14T12:00:00');
});

test('the manifest hands over to the next schedule at the changeover minute', function () {
    Carbon::setTestNow('2026-09-14 11:59:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);

    $breakfastDesign = manifestDesign($user, $workspace, 'Breakfast Slide');
    $lunchDesign = manifestDesign($user, $workspace, 'Lunch Slide');
    $breakfastPlaylist = manifestPlaylist($user, $workspace, $breakfastDesign, 'Breakfast Loop');
    $lunchPlaylist = manifestPlaylist($user, $workspace, $lunchDesign, 'Lunch Loop');

    $breakfast = manifestSchedule($user, $workspace, $breakfastPlaylist, $screen, 'Breakfast', '07:00', '12:00');
    $lunch = manifestSchedule($user, $workspace, $lunchPlaylist, $screen, 'Lunch', '12:00', '15:00');

    $before = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($before['scheduleId'])->toBe($breakfast->id)
        ->and($before['playlistId'])->toBe($breakfastPlaylist->id);

    Carbon::setTestNow('2026-09-14 12:00:00');

    $after = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($after['scheduleId'])->toBe($lunch->id)
        ->and($after['playlistId'])->toBe($lunchPlaylist->id)
        ->and($after['deploymentVersion'])->not->toBe($before['deploymentVersion'])
        ->and($after['validity']['startsAt'])->toStartWith('2026-09-14T12:00:00');
});

test('the player falls back to the active deployment outside every schedule window', function () {
    Carbon::setTestNow('2026-09-14 18:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);

    $scheduleDesign = manifestDesign($user, $workspace, 'Breakfast Slide');
    $playlist = manifestPlaylist($user, $workspace, $scheduleDesign, 'Breakfast Loop');
    manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $fallbackDesign = manifestDesign($user, $workspace, 'Always On');
    $this->actingAs($user)
        ->post(route('app.screens.publish', $screen), ['screen_design_id' => $fallbackDesign->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $deployment = $screen->fresh()->activeDeployment();

    $manifest = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($manifest['contentSource'])->toBe('deployment')
        ->and($manifest['contentType'])->toBe('screen_design')
        ->and($manifest['screenDesignId'])->toBe($fallbackDesign->id)
        ->and($manifest['deploymentId'])->toBe($deployment->id)
        ->and($manifest['deploymentVersion'])->toBe('dep-'.$deployment->id)
        ->and($manifest)->not->toHaveKey('scheduleId');

    // Inside the window the schedule takes precedence over the deployment.
    Carbon::setTestNow('2026-09-14 08:00:00');

    $scheduled = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($scheduled['contentSource'])->toBe('schedule')
        ->and($scheduled['playlistId'])->toBe($playlist->id);
});

test('a screen with no schedule and no deployment reports no content', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);
    $design = manifestDesign($user, $workspace, 'Unused Slide');
    $playlist = manifestPlaylist($user, $workspace, $design, 'Unused Loop');

    // Targets another screen, so this one has nothing to play.
    [$otherScreen] = manifestScreen($user, $workspace, 'Bar TV');
    manifestSchedule($user, $workspace, $playlist, $otherScreen, 'Bar Loop', '07:00', '12:00');

    $manifest = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($manifest['status'])->toBe('no_content')
        ->and($manifest['contentSource'])->toBe('none');

    $check = $this->withToken($token)->getJson(route('player.api.manifest.check'))->assertOk()->json();

    expect($check['version'])->toBeNull()
        ->and($check['content_source'])->toBe('none');
});

test('an inactive screen stays blank even with a matching schedule', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);
    $design = manifestDesign($user, $workspace, 'Breakfast Slide');
    $playlist = manifestPlaylist($user, $workspace, $design, 'Breakfast Loop');
    manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $screen->forceFill(['operational_status' => ScreenOperationalStatus::Inactive])->save();

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'inactive']);
});

test('a paused schedule stops reaching the player', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);
    $design = manifestDesign($user, $workspace, 'Breakfast Slide');
    $playlist = manifestPlaylist($user, $workspace, $design, 'Breakfast Loop');
    $schedule = manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['contentSource' => 'schedule']);

    $this->actingAs($user)->post(route('app.schedules.pause', $schedule))->assertRedirect();

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'no_content', 'contentSource' => 'none']);
});

test('player media access follows the scheduled playlist', function () {
    Storage::fake('public');
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$otherUser, $otherWorkspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);

    // A fresh UploadedFile per asset: hashName() is cached on the instance, so
    // reusing one would give two assets in the same workspace the same path.
    $storeImage = fn (int $workspaceId): string => UploadedFile::fake()
        ->image('slide.jpg')
        ->store('workspaces/'.$workspaceId.'/media', 'public');

    $media = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Image,
        'name' => 'Scheduled Art',
        'storage_disk' => 'public',
        'storage_path' => $storeImage($workspace->id),
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'created_by' => $user->id,
    ]);

    $unused = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Image,
        'name' => 'Unused Art',
        'storage_disk' => 'public',
        'storage_path' => $storeImage($workspace->id),
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'created_by' => $user->id,
    ]);

    $foreign = MediaAsset::factory()->create([
        'workspace_id' => $otherWorkspace->id,
        'type' => MediaType::Image,
        'name' => 'Foreign Art',
        'storage_disk' => 'public',
        'storage_path' => $storeImage($otherWorkspace->id),
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
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

    $design = manifestDesign($user, $workspace, 'Media Slide', $schema);
    $playlist = manifestPlaylist($user, $workspace, $design, 'Media Loop');
    manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $manifest = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($manifest['items'][0]['media'])->toHaveKey((string) $media->id);

    $this->withToken($token)->get(route('player.api.media.show', $media))->assertOk();
    $this->withToken($token)->get(route('player.api.media.show', $unused))->assertForbidden();
    $this->withToken($token)->get(route('player.api.media.show', $foreign))->assertForbidden();

    // Outside the window the scheduled media is no longer authorised.
    Carbon::setTestNow('2026-09-14 18:00:00');

    $this->withToken($token)->get(route('player.api.media.show', $media))->assertForbidden();
});

test('the screen detail page reports the current and next schedule', function () {
    Carbon::setTestNow('2026-09-14 10:00:00');

    [$user, $workspace] = manifestUser();
    [$screen] = manifestScreen($user, $workspace);

    $breakfastDesign = manifestDesign($user, $workspace, 'Breakfast Slide');
    $lunchDesign = manifestDesign($user, $workspace, 'Lunch Slide');
    $breakfastPlaylist = manifestPlaylist($user, $workspace, $breakfastDesign, 'Breakfast Loop');
    $lunchPlaylist = manifestPlaylist($user, $workspace, $lunchDesign, 'Lunch Loop');

    $breakfast = manifestSchedule($user, $workspace, $breakfastPlaylist, $screen, 'Breakfast', '07:00', '12:00');
    $lunch = manifestSchedule($user, $workspace, $lunchPlaylist, $screen, 'Lunch', '12:00', '15:00');

    $this->actingAs($user)
        ->get(route('app.screens.show', $screen))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('screen.content_source', 'schedule')
            ->where('screen.current_schedule.id', $breakfast->id)
            ->where('screen.current_schedule.name', 'Breakfast')
            ->where('screen.current_schedule.playlist_name', 'Breakfast Loop')
            ->where('screen.current_schedule.priority', 10)
            ->where('screen.current_schedule.timezone', 'UTC')
            ->where('screen.current_schedule.ends_at_local', '2026-09-14 12:00')
            ->where('screen.next_schedule.id', $lunch->id)
            ->where('screen.next_schedule.starts_at_local', '2026-09-14 12:00')
        );
});

test('the screen detail page reports deployment content when no schedule matches', function () {
    Carbon::setTestNow('2026-09-14 18:00:00');

    [$user, $workspace] = manifestUser();
    [$screen] = manifestScreen($user, $workspace);

    $design = manifestDesign($user, $workspace, 'Always On');
    $this->actingAs($user)
        ->post(route('app.screens.publish', $screen), ['screen_design_id' => $design->id])
        ->assertRedirect();

    $scheduleDesign = manifestDesign($user, $workspace, 'Breakfast Slide');
    $playlist = manifestPlaylist($user, $workspace, $scheduleDesign, 'Breakfast Loop');
    manifestSchedule($user, $workspace, $playlist, $screen, 'Breakfast', '07:00', '12:00');

    $this->actingAs($user)
        ->get(route('app.screens.show', $screen))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('screen.content_source', 'deployment')
            ->where('screen.current_schedule', null)
            ->where('screen.next_schedule.name', 'Breakfast')
            ->where('screen.next_schedule.starts_at_local', '2026-09-15 07:00')
        );
});

test('a higher priority schedule takes over the manifest mid window', function () {
    Carbon::setTestNow('2026-09-14 12:00:00');

    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);

    $baseDesign = manifestDesign($user, $workspace, 'Day Slide');
    $promoDesign = manifestDesign($user, $workspace, 'Promo Slide');
    $basePlaylist = manifestPlaylist($user, $workspace, $baseDesign, 'Day Loop');
    $promoPlaylist = manifestPlaylist($user, $workspace, $promoDesign, 'Promo Loop');

    manifestSchedule($user, $workspace, $basePlaylist, $screen, 'All Day', '09:00', '17:00', 5);
    $promo = manifestSchedule($user, $workspace, $promoPlaylist, $screen, 'Promo', '11:00', '14:00', 10);

    $manifest = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($manifest['scheduleId'])->toBe($promo->id)
        ->and($manifest['playlistId'])->toBe($promoPlaylist->id)
        ->and($manifest['schedulePriority'])->toBe(10);

    Carbon::setTestNow('2026-09-14 15:00:00');

    $afterPromo = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($afterPromo['playlistId'])->toBe($basePlaylist->id);
});

test('a schedule with an overnight window plays across midnight', function () {
    [$user, $workspace] = manifestUser();
    [$screen, $token] = manifestScreen($user, $workspace);

    $design = manifestDesign($user, $workspace, 'Late Slide');
    $playlist = manifestPlaylist($user, $workspace, $design, 'Late Loop');

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->forPlaylist($playlist)
        ->forScreens($screen)
        ->timezone('UTC')
        ->window('22:00', '02:00', [ScheduleDays::MONDAY])
        ->active()
        ->create(['name' => 'Late Night']);

    // Monday 23:30 and the Tuesday 01:30 tail are the same occurrence.
    Carbon::setTestNow('2026-09-14 23:30:00');
    $evening = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    Carbon::setTestNow('2026-09-15 01:30:00');
    $morning = $this->withToken($token)->getJson(route('player.api.manifest'))->assertOk()->json();

    expect($evening['scheduleId'])->toBe($schedule->id)
        ->and($morning['scheduleId'])->toBe($schedule->id)
        ->and($morning['deploymentVersion'])->toBe($evening['deploymentVersion'])
        ->and($morning['validity']['endsAt'])->toStartWith('2026-09-15T02:00:00');

    Carbon::setTestNow('2026-09-15 02:00:00');

    $this->withToken($token)
        ->getJson(route('player.api.manifest'))
        ->assertOk()
        ->assertJson(['status' => 'no_content']);
});
