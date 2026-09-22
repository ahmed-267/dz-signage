<?php

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\TemplateOrientation;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Player\PlayerOfflinePackageBuilder;
use App\Support\Schedules\ScheduleDays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function offlinePkgUser(): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Owner);

    return [$user, $workspace];
}

/**
 * @return array{0: Screen, 1: string}
 */
function offlinePkgScreen(User $user, Workspace $workspace): array
{
    $screen = Screen::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create([
            'name' => 'Offline TV',
            'orientation' => 'landscape',
            'operational_status' => ScreenOperationalStatus::Active,
        ]);

    $token = Str::random(64);
    ScreenDevice::factory()->forScreen($screen)->withToken($token)->create();

    return [$screen, $token];
}

function offlinePkgDesign(User $user, Workspace $workspace, string $name): ScreenDesign
{
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
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

function offlinePkgPlaylist(User $user, Workspace $workspace, ScreenDesign $design, string $name): Playlist
{
    $playlist = Playlist::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => $name, 'orientation' => TemplateOrientation::Landscape]);

    $version = $playlist->latestVersion();
    $version->items()->create([
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $design->published_version_id,
        'position' => 1,
        'duration_seconds' => 8,
        'transition' => 'fade',
        'transition_speed' => 'normal',
        'is_active' => true,
    ]);
    $version->forceFill(['published_at' => now()])->save();
    $playlist->forceFill([
        'status' => PlaylistStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $playlist->fresh(['publishedVersion']) ?? $playlist;
}

afterEach(function () {
    Carbon::setTestNow();
});

test('offline package includes authorised design deployment and assets list', function () {
    [$user, $workspace] = offlinePkgUser();
    [$screen, $token] = offlinePkgScreen($user, $workspace);
    $design = offlinePkgDesign($user, $workspace, 'Welcome');

    Deployment::query()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'content_type' => DeploymentContentType::ScreenDesign,
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $design->published_version_id,
        'status' => DeploymentStatus::Active,
        'deployed_by' => $user->id,
        'deployed_at' => now(),
    ]);

    $response = $this->withHeaders(['X-Device-Token' => $token])
        ->getJson('/player/api/offline-package');

    $response->assertOk()
        ->assertJsonPath('current.status', 'ready')
        ->assertJsonPath('current.contentType', 'screen_design')
        ->assertJsonPath('screen.id', $screen->id)
        ->assertJsonStructure([
            'packageVersion',
            'horizonHours',
            'current',
            'scheduleEntries',
            'fallbackDeployment',
            'assets',
        ]);

    expect($response->json('packageVersion'))->toStartWith('pkg-');
    expect($response->json('fallbackDeployment.deploymentVersion'))->not->toBeEmpty();
});

test('offline package includes upcoming schedule windows within the horizon', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-14 10:00:00', 'UTC'));

    [$user, $workspace] = offlinePkgUser();
    [$screen, $token] = offlinePkgScreen($user, $workspace);
    $design = offlinePkgDesign($user, $workspace, 'Lunch Design');
    $playlist = offlinePkgPlaylist($user, $workspace, $design, 'Lunch List');

    $schedule = Schedule::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->create([
            'name' => 'Lunch',
            'status' => ScheduleStatus::Active,
            'playlist_id' => $playlist->id,
            'playlist_version_id' => $playlist->published_version_id,
            'timezone' => 'UTC',
            'days_of_week' => ScheduleDays::weekdays(),
            'start_time' => '12:00',
            'end_time' => '14:00',
            'priority' => 10,
            'activated_at' => now(),
        ]);
    $schedule->screens()->sync([$screen->id]);

    $response = $this->withHeaders(['X-Device-Token' => $token])
        ->getJson('/player/api/offline-package');

    $response->assertOk();
    $entries = $response->json('scheduleEntries');
    expect($entries)->not->toBeEmpty();
    expect(collect($entries)->pluck('scheduleId'))->toContain($schedule->id);
    expect($entries[0]['content']['playlistVersionId'])->toBe($playlist->published_version_id);
});

test('offline package excludes foreign workspace content', function () {
    [$user, $workspace] = offlinePkgUser();
    [$screen, $token] = offlinePkgScreen($user, $workspace);
    $design = offlinePkgDesign($user, $workspace, 'Local');

    Deployment::query()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'content_type' => DeploymentContentType::ScreenDesign,
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $design->published_version_id,
        'status' => DeploymentStatus::Active,
        'deployed_by' => $user->id,
        'deployed_at' => now(),
    ]);

    $other = User::factory()->create();
    $foreign = attachWorkspace($other, null, WorkspaceRole::Owner);
    $foreignDesign = offlinePkgDesign($other, $foreign, 'Foreign');

    $response = $this->withHeaders(['X-Device-Token' => $token])
        ->getJson('/player/api/offline-package');

    $response->assertOk();
    expect($response->json('current.screenDesignId'))->toBe($design->id);
    expect($response->json('current.screenDesignId'))->not->toBe($foreignDesign->id);
});

test('revoked device cannot fetch offline package', function () {
    [$user, $workspace] = offlinePkgUser();
    [$screen, $token] = offlinePkgScreen($user, $workspace);
    $device = $screen->devices()->first();
    $device->forceFill(['revoked_at' => now()])->save();

    $this->withHeaders(['X-Device-Token' => $token])
        ->getJson('/player/api/offline-package')
        ->assertUnauthorized();
});

test('heartbeat stores offline readiness on the device', function () {
    [$user, $workspace] = offlinePkgUser();
    [$screen, $token] = offlinePkgScreen($user, $workspace);

    $this->withHeaders(['X-Device-Token' => $token])
        ->postJson('/player/api/heartbeat', [
            'player_version' => '1.0.0',
            'playback_state' => 'ready',
            'metadata' => [
                'offline' => [
                    'cache_ready' => true,
                    'package_version' => 'pkg-abc',
                    'last_sync_at' => now()->toIso8601String(),
                ],
            ],
        ])
        ->assertOk();

    $device = $screen->devices()->first()->fresh();
    expect(data_get($device->platform_meta, 'offline.cache_ready'))->toBeTrue();
    expect(data_get($device->platform_meta, 'offline.package_version'))->toBe('pkg-abc');
});

test('package version is stable for identical content', function () {
    [$user, $workspace] = offlinePkgUser();
    [$screen] = offlinePkgScreen($user, $workspace);
    $design = offlinePkgDesign($user, $workspace, 'Stable');

    Deployment::query()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'content_type' => DeploymentContentType::ScreenDesign,
        'screen_design_id' => $design->id,
        'screen_design_version_id' => $design->published_version_id,
        'status' => DeploymentStatus::Active,
        'deployed_by' => $user->id,
        'deployed_at' => now(),
    ]);

    $builder = app(PlayerOfflinePackageBuilder::class);
    $a = $builder->build($screen);
    $b = $builder->build($screen);

    expect($a['packageVersion'])->toBe($b['packageVersion']);
});
