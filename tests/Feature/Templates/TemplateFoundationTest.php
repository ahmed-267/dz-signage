<?php

use App\Actions\Templates\CreateTemplate;
use App\Enums\PlatformRole;
use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceRole;
use App\Models\Template;
use App\Models\TemplateFavourite;
use App\Models\User;
use App\Support\Rendering\LayoutSchema;

function templateUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

function platformStaff(PlatformRole $role = PlatformRole::SuperAdmin): User
{
    $user = User::factory()->create();
    $user->assignPlatformRole($role);
    attachWorkspace($user);

    return $user->fresh() ?? $user;
}

function publishedPlatformTemplate(User $creator, array $attributes = []): Template
{
    $template = Template::factory()
        ->platform()
        ->createdBy($creator)
        ->withDraftVersion($creator)
        ->create(array_merge([
            'name' => 'DZ Prayer Times',
            'status' => TemplateStatus::Draft,
        ], $attributes));

    $version = $template->versions()->first();
    $version->forceFill(['published_at' => now()])->save();
    $template->forceFill([
        'status' => TemplateStatus::Published,
        'published_version_id' => $version->id,
    ])->save();

    return $template->fresh(['publishedVersion', 'versions']) ?? $template;
}

test('customers can browse published platform templates only', function () {
    $staff = platformStaff();
    [$owner] = templateUser();

    $published = publishedPlatformTemplate($staff, ['name' => 'Published Board']);
    $draft = Template::factory()
        ->platform()
        ->createdBy($staff)
        ->withDraftVersion($staff)
        ->create(['name' => 'Draft Board', 'status' => TemplateStatus::Draft]);

    $this->actingAs($owner)
        ->get(route('app.templates'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/templates/index')
            ->has('templates.data', 1)
            ->where('templates.data.0.id', $published->id)
            ->where('use_template_available', true)
            ->missing('counts.mine'));

    expect(Template::query()->whereKey($draft->id)->exists())->toBeTrue();
});

test('workspace roles cannot create or mutate platform templates via customer routes', function () {
    $staff = platformStaff();
    [$owner] = templateUser();
    $admin = User::factory()->create();
    $designer = User::factory()->create();
    $workspace = $owner->fresh()->currentWorkspace;
    attachWorkspace($admin, $workspace, WorkspaceRole::Admin);
    attachWorkspace($designer, $workspace, WorkspaceRole::Designer);

    $template = publishedPlatformTemplate($staff);

    foreach ([$owner, $admin, $designer] as $user) {
        foreach ([
            fn () => $this->actingAs($user)->post('/app/templates', [
                'name' => 'Forbidden',
                'orientation' => TemplateOrientation::Landscape->value,
                'theme' => TemplateTheme::Minimal->value,
            ]),
            fn () => $this->actingAs($user)->patch('/app/templates/'.$template->id, ['name' => 'Hacked']),
            fn () => $this->actingAs($user)->post('/app/templates/'.$template->id.'/publish'),
            fn () => $this->actingAs($user)->delete('/app/templates/'.$template->id),
            fn () => $this->actingAs($user)->get('/app/templates/'.$template->id.'/builder'),
        ] as $request) {
            $response = $request();
            expect($response->status())->toBeIn([403, 404, 405]);
        }
    }
});

test('workspace admin role never grants platform admin access', function () {
    [$owner] = templateUser(WorkspaceRole::Admin);

    expect($owner->isPlatformStaff())->toBeFalse()
        ->and($owner->canManagePlatformTemplates())->toBeFalse();

    $this->actingAs($owner)
        ->get(route('admin.templates'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('admin.templates.store'), [
            'name' => 'Nope',
            'orientation' => TemplateOrientation::Landscape->value,
            'theme' => TemplateTheme::Blank->value,
        ])
        ->assertForbidden();
});

test('ordinary user cannot access admin templates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.templates'))
        ->assertForbidden();
});

