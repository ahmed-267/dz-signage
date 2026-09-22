<?php

use App\Enums\MediaType;
use App\Enums\WorkspaceRole;
use App\Models\BrandKit;
use App\Models\MediaAsset;
use App\Models\User;

function brandKitUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

test('get creates brand kit defaults for the workspace', function () {
    [$user, $workspace] = brandKitUser();

    expect(BrandKit::query()->where('workspace_id', $workspace->id)->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('app.brand_kit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/brand-kit/index')
            ->where('brandKit.name', $workspace->name)
            ->where('brandKit.primary_color', BrandKit::DEFAULT_PRIMARY)
            ->where('permissions.can_manage', true));

    expect(BrandKit::query()->where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('owner can update brand kit', function () {
    [$user, $workspace] = brandKitUser();

    $brandKit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($user)
        ->put(route('app.brand_kit.update', $brandKit), [
            'name' => 'Acme Signage',
            'tagline' => 'Screens that sell',
            'primary_color' => '#0D9488',
            'secondary_color' => '#134E4A',
            'accent_color' => '#F59E0B',
            'background_color' => '#FFF',
            'text_color' => '#111',
            'heading_font' => 'Outfit',
            'body_font' => 'Georgia',
            'logo_media_asset_id' => null,
            'secondary_logo_media_asset_id' => null,
        ])
        ->assertRedirect();

    $brandKit->refresh();

    expect($brandKit->name)->toBe('Acme Signage')
        ->and($brandKit->tagline)->toBe('Screens that sell')
        ->and($brandKit->primary_color)->toBe('#0D9488')
        ->and($brandKit->background_color)->toBe('#FFFFFF')
        ->and($brandKit->text_color)->toBe('#111111')
        ->and($brandKit->body_font)->toBe('Georgia');
});

test('viewer cannot update brand kit', function () {
    [$owner, $workspace] = brandKitUser();
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    $brandKit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('app.brand_kit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('permissions.can_manage', false));

    $this->actingAs($viewer)
        ->put(route('app.brand_kit.update', $brandKit), [
            'name' => 'Hacked',
            'tagline' => null,
            'primary_color' => '#FF0000',
            'secondary_color' => BrandKit::DEFAULT_SECONDARY,
            'accent_color' => BrandKit::DEFAULT_ACCENT,
            'background_color' => BrandKit::DEFAULT_BACKGROUND,
            'text_color' => BrandKit::DEFAULT_TEXT,
            'heading_font' => 'Outfit',
            'body_font' => 'system-ui',
        ])
        ->assertForbidden();

    expect($brandKit->fresh()->name)->toBe($workspace->name);
});

test('logo media must belong to the same workspace', function () {
    [$userA, $workspaceA] = brandKitUser();
    [$userB, $workspaceB] = brandKitUser();

    $foreignLogo = MediaAsset::factory()->forWorkspace($workspaceB)->createdBy($userB)->create([
        'type' => MediaType::Logo,
        'name' => 'Foreign Logo',
        'text_content' => null,
        'storage_disk' => 'public',
        'storage_path' => 'media/foreign-logo.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);

    $brandKit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspaceA),
        'workspace_id' => $workspaceA->id,
    ]);

    $this->actingAs($userA)
        ->put(route('app.brand_kit.update', $brandKit), [
            'name' => $workspaceA->name,
            'tagline' => null,
            'primary_color' => BrandKit::DEFAULT_PRIMARY,
            'secondary_color' => BrandKit::DEFAULT_SECONDARY,
            'accent_color' => BrandKit::DEFAULT_ACCENT,
            'background_color' => BrandKit::DEFAULT_BACKGROUND,
            'text_color' => BrandKit::DEFAULT_TEXT,
            'heading_font' => 'Outfit',
            'body_font' => 'system-ui',
            'logo_media_asset_id' => $foreignLogo->id,
        ])
        ->assertSessionHasErrors('logo_media_asset_id');

    expect($brandKit->fresh()->logo_media_asset_id)->toBeNull();
});

test('designer can update brand kit with workspace logo media', function () {
    [$user, $workspace] = brandKitUser(WorkspaceRole::Designer);

    $logo = MediaAsset::factory()->forWorkspace($workspace)->createdBy($user)->create([
        'type' => MediaType::Image,
        'name' => 'Brand Mark',
        'text_content' => null,
        'storage_disk' => 'public',
        'storage_path' => 'media/brand-mark.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
    ]);

    $brandKit = BrandKit::query()->create([
        ...BrandKit::defaultsFor($workspace),
        'workspace_id' => $workspace->id,
    ]);

    $this->actingAs($user)
        ->put(route('app.brand_kit.update', $brandKit), [
            'name' => 'Designer Brand',
            'tagline' => null,
            'primary_color' => '#ABC',
            'secondary_color' => BrandKit::DEFAULT_SECONDARY,
            'accent_color' => BrandKit::DEFAULT_ACCENT,
            'background_color' => BrandKit::DEFAULT_BACKGROUND,
            'text_color' => BrandKit::DEFAULT_TEXT,
            'heading_font' => 'Georgia',
            'body_font' => 'system-ui',
            'logo_media_asset_id' => $logo->id,
        ])
        ->assertRedirect();

    $brandKit->refresh();

    expect($brandKit->logo_media_asset_id)->toBe($logo->id)
        ->and($brandKit->primary_color)->toBe('#AABBCC')
        ->and($brandKit->heading_font)->toBe('Georgia');
});
