<?php

use App\Actions\ScreenDesigns\CreateScreenDesignFromTemplate;
use App\Enums\MediaType;
use App\Enums\PlatformRole;
use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\BrandKit;
use App\Models\MediaAsset;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Support\BrandKit\BrandKitSchemaApplier;
use App\Support\Rendering\LayoutSchema;
use App\Support\Rendering\LayoutSchemaBuilder;
use Database\Seeders\PlatformTemplatesSeeder;

function brandKitApplierUser(): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, WorkspaceRole::Owner);

    return [$user, $workspace];
}

function brandBoundSchema(): array
{
    return LayoutSchemaBuilder::make(
        TemplateOrientation::Landscape,
        TemplateTheme::Minimal,
        [
            LayoutSchemaBuilder::text('title', 'Placeholder Biz', 40, 40, 800, 80, [
                'text' => 'Placeholder Biz',
                'fontSize' => 48,
                'fontWeight' => 700,
                'color' => '#111111',
                'brandBinding' => 'brand.business_name',
            ]),
            LayoutSchemaBuilder::text('tagline', 'Placeholder tagline', 40, 140, 800, 48, [
                'text' => 'Placeholder tagline',
                'fontSize' => 24,
                'brandBinding' => 'brand.tagline',
            ]),
            LayoutSchemaBuilder::logoPlaceholder('logo', 'Logo', 40, 220, 160, 100, [
                'brandBinding' => 'brand.logo',
            ]),
            LayoutSchemaBuilder::panel('panel', 'Panel', 40, 360, 400, 120, [
                'fill' => '#EEEEEE',
                'brandBinding' => 'brand.primary_color',
            ]),
            LayoutSchemaBuilder::text('unbound', 'Leave me alone', 40, 520, 400, 40, [
                'text' => 'Leave me alone',
                'fontSize' => 20,
            ]),
        ],
        '#FFFFFF',
    );
}

test('brand kit schema applier applies business name binding', function () {
    [, $workspace] = brandKitApplierUser();

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'Acme Screens',
        'tagline' => 'Signs that work',
        'primary_color' => '#0D9488',
    ]);

    $schema = brandBoundSchema();
    $applied = BrandKitSchemaApplier::apply($schema, $kit, $workspace);

    $byId = collect($applied['elements'])->keyBy('id');

    expect($byId['title']['props']['text'])->toBe('Acme Screens')
        ->and($byId['tagline']['props']['text'])->toBe('Signs that work')
        ->and($byId['panel']['props']['fill'])->toBe('#0D9488')
        ->and($byId['unbound']['props']['text'])->toBe('Leave me alone');
});

test('brand kit schema applier keeps placeholders when kit values are missing', function () {
    [, $workspace] = brandKitApplierUser();

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => null,
        'tagline' => null,
        'logo_media_asset_id' => null,
    ]);

    $schema = brandBoundSchema();
    $applied = BrandKitSchemaApplier::apply($schema, $kit, $workspace);
    $byId = collect($applied['elements'])->keyBy('id');

    expect($byId['title']['props']['text'])->toBe('Placeholder Biz')
        ->and($byId['tagline']['props']['text'])->toBe('Placeholder tagline')
        ->and($byId['logo']['props']['mediaAssetId'] ?? null)->toBeNull()
        ->and($byId['logo']['props']['placeholder'] ?? null)->toBeTrue();
});

test('brand kit schema applier rejects logo from another workspace', function () {
    [, $workspace] = brandKitApplierUser();
    $other = Workspace::factory()->create();

    $foreignLogo = MediaAsset::factory()->create([
        'workspace_id' => $other->id,
        'type' => MediaType::Logo,
        'name' => 'Foreign logo',
    ]);

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'Local Biz',
        'logo_media_asset_id' => $foreignLogo->id,
    ]);

    $schema = brandBoundSchema();
    $applied = BrandKitSchemaApplier::apply($schema, $kit, $workspace);
    $logo = collect($applied['elements'])->firstWhere('id', 'logo');

    expect($logo['props']['mediaAssetId'] ?? null)->toBeNull()
        ->and($logo['props']['placeholder'] ?? null)->toBeTrue();
});

test('brand kit schema applier accepts owned workspace logo', function () {
    [$user, $workspace] = brandKitApplierUser();

    $logo = MediaAsset::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => MediaType::Logo,
        'name' => 'Owned logo',
        'created_by' => $user->id,
    ]);

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'logo_media_asset_id' => $logo->id,
    ]);

    $schema = brandBoundSchema();
    $applied = BrandKitSchemaApplier::apply($schema, $kit, $workspace);
    $logoEl = collect($applied['elements'])->firstWhere('id', 'logo');

    expect($logoEl['props']['mediaAssetId'])->toBe($logo->id)
        ->and($logoEl['props']['placeholder'] ?? null)->toBeNull();
});

