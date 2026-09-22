<?php

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Support\Rendering\LayoutSchema;
use App\Support\Widgets\EmbedUrlValidator;
use App\Support\Widgets\SafeRemoteUrl;
use App\Support\Widgets\Weather\WeatherService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function widgetDesignUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

/**
 * @param  list<array<string, mixed>>  $elements
 * @return array<string, mixed>
 */
function widgetSchema(array $elements = []): array
{
    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = $elements;

    return $schema;
}

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function widgetElement(string $type, array $config = [], string $id = 'w-1'): array
{
    return [
        'id' => $id,
        'type' => 'widget',
        'name' => ucfirst($type),
        'x' => 40,
        'y' => 40,
        'width' => 400,
        'height' => 240,
        'zIndex' => 1,
        'props' => [
            'widgetType' => $type,
            'config' => $config,
        ],
    ];
}

test('valid widget schema is accepted on screen design save', function () {
    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([
        widgetElement('clock', [
            'timezone' => 'Europe/London',
            'hourFormat' => '24',
            'showSeconds' => true,
            'showDate' => true,
            'showWeekday' => false,
        ]),
        widgetElement('weather', [
            'location' => 'Algiers',
            'units' => 'c',
        ], 'w-2'),
        widgetElement('embed', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], 'w-3'),
        widgetElement('alert', [
            'title' => 'Notice',
            'message' => 'Doors open at 9',
            'severity' => 'info',
        ], 'w-4'),
    ]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $saved = $design->fresh()->latestVersion()->schema;
    expect($saved['elements'])->toHaveCount(4)
        ->and($saved['elements'][0]['props']['widgetType'])->toBe('clock');
});

test('invalid timezone is rejected', function () {
    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([
        widgetElement('clock', [
            'timezone' => 'Not/ARealZone',
            'hourFormat' => '12',
        ]),
    ]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertSessionHasErrors();
});

test('rss localhost feed url is rejected', function () {
    expect(SafeRemoteUrl::isSafe('http://localhost/feed.xml'))->toBeFalse();

    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([
        widgetElement('news', [
            'feedUrl' => 'http://127.0.0.1/rss.xml',
            'maxItems' => 5,
            'rotationSeconds' => 8,
        ]),
    ]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertSessionHasErrors();
});

test('rss private ip feed url is rejected', function () {
    expect(SafeRemoteUrl::isSafe('http://192.168.1.50/feed.xml'))->toBeFalse();
    expect(SafeRemoteUrl::isSafe('http://10.0.0.8/feed.xml'))->toBeFalse();
    expect(SafeRemoteUrl::isSafe('http://172.16.4.2/feed.xml'))->toBeFalse();

    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([
        widgetElement('calendar', [
            'feedUrl' => 'http://192.168.0.10/calendar.ics',
            'maxEvents' => 5,
        ]),
    ]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertSessionHasErrors();
});

test('embed javascript url is rejected', function () {
    expect(EmbedUrlValidator::isAllowed('javascript:alert(1)'))->toBeFalse();

    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([
        widgetElement('embed', [
            'url' => 'javascript:alert(1)',
        ]),
    ]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertSessionHasErrors();
});

test('embed youtube url is accepted and normalized', function () {
    $normalized = EmbedUrlValidator::normalize('https://youtu.be/dQw4w9WgXcQ');
    expect($normalized)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');

    $watch = EmbedUrlValidator::normalize('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
    expect($watch)->toBe('https://www.youtube.com/embed/dQw4w9WgXcQ');

    $vimeo = EmbedUrlValidator::normalize('https://vimeo.com/123456789');
    expect($vimeo)->toBe('https://player.vimeo.com/video/123456789');
});

test('weather service caches responses', function () {
    Cache::flush();

    Http::fake([
        'geocoding-api.open-meteo.com/*' => Http::response([
            'results' => [[
                'name' => 'Algiers',
                'latitude' => 36.75,
                'longitude' => 3.06,
                'country' => 'Algeria',
            ]],
        ]),
        'api.open-meteo.com/*' => Http::response([
            'current' => [
                'temperature_2m' => 22.4,
                'weather_code' => 1,
            ],
            'daily' => [
                'temperature_2m_max' => [26.0],
                'temperature_2m_min' => [18.0],
            ],
        ]),
    ]);

    $service = app(WeatherService::class);

    $first = $service->fetch('Algiers', 'c');
    $second = $service->fetch('Algiers', 'c');

    expect($first)->not->toBeNull()
        ->and($first['temp'])->toBe(22.4)
        ->and($first['units'])->toBe('c')
        ->and($first['condition'])->toBe('Partly cloudy')
        ->and($second['fetchedAt'])->toBe($first['fetchedAt']);

    Http::assertSentCount(2); // geocode + forecast once; second call served from cache
});

test('cross-workspace media on info_card is rejected', function () {
    [$userA, $workspaceA] = widgetDesignUser();
    [$userB, $workspaceB] = widgetDesignUser();

    $foreignMedia = MediaAsset::factory()
        ->forWorkspace($workspaceB)
        ->createdBy($userB)
        ->create();

    $design = ScreenDesign::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($userA)
        ->withDraftVersion($userA)
        ->create();

    $schema = widgetSchema([
        widgetElement('info_card', [
            'heading' => 'Lobby',
            'body' => 'Welcome',
            'mediaAssetId' => $foreignMedia->id,
        ]),
    ]);

    $this->actingAs($userA)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertSessionHasErrors('schema');
});

test('legacy schema without widgets still validates', function () {
    [$user, $workspace] = widgetDesignUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $schema = widgetSchema([[
        'id' => 'heading-1',
        'type' => 'text',
        'name' => 'Heading',
        'x' => 100,
        'y' => 100,
        'width' => 400,
        'height' => 80,
        'zIndex' => 1,
        'props' => ['text' => 'Hello', 'fontSize' => 42],
    ]]);

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), ['schema' => $schema])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

test('safe remote url rejects metadata and cloud hosts', function () {
    expect(fn () => SafeRemoteUrl::assertSafe('http://169.254.169.254/latest/meta-data'))
        ->toThrow(ValidationException::class);
    expect(fn () => SafeRemoteUrl::assertSafe('http://metadata.google.internal/computeMetadata/v1/'))
        ->toThrow(ValidationException::class);
    expect(fn () => SafeRemoteUrl::assertSafe('file:///etc/passwd'))
        ->toThrow(ValidationException::class);
});
