<?php

use App\Actions\ScreenDesigns\CreateScreenDesignFromTemplate;
use App\Enums\MediaType;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\BrandKit;
use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Models\User;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function designUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function publishedTemplateForUse(): Template
{
    $staff = User::factory()->admin()->create();
    $template = Template::factory()
        ->platform()
        ->createdBy($staff)
        ->withDraftVersion($staff)
        ->create(['name' => 'Lobby Starter', 'status' => TemplateStatus::Draft]);

    $version = $template->versions()->first();
    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Minimal);
    $schema['elements'] = [[
        'id' => 'heading-1',
        'type' => 'text',
        'name' => 'Heading',
        'x' => 700,
        'y' => 400,
        'width' => 520,
        'height' => 80,
        'zIndex' => 1,
        'props' => ['text' => 'Welcome', 'fontSize' => 48],
    ]];
    $version->forceFill([
        'schema' => $schema,
        'published_at' => now(),
    ])->save();
    $template->forceFill([
        'status' => TemplateStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $template->fresh(['publishedVersion']) ?? $template;
}

test('owner can create blank landscape and portrait screen designs', function () {
    [$user] = designUser();

    $this->actingAs($user)
        ->post(route('app.screen_designs.store_blank'), [
            'name' => 'Blank Lobby',
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $design = ScreenDesign::query()->where('name', 'Blank Lobby')->first();
    expect($design)->not->toBeNull()
        ->and($design->canvas_width)->toBe(1920)
        ->and($design->canvas_height)->toBe(1080)
        ->and($design->versions()->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('app.screen_designs.store_blank'), [
            'orientation' => 'portrait',
        ])
        ->assertRedirect();

    $portrait = ScreenDesign::query()->where('orientation', 'portrait')->first();
    expect($portrait->name)->toBe('New Portrait Design')
        ->and($portrait->canvas_width)->toBe(1080)
        ->and($portrait->canvas_height)->toBe(1920);

    $this->actingAs($user)
        ->post(route('app.screen_designs.store_blank'), [
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    expect(ScreenDesign::query()->where('name', 'New Landscape Design')->exists())->toBeTrue()
        ->and(ScreenDesign::query()->where('name', 'Untitled Design')->exists())->toBeFalse();
});

test('use template copies schema and stays independent from later template edits', function () {
    [$user] = designUser();
    $template = publishedTemplateForUse();
    $originalSchema = $template->publishedVersion->schema;

    $this->actingAs($user)
        ->post(route('app.templates.use', $template))
        ->assertRedirect();

    $design = ScreenDesign::query()->first();
    expect($design)->not->toBeNull()
        ->and($design->name)->toBe('Lobby Starter')
        ->and($design->source_template_id)->toBe($template->id)
        ->and($design->source_template_version_id)->toBe($template->published_version_id)
        ->and($design->latestVersion()->schema['elements'][0]['props']['text'])->toBe('Welcome');

    // Mutate published template schema (new draft version)
    $staff = User::factory()->admin()->create();
    $newSchema = $originalSchema;
    $newSchema['elements'][0]['props']['text'] = 'Changed Master';
    $this->actingAs($staff)
        ->patch(route('admin.templates.update', $template), [
            'schema' => $newSchema,
        ])
        ->assertRedirect();

    expect($design->fresh()->latestVersion()->schema['elements'][0]['props']['text'])->toBe('Welcome');
});

test('use template names the design after the template not the brand kit', function () {
    [$user, $workspace] = designUser();
    $template = publishedTemplateForUse();

    BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
        'name' => 'North & Bean Café',
    ]);

    $design = app(CreateScreenDesignFromTemplate::class)
        ->handle($user, $workspace, $template);

    expect($design->name)->toBe('Lobby Starter')
        ->and($design->name)->not->toBe('North & Bean Café');
});

test('index prunes abandoned empty drafts', function () {
    [$user, $workspace] = designUser();

    $abandoned = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Untitled Design']);

    $keptSchema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $keptSchema['elements'] = [[
        'id' => 't1',
        'type' => 'text',
        'name' => 'Title',
        'x' => 10,
        'y' => 10,
        'width' => 200,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'Keep me'],
    ]];

    $kept = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $keptSchema)
        ->create(['name' => 'Real Design']);

    $this->actingAs($user)
        ->get(route('app.screen_designs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('designs.data', 1)
            ->where('designs.data.0.id', $kept->id)
        );

    expect(ScreenDesign::query()->find($abandoned->id))->toBeNull()
        ->and(ScreenDesign::query()->find($kept->id))->not->toBeNull();
});

test('duplicate rejects non-meaningful designs', function () {
    [$user, $workspace] = designUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create(['name' => 'Empty Shell']);

    $this->actingAs($user)
        ->post(route('app.screen_designs.duplicate', $design))
        ->assertSessionHasErrors('design');

    expect(ScreenDesign::query()->where('name', 'Empty Shell Copy')->exists())->toBeFalse();
});

test('workspace isolation blocks foreign screen designs', function () {
    [$userA, $workspaceA] = designUser();
    [$userB] = designUser();

    $design = ScreenDesign::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($userA)
        ->withDraftVersion($userA)
        ->create(['name' => 'Private A']);

    $this->actingAs($userB)
        ->get(route('app.screen_designs.edit', $design))
        ->assertNotFound();

    $this->actingAs($userB)
        ->patch(route('app.screen_designs.update', $design), ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($userB)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertNotFound();
});

test('designer can manage designs but viewer cannot mutate', function () {
    [$owner, $workspace] = designUser();
    $designer = User::factory()->create();
    $viewer = User::factory()->create();
    attachWorkspace($designer, $workspace, WorkspaceRole::Designer);
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $this->actingAs($designer)
        ->post(route('app.screen_designs.store_blank'), [
            'name' => 'Designer Board',
            'orientation' => 'landscape',
        ])
        ->assertRedirect();

    $design = ScreenDesign::query()->where('name', 'Designer Board')->firstOrFail();

    $this->actingAs($viewer)
        ->get(route('app.screen_designs'))
        ->assertOk();

    $this->actingAs($viewer)
        ->get(route('app.screen_designs.edit', $design))
        ->assertOk();

    $this->actingAs($viewer)
        ->patch(route('app.screen_designs.update', $design), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertForbidden();
});

test('content manager can create and location manager cannot', function () {
    [$owner, $workspace] = designUser();
    $cm = User::factory()->create();
    $lm = User::factory()->create();
    attachWorkspace($cm, $workspace, WorkspaceRole::ContentManager);
    attachWorkspace($lm, $workspace, WorkspaceRole::LocationManager);

    $this->actingAs($cm)
        ->post(route('app.screen_designs.store_blank'), [
            'orientation' => 'landscape',
            'name' => 'CM Design',
        ])
        ->assertRedirect();

    $this->actingAs($lm)
        ->post(route('app.screen_designs.store_blank'), [
            'orientation' => 'landscape',
            'name' => 'LM Design',
        ])
        ->assertForbidden();
});

test('publish preserves published version when later draft edits occur', function () {
    [$user, $workspace] = designUser();
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user)
        ->create();

    $this->actingAs($user)
        ->post(route('app.screen_designs.publish', $design))
        ->assertRedirect();

    $design->refresh();
    $published = $design->publishedVersion;
    $original = $published->schema;

    $newSchema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Bold);
    $newSchema['elements'] = [[
        'id' => 'el-2',
        'type' => 'text',
        'name' => 'Draft',
        'x' => 10,
        'y' => 10,
        'width' => 100,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'After publish'],
    ]];

    $this->actingAs($user)
        ->patch(route('app.screen_designs.update', $design), [
            'schema' => $newSchema,
        ])
        ->assertRedirect();

    expect($published->fresh()->schema)->toEqual($original)
        ->and($design->fresh()->latestVersion()->version_number)->toBe(2)
        ->and($design->fresh()->latestVersion()->published_at)->toBeNull();
});

test('foreign workspace media references are rejected', function () {
    [$userA, $workspaceA] = designUser();
    [$userB, $workspaceB] = designUser();

    $foreignMedia = MediaAsset::factory()->text('Secret')->forWorkspace($workspaceB)->createdBy($userB)->create();

    $design = ScreenDesign::factory()
        ->forWorkspace($workspaceA)
        ->createdBy($userA)
        ->withDraftVersion($userA)
        ->create();

    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 'img-1',
        'type' => 'image',
        'name' => 'Image',
        'x' => 0,
        'y' => 0,
        'width' => 400,
        'height' => 300,
        'zIndex' => 1,
        'props' => ['mediaAssetId' => $foreignMedia->id],
    ]];

    $this->actingAs($userA)
        ->patch(route('app.screen_designs.update', $design), [
            'schema' => $schema,
        ])
        ->assertSessionHasErrors('schema');
});