test('super admin can manage global templates', function () {
    $admin = platformStaff(PlatformRole::SuperAdmin);

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'name' => 'Platform Promo',
            'orientation' => TemplateOrientation::Landscape->value,
            'theme' => TemplateTheme::Corporate->value,
            'category' => TemplateCategory::Promo->value,
        ])
        ->assertRedirect();

    $platform = Template::query()->platform()->where('name', 'Platform Promo')->first();
    expect($platform)->not->toBeNull()
        ->and($platform->workspace_id)->toBeNull()
        ->and($platform->status)->toBe(TemplateStatus::Draft);

    $this->actingAs($admin)
        ->get(route('admin.templates.builder', $platform))
        ->assertOk();

    $schema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Corporate);
    $schema['elements'] = [[
        'id' => 'el-1',
        'type' => 'text',
        'name' => 'Heading',
        'x' => 100,
        'y' => 80,
        'width' => 400,
        'height' => 60,
        'zIndex' => 1,
    ]];

    $this->actingAs($admin)
        ->patch(route('admin.templates.update', $platform), [
            'name' => 'Platform Promo',
            'schema' => $schema,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.templates.publish', $platform))
        ->assertRedirect();

    $platform->refresh();
    expect($platform->status)->toBe(TemplateStatus::Published)
        ->and($platform->published_version_id)->not->toBeNull();
});

test('platform admin can manage global templates', function () {
    $platformAdmin = platformStaff(PlatformRole::PlatformAdmin);

    expect($platformAdmin->isSuperAdmin())->toBeFalse()
        ->and($platformAdmin->canManagePlatformTemplates())->toBeTrue();

    $this->actingAs($platformAdmin)
        ->post(route('admin.templates.store'), [
            'name' => 'PA Board',
            'orientation' => TemplateOrientation::Portrait->value,
            'theme' => TemplateTheme::Islamic->value,
        ])
        ->assertRedirect();

    $template = Template::query()->where('name', 'PA Board')->first();
    expect($template)->not->toBeNull()
        ->and($template->canvas_width)->toBe(1080)
        ->and($template->canvas_height)->toBe(1920);

    $this->actingAs($platformAdmin)
        ->post(route('admin.templates.archive', $template))
        ->assertRedirect(route('admin.templates'));

    expect($template->fresh()->status)->toBe(TemplateStatus::Archived);
});

test('workspace owner cannot create or edit global templates', function () {
    $staff = platformStaff();
    [$owner] = templateUser();
    $template = publishedPlatformTemplate($staff);

    $this->actingAs($owner)
        ->post(route('admin.templates.store'), [
            'name' => 'Stolen',
            'orientation' => TemplateOrientation::Landscape->value,
            'theme' => TemplateTheme::Blank->value,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.templates.builder', $template))
        ->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('admin.templates.update', $template), ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('saving a draft after publish does not overwrite the published version', function () {
    $admin = platformStaff();
    $template = publishedPlatformTemplate($admin);
    $published = $template->publishedVersion;
    $originalSchema = $published->schema;

    $newSchema = LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Bold);
    $newSchema['elements'] = [[
        'id' => 'el-1',
        'type' => 'text',
        'name' => 'Draft',
        'x' => 10,
        'y' => 20,
        'width' => 100,
        'height' => 40,
        'zIndex' => 1,
        'props' => ['text' => 'Draft edit'],
    ]];

    $this->actingAs($admin)
        ->patch(route('admin.templates.update', $template), [
            'schema' => $newSchema,
        ])
        ->assertRedirect();

    $published->refresh();
    expect($published->schema)->toEqual($originalSchema)
        ->and($published->published_at)->not->toBeNull();

    $latest = $template->fresh()->latestVersion();
    expect($latest->version_number)->toBe(2)
        ->and($latest->published_at)->toBeNull();
});

test('member can favourite published platform templates and viewers can browse', function () {
    $staff = platformStaff();
    [$owner, $workspace] = templateUser();
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $template = publishedPlatformTemplate($staff);

    $this->actingAs($viewer)
        ->get(route('app.templates'))
        ->assertOk();

    $this->actingAs($owner)
        ->post(route('app.templates.favourite', $template))
        ->assertRedirect();

    expect(TemplateFavourite::query()->where('user_id', $owner->id)->where('template_id', $template->id)->exists())
        ->toBeTrue();

    $this->actingAs($owner)
        ->get(route('app.templates.preview', $template))
        ->assertOk()
        ->assertJsonPath('id', $template->id);
});

test('create template action rejects workspace ownership', function () {
    [$owner, $workspace] = templateUser();

    expect(fn () => app(CreateTemplate::class)->handle($owner, $workspace, [
        'name' => 'Bad',
        'orientation' => TemplateOrientation::Landscape,
        'theme' => TemplateTheme::Blank,
    ]))->toThrow(InvalidArgumentException::class);
});
