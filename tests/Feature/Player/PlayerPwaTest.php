<?php

test('the player PWA manifest is installable and scoped to /player', function () {
    $response = $this->get(route('player.manifest'))->assertOk();

    expect($response->headers->get('content-type'))
        ->toStartWith('application/manifest+json');

    $manifest = json_decode($response->getFile()->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('RMSignage Player')
        ->and($manifest['start_url'])->toBe('/player')
        ->and($manifest['scope'])->toBe('/player')
        ->and($manifest['display'])->toBe('fullscreen');

    $pngIcons = collect($manifest['icons'])->where('type', 'image/png');
    expect($pngIcons->pluck('sizes')->all())->toContain('192x192', '512x512');
});

test('digital asset links are empty until android fingerprints are configured', function () {
    $this->get(route('well-known.assetlinks'))
        ->assertOk()
        ->assertExactJson([]);
});

test('digital asset links include the player package when fingerprints are set', function () {
    config([
        'player.android.package_name' => 'com.rmsignage.player',
        'player.android.sha256_cert_fingerprints' => ['AA:BB:CC:DD'],
    ]);

    $this->get(route('well-known.assetlinks'))
        ->assertOk()
        ->assertJsonPath('0.target.namespace', 'android_app')
        ->assertJsonPath('0.target.package_name', 'com.rmsignage.player')
        ->assertJsonPath('0.target.sha256_cert_fingerprints.0', 'AA:BB:CC:DD');
});

test('the player page advertises the player manifest not the customer PWA', function () {
    $this->withoutVite();

    $this->get(route('player'))
        ->assertOk()
        ->assertDontSee('href="/manifest.webmanifest"', false)
        ->assertSee('href="/player.webmanifest"', false);
});
