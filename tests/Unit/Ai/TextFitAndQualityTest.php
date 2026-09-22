<?php

use App\Enums\WorkspaceIndustry;
use App\Models\User;
use App\Support\Ai\AiDesignSchemaBuilder;
use App\Support\Ai\CreativeBrief;
use App\Support\Ai\CreativeBriefBuilder;
use App\Support\Ai\DesignArchetypes;
use App\Support\Ai\DesignQualityValidator;
use App\Support\Ai\Dto\DesignPlanResult;
use App\Support\Ai\TextFit;

beforeEach(function () {
    config([
        'ai.provider' => 'fake',
        'ai.enabled' => true,
        'ai.quality_mode' => 'premium',
        'ai.design.max_elements' => 14,
        'ai.design.min_margin' => 48,
    ]);
});

test('TextFit shrinks oversized font to fit the box', function () {
    $result = TextFit::fit(
        'HELLO WORLD',
        200,
        400,
        80,
        28,
        200,
    );

    expect($result['fits'])->toBeTrue();
    expect($result['fontSize'])->toBeLessThan(200);
    expect($result['fontSize'])->toBeGreaterThanOrEqual(28);
    expect($result['truncated'])->toBeFalse();
});

test('TextFit truncates when text cannot fit even at min font', function () {
    $huge = str_repeat('OVERFLOWING SIGNAGE COPY ', 20);
    $result = TextFit::fit($huge, 64, 200, 60, 28, 64, maxChars: 120);

    expect($result['fontSize'])->toBe(28);
    expect(mb_strlen($result['text']))->toBeLessThan(mb_strlen($huge));
    expect($result['truncated'])->toBeTrue();
    expect(TextFit::fits($result['text'], $result['fontSize'], 200, 60))->toBeTrue();
});

test('TextFit reports when huge text does not fit', function () {
    expect(TextFit::fits('A VERY LONG HEADLINE THAT WILL NOT FIT', 120, 100, 40))->toBeFalse();
    expect(TextFit::fits('OK', 48, 400, 80))->toBeTrue();
});

test('quality normalize fits text so huge fonts do not remain clipped', function () {
    $schema = [
        'canvas' => ['width' => 1920, 'height' => 1080, 'background' => ['type' => 'color', 'value' => '#000000']],
        'elements' => [
            [
                'id' => 'a',
                'type' => 'text',
                'name' => 'Headline',
                'x' => 100,
                'y' => 100,
                'width' => 400,
                'height' => 80,
                'props' => [
                    'text' => 'THIS HEADLINE IS WAY TOO BIG FOR THE BOX',
                    'fontSize' => 180,
                    'color' => '#FFFFFF',
                    'fontFamily' => 'Outfit',
                ],
            ],
            [
                'id' => 'b',
                'type' => 'shape',
                'name' => 'Panel',
                'x' => 0,
                'y' => 0,
                'width' => 1920,
                'height' => 1080,
                'props' => ['fill' => '#000000', 'shape' => 'rectangle'],
            ],
            [
                'id' => 'c',
                'type' => 'text',
                'name' => 'CTA',
                'x' => 100,
                'y' => 400,
                'width' => 300,
                'height' => 60,
                'props' => [
                    'text' => 'Shop now',
                    'fontSize' => 36,
                    'color' => '#FFFFFF',
                    'fontFamily' => 'Outfit',
                ],
            ],
        ],
    ];

    $normalized = DesignQualityValidator::normalize($schema);
    $headline = collect($normalized['elements'])->firstWhere('name', 'Headline');
    expect($headline)->not->toBeNull();
    expect($headline['props']['fontSize'])->toBeLessThan(180);
    expect(TextFit::fits(
        (string) $headline['props']['text'],
        (float) $headline['props']['fontSize'],
        (float) $headline['width'],
        (float) $headline['height'],
    ))->toBeTrue();
});

test('refine pushes content into min margin', function () {
    $schema = [
        'canvas' => ['width' => 1920, 'height' => 1080, 'background' => ['type' => 'color', 'value' => '#000000']],
        'elements' => [
            [
                'id' => 'a',
                'type' => 'text',
                'name' => 'Headline',
                'x' => 4,
                'y' => 4,
                'width' => 600,
                'height' => 120,
                'props' => [
                    'text' => 'SALE',
                    'fontSize' => 64,
                    'color' => '#FFFFFF',
                    'fontFamily' => 'Outfit',
                ],
            ],
            [
                'id' => 'b',
                'type' => 'shape',
                'name' => 'Backdrop',
                'x' => 0,
                'y' => 0,
                'width' => 1920,
                'height' => 1080,
                'props' => ['fill' => '#000000', 'shape' => 'rectangle'],
            ],
            [
                'id' => 'c',
                'type' => 'text',
                'name' => 'CTA',
                'x' => 100,
                'y' => 400,
                'width' => 280,
                'height' => 64,
                'props' => [
                    'text' => 'Shop now',
                    'fontSize' => 36,
                    'color' => '#0D9488',
                    'fontFamily' => 'Outfit',
                ],
            ],
        ],
    ];

    $refined = DesignQualityValidator::refine($schema);
    $headline = collect($refined['elements'])->firstWhere('name', 'Headline');
    expect($headline['x'])->toBeGreaterThanOrEqual(48);
    expect($headline['y'])->toBeGreaterThanOrEqual(48);
});

