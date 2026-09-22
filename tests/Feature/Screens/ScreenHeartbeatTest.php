<?php

use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenHealthStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\WorkspaceRole;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use App\Models\User;
use App\Support\Screens\ScreenPresence;
use Illuminate\Support\Facades\Artisan;

function heartbeatOwner(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function heartbeatPublishedDesign(User $user, $workspace, string $orientation = 'landscape'): ScreenDesign
{
    $factory = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user);

    $factory = $orientation === 'portrait' ? $factory->portrait() : $factory->landscape();

    $design = $factory
        ->withDraftVersion($user)
        ->create(['name' => 'Heartbeat Design']);

    $version = $design->latestVersion();
    $version->forceFill(['published_at' => now()])->save();
    $design->forceFill([
        'status' => ScreenDesignStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $design->fresh(['publishedVersion']) ?? $design;
}

/**
 * Pair a screen through the real pairing flow so the device token matches production.
 *
 * @return array{screen: Screen, device_token: string}
 */
function heartbeatPairScreen(User $user, string $name = 'E2E Heartbeat TV'): array
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

test('paired device heartbeat updates presence and stores a heartbeat row', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user);

    ScreenDevice::findByToken($paired['device_token'])
        ->forceFill(['last_seen_at' => now()->subMinutes(30)])
        ->save();

    $response = $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'player_version' => '1.0.0',
            'viewport_width' => 1920,
            'viewport_height' => 1080,
            'orientation' => 'landscape',
            'playback_state' => 'no_content',
        ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
            'screen_active' => true,
            'heartbeat_interval_seconds' => ScreenPresence::heartbeatIntervalSeconds(),
        ])
        ->json();

    expect($response['recorded_at'])->not->toBeNull();

    $device = ScreenDevice::findByToken($paired['device_token']);
    expect($device->last_seen_at->diffInSeconds(now(), true))->toBeLessThan(5)
        ->and($device->player_version)->toBe('1.0.0')
        ->and($device->viewport_width)->toBe(1920)
        ->and($device->viewport_height)->toBe(1080)
        ->and($device->reported_orientation)->toBe('landscape')
        ->and($device->playback_state)->toBe('no_content');

    $heartbeat = ScreenHeartbeat::query()
        ->where('screen_id', $paired['screen']->id)
        ->latest('recorded_at')
        ->first();

    expect($heartbeat)->not->toBeNull()
        ->and($heartbeat->screen_device_id)->toBe($device->id)
        ->and($heartbeat->playback_state)->toBe('no_content')
        ->and($heartbeat->player_version)->toBe('1.0.0');
});

test('heartbeat rejects missing and invalid device tokens', function () {
    $this->postJson(route('player.api.heartbeat'), [])
        ->assertUnauthorized();

    $this->withToken('not-a-real-device-token')
        ->postJson(route('player.api.heartbeat'), [])
        ->assertUnauthorized();
});

test('revoked device cannot heartbeat', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Revoked TV');

    $this->actingAs($user)
        ->delete(route('app.screens.destroy', $paired['screen']))
        ->assertRedirect(route('app.screens'));

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['playback_state' => 'ready'])
        ->assertUnauthorized();

    expect($paired['screen']->fresh()->pairingState())->toBe('disconnected');
});

test('recent heartbeat is online and a stale heartbeat is offline', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Presence TV');

    $device = ScreenDevice::findByToken($paired['device_token']);

    $device->forceFill(['last_seen_at' => now()->subSeconds(30)])->save();
    expect(ScreenPresence::networkState($device->fresh()))->toBe('online');

    $device->forceFill([
        'last_seen_at' => now()->subSeconds(ScreenPresence::onlineThresholdSeconds() + 30),
    ])->save();
    expect(ScreenPresence::networkState($device->fresh()))->toBe('offline');

    $device->forceFill(['last_seen_at' => null])->save();
    expect(ScreenPresence::networkState($device->fresh()))->toBe('offline');
});

test('active connected online screen with content is healthy', function () {
    [$user, $workspace] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Healthy TV');
    $design = heartbeatPublishedDesign($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'playback_state' => 'ready',
            'orientation' => 'landscape',
            'deployment_id' => $paired['screen']->fresh()->activeDeployment()->id,
        ])
        ->assertOk();

    $screen = $paired['screen']->fresh();
    $device = ScreenDevice::findByToken($paired['device_token']);

    expect(ScreenPresence::health($screen, $device))->toBe(ScreenHealthStatus::Healthy)
        ->and(ScreenPresence::contentSyncState($screen->activeDeployment(), $device)->value)
        ->toBe('up_to_date');
});

test('connected but stale screen reports offline health', function () {
    [$user, $workspace] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Stale TV');
    $design = heartbeatPublishedDesign($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $device = ScreenDevice::findByToken($paired['device_token']);
    $device->forceFill([
        'last_seen_at' => now()->subSeconds(ScreenPresence::onlineThresholdSeconds() + 60),
    ])->save();

    $screen = $paired['screen']->fresh();

    expect(ScreenPresence::health($screen, $device->fresh()))
        ->toBe(ScreenHealthStatus::Offline);
});

test('orientation mismatch flags the screen for attention', function () {
    [$user, $workspace] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Rotated TV');
    $design = heartbeatPublishedDesign($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'playback_state' => 'ready',
            'viewport_width' => 1080,
            'viewport_height' => 1920,
            'orientation' => 'portrait',
        ])
        ->assertOk();

    $screen = $paired['screen']->fresh();
    $device = ScreenDevice::findByToken($paired['device_token']);

    expect(ScreenPresence::hasOrientationMismatch($screen, $device, $screen->activeDeployment()))
        ->toBeTrue()
        ->and(ScreenPresence::health($screen, $device))->toBe(ScreenHealthStatus::Attention);
});