test('media deletion is blocked when referenced by a screen design', function () {
    Storage::fake(config('media.disk', 'public'));
    [$user, $workspace] = designUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Used Image',
        'file' => UploadedFile::fake()->image('used.jpg'),
    ]);

    $media = MediaAsset::query()->firstOrFail();

    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 'img-1',
        'type' => 'image',
        'name' => 'Hero',
        'x' => 0,
        'y' => 0,
        'width' => 800,
        'height' => 600,
        'zIndex' => 1,
        'props' => ['mediaAssetId' => $media->id],
    ]];

    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create();

    expect($media->fresh()->hasDependencies())->toBeTrue();

    $this->actingAs($user)
        ->delete(route('app.media.destroy', $media))
        ->assertSessionHasErrors('media');

    expect(MediaAsset::query()->find($media->id))->not->toBeNull();
});

test('duplicate rename and delete screen design', function () {
    [$user, $workspace] = designUser();
    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $schema['elements'] = [[
        'id' => 't1',
        'type' => 'text',
        'name' => 'Title',
        'x' => 10,
        'y' => 10,
        'width' => 200,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'Promo'],
    ]];
    $design = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $schema)
        ->create(['name' => 'Summer Promo']);

    $this->actingAs($user)
        ->post(route('app.screen_designs.duplicate', $design))
        ->assertRedirect();

    expect(ScreenDesign::query()->where('name', 'Summer Promo Copy')->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('app.screen_designs.rename', $design), ['name' => 'Autumn Promo'])
        ->assertRedirect();

    expect($design->fresh()->name)->toBe('Autumn Promo');

    $this->actingAs($user)
        ->delete(route('app.screen_designs.destroy', $design))
        ->assertRedirect(route('app.screen_designs'));

    expect(ScreenDesign::query()->find($design->id))->toBeNull();
});

