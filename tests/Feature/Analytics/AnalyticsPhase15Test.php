<?php

use App\Enums\DeploymentStatus;
use App\Enums\PlaybackEventType;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\PlaybackEvent;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;

function analyticsOwner(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function analyticsPairScreen(User $user, string $name = 'Analytics TV'): array
{
    $create = test()->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    test()->actingAs($user)
        ->post(route('app.screens.pair.store'), [
            'code' => $create['code'],
            'name' => $name,
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $poll = test()->getJson(route('player.api.pairing_sessions.show', $create['public_id']))
        ->assertOk()
        ->json();

    return [
        'screen' => Screen::query()->where('name', $name)->firstOrFail(),
        'device_token' => $poll['device_token'],
    ];
}

test('owner can view analytics dashboard', function () {
    [$user, $workspace] = analyticsOwner();
    $screen = Screen::factory()->forWorkspace($workspace)->create(['name' => 'Lobby']);

    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'stat_date' => now()->toDateString(),
        'online_seconds' => 3600,
        'playback_seconds' => 1200,
        'content_play_count' => 4,
        'error_count' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('app.analytics', ['range' => '7d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/analytics/index')
            ->where('filters.range', '7d')
            ->where('tab', 'overview')
            ->where('dashboard.overview.total_screens', 1)
            ->where('dashboard.overview.content_play_count', 4)
            ->where('dashboard.empty', false)
            ->has('dashboard.screens', 1)
        );
});

test('analytics tab query is validated and preserved with filters', function () {
    [$user, $workspace] = analyticsOwner();
    $screen = Screen::factory()->forWorkspace($workspace)->create();

    $this->actingAs($user)
        ->get(route('app.analytics', [
            'range' => '30d',
            'tab' => 'tvs',
            'screen_id' => $screen->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tab', 'tvs')
            ->where('filters.range', '30d')
            ->where('filters.screen_id', $screen->id)
        );

    $this->actingAs($user)
        ->get(route('app.analytics', ['tab' => 'not-a-tab']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tab', 'overview'));
});

test('analytics is workspace isolated', function () {
    [$userA, $workspaceA] = analyticsOwner();
    [$userB, $workspaceB] = analyticsOwner();

    $screenA = Screen::factory()->forWorkspace($workspaceA)->create();
    $screenB = Screen::factory()->forWorkspace($workspaceB)->create();

    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspaceA->id,
        'screen_id' => $screenA->id,
        'stat_date' => now()->toDateString(),
        'content_play_count' => 9,
    ]);
    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspaceB->id,
        'screen_id' => $screenB->id,
        'stat_date' => now()->toDateString(),
        'content_play_count' => 99,
    ]);

    $this->actingAs($userA)
        ->get(route('app.analytics'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.overview.content_play_count', 9)
            ->where('dashboard.overview.total_screens', 1)
        );

    $this->actingAs($userA)
        ->get(route('app.analytics', ['screen_id' => $screenB->id]))
        ->assertNotFound();
});

test('viewer can read analytics', function () {
    [$user] = analyticsOwner(WorkspaceRole::Viewer);

    $this->actingAs($user)
        ->get(route('app.analytics'))
        ->assertOk();
});

test('player playback events are recorded with idempotency', function () {
    [$user, $workspace] = analyticsOwner();
    $paired = analyticsPairScreen($user);
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();
    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();

    $payload = [
        'events' => [
            [
                'type' => PlaybackEventType::ContentStarted->value,
                'screen_design_version_id' => $version->id,
                'idempotency_key' => 'test-start-1',
                'occurred_at' => now()->toIso8601String(),
            ],
        ],
    ];

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.playback_events'), $payload)
        ->assertOk()
        ->assertJson(['ok' => true, 'accepted' => 1, 'skipped' => 0]);

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.playback_events'), $payload)
        ->assertOk()
        ->assertJson(['ok' => true, 'accepted' => 0, 'skipped' => 1]);

    expect(PlaybackEvent::query()->where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('player cannot attribute events to another workspace design', function () {
    [$userA, $workspaceA] = analyticsOwner();
    [$userB, $workspaceB] = analyticsOwner();
    $paired = analyticsPairScreen($userA);

    $foreignDesign = ScreenDesign::factory()
        ->forWorkspace($workspaceB)
        ->createdBy($userB)
        ->withDraftVersion($userB)
        ->create();
    $foreignVersion = $foreignDesign->latestVersion();

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.playback_events'), [
            'events' => [
                [
                    'type' => PlaybackEventType::ContentStarted->value,
                    'screen_design_version_id' => $foreignVersion->id,
                    'idempotency_key' => 'cross-ws-1',
                ],
            ],
        ])
        ->assertOk()
        ->assertJson(['accepted' => 0, 'skipped' => 1]);

    expect(PlaybackEvent::query()->count())->toBe(0);
});

test('publishing metrics classify pending separately from failed', function () {
    [$user, $workspace] = analyticsOwner();
    $screen = Screen::factory()->forWorkspace($workspace)->create();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();
    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();

    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Pending,
            'deployed_by' => $user->id,
            'created_at' => now(),
        ]);
    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Failed,
            'deployed_by' => $user->id,
            'created_at' => now(),
        ]);
    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Active,
            'deployed_by' => $user->id,
            'created_at' => now(),
        ]);

    $this->actingAs($user)
        ->get(route('app.analytics', ['range' => '7d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.publishing.pending', 1)
            ->where('dashboard.publishing.failed', 1)
            ->where('dashboard.publishing.active', 1)
            ->where('dashboard.publishing.total', 3)
        );
});

