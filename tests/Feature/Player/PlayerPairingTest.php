<?php

use App\Enums\WorkspaceRole;
use App\Models\PairingSession;
use App\Models\Screen;
use App\Models\User;

test('player pairing create returns code and pair url', function () {
    $response = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated();

    expect($response->json('code'))->toMatch('/^DZ-[A-Z2-9]{4}$/')
        ->and($response->json('pair_url'))->toStartWith(rtrim((string) config('app.url'), '/'))
        ->and($response->json('pair_url'))->toContain('/app/screens/pair/')
        ->and($response->json('expires_in_seconds'))->toBeGreaterThan(0)
        ->and($response->json('expires_in_seconds'))->toBeLessThanOrEqual(600);
});

test('player pairing poll transitions pending to claimed with one-time token', function () {
    $user = User::factory()->create();
    attachWorkspace($user);

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->getJson(route('player.api.pairing_sessions.show', $created['public_id']))
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    $this->actingAs($user)
        ->post(route('app.screens.pair.claim', $created['public_id']), [
            'name' => 'Player Poll Screen',
            'orientation' => 'portrait',
        ])
        ->assertRedirect();

    $first = $this->getJson(route('player.api.pairing_sessions.show', $created['public_id']))
        ->assertOk()
        ->json();

    expect($first['status'])->toBe('claimed')
        ->and($first['device_token'])->toBeString();

    $auth = $this->withToken($first['device_token'])
        ->getJson(route('player.api.manifest.check'))
        ->assertOk()
        ->json();

    expect($auth['screen_active'])->toBeTrue();

    $second = $this->getJson(route('player.api.pairing_sessions.show', $created['public_id']))
        ->assertOk()
        ->json();

    expect($second['status'])->toBe('claimed')
        ->and($second)->not->toHaveKey('device_token');

    $screen = Screen::query()->where('name', 'Player Poll Screen')->first();
    expect($screen)->not->toBeNull()
        ->and($screen->orientation)->toBe('portrait');
});

test('viewer cannot claim pairing sessions', function () {
    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner);
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $created = $this->postJson(route('player.api.pairing_sessions.store'))
        ->assertCreated()
        ->json();

    $this->actingAs($viewer)
        ->post(route('app.screens.pair.store'), [
            'code' => $created['code'],
            'name' => 'Nope',
        ])
        ->assertForbidden();

    expect(PairingSession::query()->where('public_id', $created['public_id'])->first()->isClaimed())->toBeFalse();
});

test('player pairing stores device meta from the TV', function () {
    $response = $this->postJson(route('player.api.pairing_sessions.store'), [
        'device_meta' => [
            'form_factor' => 'tv',
            'player_version' => '1.0.0',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 9; AFTKA)',
            'viewport' => ['width' => 1920, 'height' => 1080],
        ],
    ])->assertCreated();

    $session = PairingSession::query()
        ->where('public_id', $response->json('public_id'))
        ->first();

    expect($session)->not->toBeNull()
        ->and($session->device_meta['form_factor'])->toBe('tv')
        ->and($session->device_meta['player_version'])->toBe('1.0.0')
        ->and($session->device_meta['viewport']['width'])->toBe(1920);
});

test('unknown pairing public id reports expired', function () {
    $this->getJson(route('player.api.pairing_sessions.show', '01HZZZZZZZZZZZZZZZZZZZZZZZ'))
        ->assertOk()
        ->assertJson(['status' => 'expired']);
});