test('use template personalises copy without mutating master template', function () {
    $staff = User::factory()->create();
    $staff->assignPlatformRole(PlatformRole::SuperAdmin);
    attachWorkspace($staff);
    $this->seed(PlatformTemplatesSeeder::class);

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'Corner Café Co',
        'primary_color' => '#78350F',
    ]);

    $template = Template::query()->platform()->where('slug', 'cafe-promotion')->firstOrFail();
    $masterBefore = $template->publishedVersion?->schema;
    expect($masterBefore)->toBeArray();

    $design = app(CreateScreenDesignFromTemplate::class)->handle($owner, $workspace, $template);
    $copied = $design->latestVersion()?->schema;
    expect($copied)->toBeArray();

    $footer = collect($copied['elements'])->firstWhere('id', 'cafe-footer');
    expect($footer['props']['text'] ?? null)->toBe('Corner Café Co')
        ->and($design->name)->toBe($template->name)
        ->and($design->name)->not->toBe('Corner Café Co');

    $template->refresh()->load('publishedVersion');
    $masterAfter = $template->publishedVersion?->schema;

    expect($masterAfter['elements'])->toEqual($masterBefore['elements']);

    $masterFooter = collect($masterAfter['elements'])->firstWhere('id', 'cafe-footer');
    expect($masterFooter['props']['text'] ?? null)->toBe('The Corner Café — Open 7am–6pm')
        ->and($kit->fresh()->name)->toBe('Corner Café Co');
});

test('use template always uses template name even when brand kit name is set', function () {
    $staff = User::factory()->create();
    $staff->assignPlatformRole(PlatformRole::SuperAdmin);
    attachWorkspace($staff);

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);

    BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'North & Bean Café',
    ]);

    $schema = brandBoundSchema();
    $template = Template::query()->create([
        'workspace_id' => null,
        'name' => 'Bound Demo Template',
        'slug' => 'bound-demo-template-'.uniqid(),
        'description' => 'Test',
        'category' => TemplateCategory::Promo,
        'orientation' => TemplateOrientation::Landscape,
        'canvas_width' => 1920,
        'canvas_height' => 1080,
        'theme' => TemplateTheme::Minimal,
        'status' => TemplateStatus::Published,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $version = TemplateVersion::query()->create([
        'template_id' => $template->id,
        'version_number' => 1,
        'schema' => $schema,
        'created_by' => $staff->id,
        'published_at' => now(),
    ]);
    $template->forceFill(['published_version_id' => $version->id])->save();

    $design = app(CreateScreenDesignFromTemplate::class)->handle($owner, $workspace, $template->fresh());

    expect($design->name)->toBe('Bound Demo Template')
        ->and($design->name)->not->toBe('North & Bean Café');
});

test('use template falls back to template name when brand kit name is empty', function () {
    $staff = User::factory()->create();
    $staff->assignPlatformRole(PlatformRole::SuperAdmin);
    attachWorkspace($staff);

    $owner = User::factory()->create();
    $workspace = attachWorkspace($owner, null, WorkspaceRole::Owner);

    BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => null,
    ]);

    $schema = brandBoundSchema();
    $template = Template::query()->create([
        'workspace_id' => null,
        'name' => 'Bound Demo Template',
        'slug' => 'bound-demo-template-'.uniqid(),
        'description' => 'Test',
        'category' => TemplateCategory::Promo,
        'orientation' => TemplateOrientation::Landscape,
        'canvas_width' => 1920,
        'canvas_height' => 1080,
        'theme' => TemplateTheme::Minimal,
        'status' => TemplateStatus::Published,
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $version = TemplateVersion::query()->create([
        'template_id' => $template->id,
        'version_number' => 1,
        'schema' => $schema,
        'created_by' => $staff->id,
        'published_at' => now(),
    ]);
    $template->forceFill(['published_version_id' => $version->id])->save();

    $design = app(CreateScreenDesignFromTemplate::class)->handle($owner, $workspace, $template->fresh());

    expect($design->name)->toBe('Bound Demo Template');

    $title = collect($design->latestVersion()?->schema['elements'] ?? [])->firstWhere('id', 'title');
    expect($title['props']['text'] ?? null)->toBe('Placeholder Biz');
});

test('applier returns an immutable copy of the schema', function () {
    [, $workspace] = brandKitApplierUser();

    $kit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'Mutate Check',
    ]);

    $schema = brandBoundSchema();
    $originalTitle = $schema['elements'][0]['props']['text'];

    $applied = BrandKitSchemaApplier::apply($schema, $kit, $workspace);

    expect($schema['elements'][0]['props']['text'])->toBe($originalTitle)
        ->and($applied['elements'][0]['props']['text'])->toBe('Mutate Check')
        ->and($applied)->not->toBe($schema);
});

test('null brand kit leaves schema bindings untouched', function () {
    [, $workspace] = brandKitApplierUser();

    $schema = brandBoundSchema();
    $applied = BrandKitSchemaApplier::apply($schema, null, $workspace);

    expect($applied['elements'][0]['props']['text'])->toBe('Placeholder Biz')
        ->and($applied['schemaVersion'] ?? null)->toBe(LayoutSchema::SCHEMA_VERSION);
});