test('analytics series include availability percent errors and publishing trend', function () {
    [$user, $workspace] = analyticsOwner();
    $screen = Screen::factory()->forWorkspace($workspace)->create(['name' => 'Lobby']);

    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();

    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'stat_date' => $yesterday,
        'online_seconds' => 7200,
        'offline_seconds' => 800,
        'playback_seconds' => 600,
        'content_play_count' => 3,
        'error_count' => 2,
    ]);
    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'stat_date' => $today,
        'online_seconds' => 3600,
        'offline_seconds' => 0,
        'playback_seconds' => 300,
        'content_play_count' => 1,
        'error_count' => 0,
    ]);

    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();
    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();

    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Active,
            'deployed_by' => $user->id,
            'created_at' => now()->subDay()->setTime(10, 0),
        ]);
    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Superseded,
            'deployed_by' => $user->id,
            'created_at' => now()->subDay()->setTime(12, 0),
        ]);
    Deployment::factory()
        ->forScreen($screen)
        ->forDesign($design, $version)
        ->create([
            'status' => DeploymentStatus::Failed,
            'deployed_by' => $user->id,
            'created_at' => now()->setTime(9, 0),
        ]);

    $this->actingAs($user)
        ->get(route('app.analytics', ['range' => '7d']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.series.availability', 7)
            ->has('dashboard.series.playback', 7)
            ->has('dashboard.series.errors', 7)
            ->has('dashboard.series.publishing_trend', 7)
            ->where('dashboard.overview.availability_percent', fn ($v) => $v !== null && $v > 0)
            ->where('dashboard.series.availability', function ($series) use ($yesterday) {
                $row = collect($series)->firstWhere('date', $yesterday);

                return $row
                    && $row['online_seconds'] === 7200
                    && $row['offline_seconds'] === 800
                    && abs($row['availability_percent'] - 90.0) < 0.2;
            })
            ->where('dashboard.series.errors', function ($series) use ($yesterday) {
                $row = collect($series)->firstWhere('date', $yesterday);

                return $row && $row['error_count'] === 2;
            })
            ->where('dashboard.series.publishing_trend', function ($series) use ($yesterday) {
                $row = collect($series)->firstWhere('date', $yesterday);

                return $row
                    && $row['active'] === 1
                    && $row['superseded'] === 1
                    && $row['failed'] === 0
                    && $row['total'] === 2;
            })
            ->where('dashboard.publishing.failed', 1)
            ->where('dashboard.publishing.superseded', 1)
        );
});

