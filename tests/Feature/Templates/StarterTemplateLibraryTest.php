<?php

use App\Actions\ScreenDesigns\CreateScreenDesignFromTemplate;
use App\Actions\ScreenDesigns\SaveScreenDesignDraft;
use App\Actions\Templates\SaveTemplateDraft;
use App\Enums\PlatformRole;
use App\Enums\TemplateStatus;
use App\Enums\WorkspaceRole;
use App\Models\Template;
use App\Models\User;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\LayoutSchemaValidator;
use App\Support\Rendering\StarterTemplateCatalog;
use Database\Seeders\PlatformTemplatesSeeder;

function starterPlatformStaff(): User
{
    $user = User::factory()->create();
    $user->assignPlatformRole(PlatformRole::SuperAdmin);
    attachWorkspace($user);

    return $user->fresh() ?? $user;
}

test('starter template seeding is idempotent and platform-owned', function () {
    starterPlatformStaff();

    $this->seed(PlatformTemplatesSeeder::class);
    $firstCount = Template::query()->platform()->whereNotNull('slug')->count();
    $firstIds = Template::query()->platform()->whereNotNull('slug')->orderBy('slug')->pluck('id', 'slug');

    $this->seed(PlatformTemplatesSeeder::class);
    $secondCount = Template::query()->platform()->whereNotNull('slug')->count();
    $secondIds = Template::query()->platform()->whereNotNull('slug')->orderBy('slug')->pluck('id', 'slug');

    expect($firstCount)->toBe(count(StarterTemplateCatalog::definitions()))
        ->and($secondCount)->toBe($firstCount)
        ->and($secondIds->all())->toBe($firstIds->all());

    $templates = Template::query()->platform()->whereNotNull('slug')->with('publishedVersion')->get();

    foreach ($templates as $template) {
        expect($template->workspace_id)->toBeNull()
            ->and($template->status)->toBe(TemplateStatus::Published)
            ->and($template->publishedVersion)->not->toBeNull()
            ->and($template->publishedVersion?->published_at)->not->toBeNull();

        $schema = LayoutSchemaNormalizer::normalize(
            is_array($template->publishedVersion?->schema) ? $template->publishedVersion->schema : [],
        );
        LayoutSchemaValidator::validate($schema);
        expect(count($schema['elements'] ?? []))->toBeGreaterThan(5);
    }
});

test('customer cannot edit seeded starter templates', function () {
    starterPlatformStaff();
    $this->seed(PlatformTemplatesSeeder::class);

    $owner = User::factory()->create();
    attachWorkspace($owner, null, WorkspaceRole::Owner);
    $template = Template::query()->platform()->where('slug', 'prayer-times-landscape')->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('admin.templates.update', $template), [
            'name' => 'Hacked',
            'schema' => $template->publishedVersion?->schema,
        ])
        ->assertForbidden();
});

test('use template copies schema independently from master', function () {
    $staff = starterPlatformStaff();
    $this->seed(PlatformTemplatesSeeder::class);

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);

    $template = Template::query()->platform()->where('slug', 'retail-sale-promotion')->firstOrFail();
    $original = $template->publishedVersion?->schema;
    expect($original)->toBeArray();

    $design = app(CreateScreenDesignFromTemplate::class)->handle($owner, $workspace, $template);
    $copied = $design->latestVersion()?->schema;

    expect($copied)->toBeArray()
        ->and($copied['elements'])->toEqual($original['elements'] ?? null);

    // Mutate the Screen Design copy.
    $copiedElements = $copied['elements'];
    $textIndex = collect($copiedElements)->search(
        fn (array $element): bool => ($element['type'] ?? null) === 'text',
    );
    expect($textIndex)->not->toBeFalse();
    $copiedElements[$textIndex]['props']['text'] = 'CUSTOMER EDIT';
    $copied['elements'] = $copiedElements;

    app(SaveScreenDesignDraft::class)->handle($owner, $design, [
        'schema' => $copied,
    ]);

    // Mutate the master Template separately (creates a new draft version).
    $masterElements = $original['elements'];
    $masterElements[] = [
        'id' => 'master-only-el',
        'type' => 'text',
        'name' => 'Master Only',
        'x' => 10,
        'y' => 10,
        'width' => 200,
        'height' => 40,
        'props' => ['text' => 'MASTER ONLY', 'fontSize' => 24, 'color' => '#fff'],
    ];
    app(SaveTemplateDraft::class)->handle($staff, $template, [
        'schema' => LayoutSchemaNormalizer::normalize([
            ...$original,
            'elements' => $masterElements,
        ]),
    ]);

    $design->refresh();
    $template->refresh();

    $designSchema = $design->latestVersion()?->schema;
    $templateSchema = $template->latestVersion()?->schema;
    $publishedSchema = $template->publishedVersion?->schema;

    expect(collect($designSchema['elements'] ?? [])->pluck('id'))->not->toContain('master-only-el')
        ->and(collect($designSchema['elements'] ?? [])->firstWhere('type', 'text')['props']['text'] ?? null)->toBe('CUSTOMER EDIT')
        ->and(collect($templateSchema['elements'] ?? [])->pluck('id'))->toContain('master-only-el')
        ->and(collect($publishedSchema['elements'] ?? [])->pluck('id'))->not->toContain('master-only-el');
});

test('layout schema normalizer upgrades legacy flat fields', function () {
    $normalized = LayoutSchemaNormalizer::normalize([
        'schemaVersion' => 1,
        'canvas' => [
            'width' => 1920,
            'height' => 1080,
            'orientation' => 'landscape',
            'background' => ['type' => 'color', 'value' => '#000'],
        ],
        'theme' => 'blank',
        'elements' => [
            [
                'id' => 't1',
                'type' => 'text',
                'x' => 0,
                'y' => 0,
                'width' => 100,
                'height' => 40,
                'text' => 'Hello',
                'fontSize' => 48,
                'color' => '#fff',
            ],
        ],
    ]);

    expect($normalized['elements'][0]['props']['text'])->toBe('Hello')
        ->and($normalized['elements'][0]['props']['fontSize'])->toBe(48)
        ->and($normalized['elements'][0]['name'])->toBe('Hello');
});
