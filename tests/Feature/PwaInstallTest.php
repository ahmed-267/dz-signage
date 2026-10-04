<?php

use App\Models\User;

test('the customer PWA manifest is served with the correct type', function () {
    $response = $this->get(route('pwa.manifest'))->assertOk();

    expect($response->headers->get('content-type'))
        ->toStartWith('application/manifest+json');

    $manifest = json_decode($response->getFile()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('RMSignage')
        ->and($manifest['start_url'])->toBe('/app/dashboard')
        ->and($manifest['scope'])->toBe('/app/')
        ->and($manifest['display'])->toBe('standalone');
});

test('the customer PWA service worker is scoped to the app and does not cache /app', function () {
    $response = $this->get(route('pwa.service-worker'))
        ->assertOk()
        ->assertHeader('service-worker-allowed', '/app/');

    expect($response->headers->get('content-type'))->toStartWith('application/javascript');

    $body = $response->getFile()->getContent();
    expect($body)
        ->toContain("addEventListener('fetch'")
        ->toContain('scope `/app/`')
        ->not->toContain('/player-sw.js');
});

test('customer and marketing pages advertise the PWA manifest', function () {
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="/manifest.webmanifest"', false)
        ->assertSee('apple-mobile-web-app-capable', false);

    $user = User::factory()->create();
    attachWorkspace($user);

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertSee('href="/manifest.webmanifest"', false);
});

test('the player page does not load the customer PWA manifest', function () {
    $this->withoutVite();

    $this->get(route('player'))
        ->assertOk()
        ->assertDontSee('href="/manifest.webmanifest"', false);
});