test('viewer cannot use template', function () {
    [$owner, $workspace] = designUser();
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);
    $template = publishedTemplateForUse();

    $this->actingAs($viewer)
        ->post(route('app.templates.use', $template))
        ->assertForbidden();
});

test('screen designs index normalises published status filter to all', function () {
    [$user, $workspace] = designUser();

    $meaningful = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank);
    $meaningful['elements'] = [[
        'id' => 't1',
        'type' => 'text',
        'name' => 'Title',
        'x' => 10,
        'y' => 10,
        'width' => 200,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'Hello'],
    ]];

    $draft = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $meaningful)
        ->create(['name' => 'Draft Only']);

    $published = ScreenDesign::factory()
        ->forWorkspace($workspace)
        ->createdBy($user)
        ->withDraftVersion($user, $meaningful)
        ->create(['name' => 'Published Design', 'status' => ScreenDesignStatus::Published]);

    $this->actingAs($user)
        ->get(route('app.screen_designs', ['status' => 'published']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/screen-designs/index')
            ->where('filters.status', 'all')
            ->has('designs.data', 2)
        );

    $this->actingAs($user)
        ->get(route('app.screen_designs', ['status' => 'draft']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'draft')
            ->has('designs.data', 1)
            ->where('designs.data.0.id', $draft->id)
        );

    expect($published->fresh()->status)->toBe(ScreenDesignStatus::Published);
});