test('analytics screen filter scopes series and leaderboards', function () {
    [$user, $workspace] = analyticsOwner();
    $lobby = Screen::factory()->forWorkspace($workspace)->create(['name' => 'Lobby']);
    $kitchen = Screen::factory()->forWorkspace($workspace)->create(['name' => 'Kitchen']);

    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $lobby->id,
        'stat_date' => now()->toDateString(),
        'online_seconds' => 1000,
        'offline_seconds' => 0,
        'content_play_count' => 5,
        'error_count' => 1,
    ]);
    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $kitchen->id,
        'stat_date' => now()->toDateString(),
        'online_seconds' => 500,
        'offline_seconds' => 500,
        'content_play_count' => 50,
        'error_count' => 9,
    ]);

    $this->actingAs($user)
        ->get(route('app.analytics', ['range' => '7d', 'screen_id' => $lobby->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.overview.total_screens', 1)
            ->where('dashboard.overview.content_play_count', 5)
            ->where('dashboard.overview.error_count', 1)
            ->has('dashboard.screens', 1)
            ->where('dashboard.screens.0.id', $lobby->id)
        );
});

test('customer dashboard exposes thirty day availability and content activity', function () {
    [$user, $workspace] = analyticsOwner();
    $screen = Screen::factory()->forWorkspace($workspace)->create();

    ScreenDailyStat::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'stat_date' => now()->subDays(2)->toDateString(),
        'online_seconds' => 3600,
        'offline_seconds' => 400,
        'playback_seconds' => 900,
    ]);

    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Promo Board']);
    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();

    PlaybackEvent::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $screen->id,
        'type' => PlaybackEventType::ContentStarted,
        'screen_design_version_id' => $version->id,
        'occurred_at' => now()->subDay(),
        'duration_seconds' => 30,
        'idempotency_key' => 'dash-content-1',
    ]);

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->has('availabilitySeries', 30)
            ->where('metrics.availability_percent', fn ($v) => $v !== null)
            ->where('metrics.playback_seconds', 900)
            ->has('contentActivity', 1)
            ->where('contentActivity.0.screen_design_name', 'Promo Board')
            ->where('contentActivity.0.plays', 1)
            ->where('metrics.screens.healthy', fn ($v) => is_int($v))
        );
});

test('health and ready endpoints respond', function () {
    $this->getJson(route('health'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    $this->getJson(route('ready'))
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('analytics aggregate daily command writes screen daily stats', function () {
    [$user, $workspace] = analyticsOwner();
    $paired = analyticsPairScreen($user, 'Agg TV');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'player_version' => '1.0.0',
            'playback_state' => 'ready',
        ])
        ->assertOk();

    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();
    $version = $design->latestVersion();

    PlaybackEvent::factory()->create([
        'workspace_id' => $workspace->id,
        'screen_id' => $paired['screen']->id,
        'screen_device_id' => ScreenDevice::findByToken($paired['device_token'])?->id,
        'type' => PlaybackEventType::ContentStarted,
        'screen_design_version_id' => $version->id,
        'occurred_at' => now()->subDay()->setTime(12, 0),
        'duration_seconds' => 60,
        'idempotency_key' => 'agg-1',
    ]);

    $this->artisan('analytics:aggregate-daily', [
        'date' => now()->subDay()->toDateString(),
    ])->assertSuccessful();

    expect(ScreenDailyStat::query()
        ->where('screen_id', $paired['screen']->id)
        ->where('stat_date', now()->subDay()->toDateString())
        ->exists())->toBeTrue();
});