test('provider freeform coordinates are ignored in favour of archetype geometry', function () {
    $brief = new CreativeBrief(
        prompt: 'Weekend sale 30% off',
        purpose: 'promotion',
        style: 'professional',
        orientation: 'landscape',
        industry: 'retail',
        useBrandKit: false,
        headline: 'WEEKEND SALE',
        subheadline: '30% off selected lines',
        cta: 'Shop now',
        body: 'In stores this weekend',
        brandColors: ['#0F172A', '#334155', '#0D9488', '#0B1220', '#F8FAFC'],
        brandFonts: ['heading' => 'Outfit', 'body' => 'system-ui'],
        meta: ['suggested_name' => 'AI Sale'],
    );

    $archetype = DesignArchetypes::selectFor($brief);
    $geo = DesignArchetypes::plan($brief, $archetype);

    $providerPlan = new DesignPlanResult(
        elements: [
            [
                'type' => 'text',
                'name' => 'Headline',
                'x' => 999,
                'y' => 999,
                'width' => 50,
                'height' => 50,
                'text' => 'PROVIDER HEADLINE',
                'color' => '#FF00AA',
                'fontSize' => 12,
            ],
            [
                'type' => 'text',
                'name' => 'CTA',
                'x' => 1,
                'y' => 1,
                'width' => 40,
                'height' => 40,
                'text' => 'Buy today',
            ],
        ],
        background: ['type' => 'color', 'value' => '#112233'],
        suggestedName: 'From Provider',
        model: 'test-freeform',
        archetype: $archetype,
    );

    $merged = AiDesignSchemaBuilder::mergeProviderCopyOntoArchetype($geo, $providerPlan);
    $headline = collect($merged->elements)->first(
        fn ($el) => is_array($el) && strtolower((string) ($el['name'] ?? '')) === 'headline',
    );

    expect($headline)->not->toBeNull();
    expect($headline['text'])->toBe('PROVIDER HEADLINE');
    // Geometry must match archetype — never provider freeform.
    $geoHeadline = collect($geo->elements)->first(
        fn ($el) => is_array($el) && strtolower((string) ($el['name'] ?? '')) === 'headline',
    );
    expect($headline['x'])->toBe($geoHeadline['x']);
    expect($headline['y'])->toBe($geoHeadline['y']);
    expect($headline['width'])->toBe($geoHeadline['width']);
    expect($headline['height'])->toBe($geoHeadline['height']);
    expect($headline['x'])->not->toBe(999);

    $built = AiDesignSchemaBuilder::buildFromBrief($brief, $archetype, $providerPlan);
    $schemaHeadline = collect($built['schema']['elements'])->first(
        fn ($el) => strtolower((string) ($el['name'] ?? '')) === 'headline',
    );
    expect($schemaHeadline['props']['text'])->toBe('PROVIDER HEADLINE');
    expect($schemaHeadline['x'])->toBe($geoHeadline['x']);
    expect($built['plan']->suggestedName)->toBe('From Provider');
});

test('creative brief deriveCopy prefers short sports headlines', function () {
    $user = User::factory()->create();
    $workspace = attachWorkspace($user);
    $workspace->forceFill(['industry' => WorkspaceIndustry::Other])->save();

    $brief = CreativeBriefBuilder::build(
        $workspace,
        'Please create a screen for the Manchester United vs Liverpool match this Saturday',
        ['purpose' => 'event', 'style' => 'bold', 'orientation' => 'landscape', 'use_brand_kit' => false],
    );

    expect($brief->headline)->toBe('MANCHESTER UNITED VS LIVERPOOL');
    expect(mb_strlen($brief->headline))->toBeLessThan(40);
    expect($brief->headline)->not->toContain('PLEASE CREATE');
    expect(strtolower($brief->subheadline))->toContain('saturday');
});

test('event archetype builds within max elements and margins', function () {
    $brief = new CreativeBrief(
        prompt: 'United vs City derby this weekend',
        purpose: 'event',
        style: 'bold',
        orientation: 'landscape',
        industry: 'other',
        useBrandKit: false,
        headline: 'UNITED VS CITY',
        subheadline: 'Live · This Weekend',
        cta: 'Watch live',
        body: 'Kick-off Saturday',
        brandColors: ['#111827', '#F97316', '#EF4444', '#0B1220', '#F8FAFC'],
        brandFonts: ['heading' => 'Outfit', 'body' => 'system-ui'],
    );

    $built = AiDesignSchemaBuilder::buildFromBrief($brief, DesignArchetypes::EVENT);
    $schema = $built['schema'];
    $max = (int) config('ai.design.max_elements', 14);
    $minMargin = (int) config('ai.design.min_margin', 48);

    expect(count($schema['elements']))->toBeLessThanOrEqual($max);
    expect(count($schema['elements']))->toBeGreaterThanOrEqual(3);

    foreach ($schema['elements'] as $el) {
        if (($el['type'] ?? '') !== 'text') {
            continue;
        }
        expect($el['x'])->toBeGreaterThanOrEqual($minMargin);
        expect($el['y'])->toBeGreaterThanOrEqual($minMargin);
        expect(TextFit::fits(
            (string) ($el['props']['text'] ?? ''),
            (float) ($el['props']['fontSize'] ?? 28),
            (float) $el['width'],
            (float) $el['height'],
        ))->toBeTrue();
    }
});