test('content sync is out of sync when the device reports a different deployment', function () {
    [$user, $workspace] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Sync TV');
    $design = heartbeatPublishedDesign($user, $workspace);

    $this->actingAs($user)
        ->post(route('app.screens.publish', $paired['screen']), [
            'screen_design_id' => $design->id,
        ])
        ->assertRedirect();

    $active = $paired['screen']->fresh()->activeDeployment();

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'playback_state' => 'ready',
            'deployment_id' => $active->id + 999,
        ])
        ->assertOk();

    $device = ScreenDevice::findByToken($paired['device_token']);

    expect(ScreenPresence::contentSyncState($active, $device)->value)->toBe('out_of_sync');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'playback_state' => 'ready',
            'deployment_id' => $active->id,
        ])
        ->assertOk();

    expect(ScreenPresence::contentSyncState($active, ScreenDevice::findByToken($paired['device_token']))->value)
        ->toBe('up_to_date');
});

test('screens list exposes presence data and stays inside the workspace', function () {
    [$owner] = heartbeatOwner();
    [$outsider] = heartbeatOwner();
    $paired = heartbeatPairScreen($owner, 'E2E Isolated TV');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['playback_state' => 'no_content'])
        ->assertOk();

    $this->actingAs($owner)
        ->get(route('app.screens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/screens/index')
            ->has('screens', 1)
            ->where('screens.0.name', 'E2E Isolated TV')
            ->where('screens.0.pairing_state', 'connected')
            ->where('screens.0.network_state', 'online')
            ->where('counts.online', 1)
            ->has('filters.filter')
            ->has('heartbeat_interval_seconds')
            ->where('player_url', url('/player')));

    $this->actingAs($outsider)
        ->get(route('app.screens'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('screens', 0));

    $this->actingAs($outsider)
        ->get(route('app.screens.show', $paired['screen']))
        ->assertNotFound();
});

test('screen detail returns recent heartbeats for the workspace owner', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Detail TV');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), [
            'playback_state' => 'no_content',
            'player_version' => '1.0.0',
        ])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('app.screens.show', $paired['screen']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/screens/show')
            ->where('screen.pairing_state', 'connected')
            ->where('screen.network_state', 'online')
            ->has('recent_heartbeats', 1)
            ->where('recent_heartbeats.0.playback_state', 'no_content'));
});

test('platform staff can open screen health while workspace owners cannot', function () {
    [$owner] = heartbeatOwner();
    $paired = heartbeatPairScreen($owner, 'E2E Health TV');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['playback_state' => 'no_content'])
        ->assertOk();

    $superAdmin = User::factory()->admin()->create();
    $platformAdmin = User::factory()->platformAdmin()->create();

    foreach ([$superAdmin, $platformAdmin] as $staff) {
        $this->actingAs($staff)
            ->get(route('admin.screen_health'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/screen-health/index')
                ->where('counts.total', 1)
                ->where('counts.online', 1)
                ->where('counts.connected', 1)
                ->has('screens', 1)
                ->where('screens.0.name', 'E2E Health TV'));
    }

    $this->actingAs($superAdmin)
        ->get(route('admin.screens.show', $paired['screen']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/screens/show')
            ->where('screen.pairing_state', 'connected')
            ->has('screen.recent_heartbeats', 1));

    $this->actingAs($owner)
        ->get(route('admin.screen_health'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.screens.show', $paired['screen']))
        ->assertForbidden();
});

test('prune command deletes heartbeats older than the retention window', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Prune TV');
    $device = ScreenDevice::findByToken($paired['device_token']);

    $fresh = ScreenHeartbeat::query()->create([
        'screen_id' => $paired['screen']->id,
        'screen_device_id' => $device->id,
        'recorded_at' => now()->subHours(2),
        'playback_state' => 'ready',
    ]);

    $stale = ScreenHeartbeat::query()->create([
        'screen_id' => $paired['screen']->id,
        'screen_device_id' => $device->id,
        'recorded_at' => now()->subDays((int) config('screens.heartbeat_retention_days') + 3),
        'playback_state' => 'ready',
    ]);

    Artisan::call('screens:prune-heartbeats');

    expect(ScreenHeartbeat::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(ScreenHeartbeat::query()->whereKey($stale->id)->exists())->toBeFalse();
});

test('inactive screens still accept heartbeats and report screen_active false', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Inactive TV');

    $this->actingAs($user)
        ->post(route('app.screens.status', $paired['screen']), [
            'operational_status' => ScreenOperationalStatus::Inactive->value,
        ])
        ->assertRedirect();

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['playback_state' => 'inactive'])
        ->assertOk()
        ->assertJson(['ok' => true, 'screen_active' => false]);

    $device = ScreenDevice::findByToken($paired['device_token']);
    expect($device->playback_state)->toBe('inactive')
        ->and(ScreenPresence::networkState($device))->toBe('online');
});

test('heartbeat rejects unknown playback states and orientations', function () {
    [$user] = heartbeatOwner();
    $paired = heartbeatPairScreen($user, 'E2E Validation TV');

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['playback_state' => 'dancing'])
        ->assertStatus(422);

    $this->withToken($paired['device_token'])
        ->postJson(route('player.api.heartbeat'), ['orientation' => 'sideways'])
        ->assertStatus(422);
});
