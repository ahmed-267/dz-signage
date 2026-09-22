<?php

use App\Enums\WorkspaceIndustry;
use App\Models\BrandKit;
use App\Models\User;
use App\Support\Ai\AiDesignSchemaBuilder;
use App\Support\Ai\CreativeBrief;
use App\Support\Ai\CreativeBriefBuilder;
use App\Support\Ai\DesignArchetypes;
use App\Support\Ai\DesignQualityValidator;
use App\Support\Ai\ImagePromptEnhancer;

beforeEach(function () {
    config(['ai.provider' => 'fake', 'ai.enabled' => true]);
});

test('creative brief includes brand kit colours fonts and logo', function () {
    $user = User::factory()->create();
    $workspace = attachWorkspace($user);
    $workspace->forceFill(['industry' => WorkspaceIndustry::Retail])->save();

    $kit = BrandKit::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'Northwind',
        'tagline' => 'Quality first',
        'primary_color' => '#112233',
        'secondary_color' => '#445566',
        'accent_color' => '#FF5500',
        'background_color' => '#0A0A0A',
        'text_color' => '#FFFFFF',
        'heading_font' => 'Outfit',
        'body_font' => 'Georgia',
        'logo_media_asset_id' => null,
    ]);

    expect($kit->id)->toBeInt();

    $workspace->load('brandKit');

    $brief = CreativeBriefBuilder::build($workspace, 'Summer trainers sale 40% off', [
        'purpose' => 'promotion',
        'style' => 'bold',
        'orientation' => 'landscape',
        'use_brand_kit' => true,
    ]);

    expect($brief)->toBeInstanceOf(CreativeBrief::class);
    expect($brief->brandName)->toBe('Northwind');
    expect($brief->brandColors)->toContain('#112233');
    expect($brief->brandColors)->toContain('#FF5500');
    expect($brief->headingFont())->toBe('Outfit');
    expect($brief->bodyFont())->toBe('Georgia');
    expect($brief->industry)->toBe('retail');
    expect($brief->headline)->not->toBeEmpty();
});

test('archetype selection maps purpose to expected layout', function () {
    $brief = new CreativeBrief(
        prompt: 'Lunch specials',
        purpose: 'menu',
        style: 'professional',
        orientation: 'landscape',
        industry: 'restaurant',
        useBrandKit: false,
    );

    expect(DesignArchetypes::selectFor($brief))->toBe(DesignArchetypes::MENU);
    expect(DesignArchetypes::variantsFor($brief, 3))->toHaveCount(3);
});

test('welcome purpose selects welcome lobby archetype', function () {
    $brief = new CreativeBrief(
        prompt: 'Welcome visitors to Northgate Centre',
        purpose: 'welcome',
        style: 'professional',
        orientation: 'landscape',
        industry: 'corporate',
        useBrandKit: false,
    );

    expect(DesignArchetypes::selectFor($brief))->toBe(DesignArchetypes::WELCOME);
    expect(DesignArchetypes::all())->toContain(DesignArchetypes::FULL_BLEED);
    expect(DesignArchetypes::all())->toContain(DesignArchetypes::WELCOME);
});

test('quality validator rejects overlapping text and tiny headline fonts', function () {
    $schema = [
        'canvas' => ['width' => 1920, 'height' => 1080, 'background' => ['type' => 'color', 'value' => '#000000']],
        'elements' => [
            [
                'id' => 'a',
                'type' => 'text',
                'name' => 'Headline',
                'x' => 100,
                'y' => 100,
                'width' => 800,
                'height' => 200,
                'props' => [
                    'text' => 'HELLO',
                    'fontSize' => 20,
                    'color' => '#FFFFFF',
                    'fontFamily' => 'Outfit',
                ],
            ],
            [
                'id' => 'b',
                'type' => 'text',
                'name' => 'Other',
                'x' => 120,
                'y' => 120,
                'width' => 800,
                'height' => 200,
                'props' => [
                    'text' => 'WORLD',
                    'fontSize' => 48,
                    'color' => '#FFFFFF',
                    'fontFamily' => 'Outfit',
                ],
            ],
            [
                'id' => 'c',
                'type' => 'shape',
                'name' => 'Panel',
                'x' => 0,
                'y' => 0,
                'width' => 1920,
                'height' => 1080,
                'props' => ['fill' => '#000000', 'shape' => 'rectangle'],
            ],
        ],
    ];

    $result = DesignQualityValidator::validate($schema);
    expect($result['ok'])->toBeFalse();
    expect(implode(' ', $result['issues']))->toContain('Headline font too small');
    expect(implode(' ', $result['issues']))->toContain('overlap');
});

test('image prompt enhancer adds hard no-text instruction', function () {
    $enhanced = ImagePromptEnhancer::enhance('Coffee beans on marble', [
        'aspect' => 'landscape',
        'style' => 'elegant',
        'industry' => 'Café',
        'refinement' => 'brighter',
    ]);

    expect($enhanced)->toContain('no text');
    expect($enhanced)->toContain('Coffee beans');
    expect($enhanced)->toContain('Café');
    expect(strtolower($enhanced))->toContain('negative space');
});

test('archetype design builds within canvas bounds', function () {
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
        body: 'In stores and online this weekend only',
        brandColors: ['#0F172A', '#334155', '#0D9488', '#0B1220', '#F8FAFC'],
        brandFonts: ['heading' => 'Outfit', 'body' => 'system-ui'],
    );

    $built = AiDesignSchemaBuilder::buildFromBrief($brief);
    $schema = $built['schema'];
    $w = (int) $schema['canvas']['width'];
    $h = (int) $schema['canvas']['height'];

    expect($schema['elements'])->not->toBeEmpty();

    foreach ($schema['elements'] as $el) {
        expect($el['x'])->toBeGreaterThanOrEqual(0);
        expect($el['y'])->toBeGreaterThanOrEqual(0);
        expect($el['x'] + $el['width'])->toBeLessThanOrEqual($w + 1);
        expect($el['y'] + $el['height'])->toBeLessThanOrEqual($h + 1);
    }

    $portrait = new CreativeBrief(
        prompt: $brief->prompt,
        purpose: 'welcome',
        style: 'elegant',
        orientation: 'portrait',
        industry: 'hotel',
        useBrandKit: false,
        headline: 'WELCOME',
        subheadline: 'Glad you are here',
        cta: 'Explore',
        body: 'Check in at reception',
        brandColors: $brief->brandColors,
        brandFonts: $brief->brandFonts,
    );

    $portraitSchema = AiDesignSchemaBuilder::buildFromBrief($portrait)['schema'];
    expect($portraitSchema['canvas']['width'])->toBe(1080);
    expect($portraitSchema['canvas']['height'])->toBe(1920);
});
