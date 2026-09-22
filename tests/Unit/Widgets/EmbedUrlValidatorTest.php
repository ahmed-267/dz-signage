<?php

use App\Support\Widgets\EmbedUrlValidator;
use App\Support\Widgets\WidgetConfigValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('youtube watch urls resolve to official embed player', function () {
    $resolved = EmbedUrlValidator::resolve(
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    );

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_YOUTUBE)
        ->and($resolved['play_url'])->toStartWith('https://www.youtube.com/embed/dQw4w9WgXcQ');

    expect(EmbedUrlValidator::normalize('https://youtu.be/dQw4w9WgXcQ'))
        ->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');
});

test('youtube live path resolves', function () {
    $resolved = EmbedUrlValidator::resolve(
        'https://www.youtube.com/live/dQw4w9WgXcQ',
    );

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_YOUTUBE)
        ->and($resolved['play_url'])->toContain('/embed/dQw4w9WgXcQ');
});

test('vimeo urls resolve to player embed', function () {
    expect(EmbedUrlValidator::normalize('https://vimeo.com/123456789'))
        ->toBe('https://player.vimeo.com/video/123456789');
});

test('hls and direct video urls are classified', function () {
    $hls = EmbedUrlValidator::resolve('https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8');
    expect($hls['kind'])->toBe(EmbedUrlValidator::KIND_HLS)
        ->and($hls['play_url'])->toEndWith('.m3u8');

    $mp4 = EmbedUrlValidator::resolve('https://example.com/clips/demo.mp4');
    expect($mp4['kind'])->toBe(EmbedUrlValidator::KIND_VIDEO);
});

test('known blocked news hosts return blocked kind with guidance', function () {
    $resolved = EmbedUrlValidator::resolve('https://www.bbc.co.uk/news');

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_BLOCKED)
        ->and($resolved['play_url'])->toBeNull()
        ->and($resolved['message'])->toBe('BBC does not permit this page to be embedded directly. Use the News Widget, an official embeddable video, or a supported live-stream URL.');

    expect(fn () => EmbedUrlValidator::normalize('https://www.bbc.com/news'))
        ->toThrow(ValidationException::class);
});

test('localhost and private hosts are rejected', function () {
    expect(fn () => EmbedUrlValidator::resolve('https://127.0.0.1/stream.m3u8'))
        ->toThrow(ValidationException::class);

    expect(fn () => EmbedUrlValidator::resolve('https://localhost/video.mp4'))
        ->toThrow(ValidationException::class);
});

test('widget schema persist normalizes youtube watch urls', function () {
    $schema = [
        'schemaVersion' => 1,
        'canvas' => ['width' => 1920, 'height' => 1080, 'orientation' => 'landscape', 'background' => ['type' => 'color', 'value' => '#000']],
        'theme' => 'blank',
        'elements' => [[
            'id' => 'embed-1',
            'type' => 'widget',
            'name' => 'Embed',
            'x' => 0,
            'y' => 0,
            'width' => 800,
            'height' => 450,
            'zIndex' => 1,
            'props' => [
                'widgetType' => 'embed',
                'config' => [
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'provider' => 'auto',
                ],
            ],
        ]],
    ];

    $normalized = WidgetConfigValidator::validateSchema($schema);
    $config = $normalized['elements'][0]['props']['config'];

    expect($config['url'])->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ')
        ->and($config['kind'])->toBe('youtube')
        ->and($config['source_url'])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

test('youtube channel live urls are resolved through oembed', function () {
    Http::fake([
        'www.youtube.com/oembed*' => Http::response([
            'html' => '<iframe src="https://www.youtube.com/embed/abc123XYZ"></iframe>',
        ]),
    ]);

    $resolved = EmbedUrlValidator::resolve('https://www.youtube.com/@NASA/live');

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_YOUTUBE)
        ->and($resolved['play_url'])->toContain('/embed/abc123XYZ');
});

test('youtube videos that disallow embedding are reported', function () {
    Http::fake([
        'www.youtube.com/oembed*' => Http::response('', 401),
    ]);

    $resolved = EmbedUrlValidator::resolve('https://www.youtube.com/watch?v=dQw4w9WgXcQ', [
        'check_embeddable' => true,
    ]);

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_BLOCKED)
        ->and($resolved['message'])->toBe('This YouTube video does not allow embedding.');
});

test('drm and dash urls are classified clearly', function () {
    $drm = EmbedUrlValidator::resolve('https://www.primevideo.com/detail/foo');
    expect($drm['kind'])->toBe(EmbedUrlValidator::KIND_DRM)
        ->and($drm['play_url'])->toBeNull()
        ->and($drm['message'])->toContain('third-party signage');

    $dash = EmbedUrlValidator::resolve('https://example.com/stream.mpd');
    expect($dash['kind'])->toBe(EmbedUrlValidator::KIND_DASH)
        ->and($dash['play_url'])->toBe('https://example.com/stream.mpd')
        ->and($dash['message'])->toBeNull();
});

test('teams meeting links are unsupported while stream embeds are allowed', function () {
    $meeting = EmbedUrlValidator::resolve(
        'https://teams.microsoft.com/l/meetup-join/19%3ameeting_abc/0',
    );
    expect($meeting['kind'])->toBe(EmbedUrlValidator::KIND_UNSUPPORTED)
        ->and($meeting['play_url'])->toBeNull()
        ->and($meeting['message'])->toContain('Microsoft Teams experience');

    $embed = EmbedUrlValidator::resolve(
        'https://web.microsoftstream.com/embed/video/abcd-1234',
    );
    expect($embed['kind'])->toBe(EmbedUrlValidator::KIND_TEAMS)
        ->and($embed['play_url'])->not->toBeNull();
});

test('zoom and webex meeting links explain why they cannot embed', function () {
    $zoom = EmbedUrlValidator::resolve('https://zoom.us/j/123456789');
    expect($zoom['kind'])->toBe(EmbedUrlValidator::KIND_UNSUPPORTED)
        ->and($zoom['message'])->toContain('Zoom');

    $webex = EmbedUrlValidator::resolve('https://company.webex.com/meet/jane');
    expect($webex['kind'])->toBe(EmbedUrlValidator::KIND_UNSUPPORTED)
        ->and($webex['message'])->toContain('Webex');
});

test('websites that send x-frame-options are blocked when framing is checked', function () {
    Http::fake([
        'example.com/*' => Http::response('', 200, [
            'X-Frame-Options' => 'DENY',
        ]),
    ]);

    $resolved = EmbedUrlValidator::resolve('https://example.com/story', [
        'check_framing' => true,
    ]);

    expect($resolved['kind'])->toBe(EmbedUrlValidator::KIND_BLOCKED)
        ->and($resolved['play_url'])->toBeNull();
});
