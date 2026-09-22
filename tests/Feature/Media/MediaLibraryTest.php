<?php

use App\Enums\MediaType;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('media.disk', 'public'));
});

function mediaUser(WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $user = User::factory()->create();
    $workspace = attachWorkspace($user, null, $role);

    return [$user, $workspace];
}

test('owner can list workspace media', function () {
    [$user, $workspace] = mediaUser();

    MediaAsset::factory()->text()->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Welcome Notice',
    ]);

    $this->actingAs($user)
        ->get(route('app.media'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/media/index')
            ->has('media.data', 1)
            ->where('media.data.0.name', 'Welcome Notice'));
});

test('workspace isolation prevents viewing another workspace media', function () {
    [$userA, $workspaceA] = mediaUser();
    [$userB, $workspaceB] = mediaUser();

    $asset = MediaAsset::factory()->text()->forWorkspace($workspaceA)->createdBy($userA)->create([
        'name' => 'Private A',
    ]);

    $this->actingAs($userB)
        ->get(route('app.media'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('media.data', 0));

    $this->actingAs($userB)
        ->patch(route('app.media.update', $asset), ['name' => 'Hacked'])
        ->assertNotFound();

    $this->actingAs($userB)
        ->delete(route('app.media.destroy', $asset))
        ->assertNotFound();
});

test('viewer can view but cannot create or delete media', function () {
    [$owner, $workspace] = mediaUser();
    $viewer = User::factory()->create();
    attachWorkspace($viewer, $workspace, WorkspaceRole::Viewer);

    MediaAsset::factory()->text()->forWorkspace($workspace)->createdBy($owner)->create();

    $this->actingAs($viewer)
        ->get(route('app.media'))
        ->assertOk();

    $this->actingAs($viewer)
        ->post(route('app.media.store'), [
            'type' => MediaType::Text->value,
            'name' => 'Nope',
            'text_content' => 'Blocked',
        ])
        ->assertForbidden();

    $asset = MediaAsset::factory()->text()->forWorkspace($workspace)->createdBy($owner)->create();

    $this->actingAs($viewer)
        ->delete(route('app.media.destroy', $asset))
        ->assertForbidden();
});

test('designer can upload an image', function () {
    [$user] = mediaUser(WorkspaceRole::Designer);

    $file = UploadedFile::fake()->image('promo.jpg', 800, 600);

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Image->value,
            'name' => 'Retail Promo',
            'file' => $file,
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->first();
    expect($asset)->not->toBeNull()
        ->and($asset->type)->toBe(MediaType::Image)
        ->and($asset->name)->toBe('Retail Promo')
        ->and($asset->width)->toBe(800)
        ->and($asset->height)->toBe(600)
        ->and($asset->hasStoredFile())->toBeTrue();

    Storage::disk($asset->storage_disk)->assertExists($asset->storage_path);
});

test('invalid file extension is rejected', function () {
    [$user] = mediaUser();

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Image->value,
            'name' => 'Bad',
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');
});

test('oversized image is rejected', function () {
    [$user] = mediaUser();

    config(['media.max_sizes.image' => 10]); // 10 KB

    $file = UploadedFile::fake()->image('big.jpg')->size(50);

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Image->value,
            'name' => 'Too Big',
            'file' => $file,
        ])
        ->assertSessionHasErrors('file');
});

test('logo and document uploads succeed', function () {
    [$user] = mediaUser();

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Logo->value,
            'name' => 'Brand Mark',
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Document->value,
            'name' => 'Menu PDF',
            'file' => UploadedFile::fake()->create('menu.pdf', 200, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(MediaAsset::query()->where('type', MediaType::Logo)->count())->toBe(1)
        ->and(MediaAsset::query()->where('type', MediaType::Document)->count())->toBe(1);
});

test('video upload stores file without requiring duration', function () {
    [$user] = mediaUser();

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Video->value,
            'name' => 'Lobby Loop',
            'file' => UploadedFile::fake()->create('loop.mp4', 1024, 'video/mp4'),
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->where('type', MediaType::Video)->first();
    expect($asset)->not->toBeNull()
        ->and($asset->duration_seconds)->toBeNull()
        ->and($asset->hasStoredFile())->toBeTrue();
});

test('text media can be created and edited', function () {
    [$user] = mediaUser(WorkspaceRole::ContentManager);

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Text->value,
            'name' => 'Friday Prayer Notice',
            'text_content' => "Jumu'ah begins at 1:30 PM.",
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('app.media.update', $asset), [
            'name' => 'Updated Notice',
            'text_content' => 'Updated body',
        ])
        ->assertRedirect();

    expect($asset->fresh()->name)->toBe('Updated Notice')
        ->and($asset->fresh()->text_content)->toBe('Updated body');
});

test('link media validates url and can be edited', function () {
    [$user] = mediaUser();

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Link->value,
            'name' => 'Website',
            'url' => 'not-a-url',
        ])
        ->assertSessionHasErrors('url');

    $this->actingAs($user)
        ->post(route('app.media.store'), [
            'type' => MediaType::Link->value,
            'name' => 'Website',
            'url' => 'https://example.com/live',
        ])
        ->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    $this->actingAs($user)
        ->patch(route('app.media.update', $asset), [
            'url' => 'https://example.com/updated',
        ])
        ->assertRedirect();

    expect($asset->fresh()->url)->toBe('https://example.com/updated');
});

test('rename keeps storage path stable', function () {
    [$user] = mediaUser();

    $file = UploadedFile::fake()->image('original.jpg');
    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Original',
        'file' => $file,
    ]);

    $asset = MediaAsset::query()->firstOrFail();
    $path = $asset->storage_path;

    $this->actingAs($user)
        ->patch(route('app.media.update', $asset), ['name' => 'Renamed'])
        ->assertRedirect();

    expect($asset->fresh()->name)->toBe('Renamed')
        ->and($asset->fresh()->storage_path)->toBe($path);
});

test('location manager cannot rename media', function () {
    [$owner, $workspace] = mediaUser();
    $manager = User::factory()->create();
    attachWorkspace($manager, $workspace, WorkspaceRole::LocationManager);

    $asset = MediaAsset::factory()->text()->forWorkspace($workspace)->createdBy($owner)->create();

    $this->actingAs($manager)
        ->patch(route('app.media.update', $asset), ['name' => 'Nope'])
        ->assertForbidden();
});

test('replace keeps media id and removes old file', function () {
    [$user] = mediaUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Summer Promo',
        'file' => UploadedFile::fake()->image('summer.jpg', 100, 100),
    ]);

    $asset = MediaAsset::query()->firstOrFail();
    $oldPath = $asset->storage_path;
    $oldId = $asset->id;

    $this->actingAs($user)
        ->post(route('app.media.replace', $asset), [
            'file' => UploadedFile::fake()->image('autumn.jpg', 200, 150),
        ])
        ->assertRedirect();

    $asset->refresh();

    expect($asset->id)->toBe($oldId)
        ->and($asset->storage_path)->not->toBe($oldPath)
        ->and($asset->width)->toBe(200)
        ->and($asset->height)->toBe(150);

    Storage::disk($asset->storage_disk)->assertMissing($oldPath);
    Storage::disk($asset->storage_disk)->assertExists($asset->storage_path);
});

test('failed replace does not destroy existing asset', function () {
    [$user] = mediaUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Keep Me',
        'file' => UploadedFile::fake()->image('keep.jpg'),
    ]);

    $asset = MediaAsset::query()->firstOrFail();
    $oldPath = $asset->storage_path;

    $this->actingAs($user)
        ->post(route('app.media.replace', $asset), [
            'file' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream'),
        ])
        ->assertSessionHasErrors('file');

    $asset->refresh();
    expect($asset->storage_path)->toBe($oldPath);
    Storage::disk($asset->storage_disk)->assertExists($oldPath);
});

test('delete removes database row and physical file', function () {
    [$user] = mediaUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Delete Me',
        'file' => UploadedFile::fake()->image('delete.jpg'),
    ]);

    $asset = MediaAsset::query()->firstOrFail();
    $disk = $asset->storage_disk;
    $path = $asset->storage_path;

    $this->actingAs($user)
        ->delete(route('app.media.destroy', $asset))
        ->assertRedirect();

    expect(MediaAsset::query()->find($asset->id))->toBeNull();
    Storage::disk($disk)->assertMissing($path);
});

test('duplicate copies file based media independently', function () {
    [$user] = mediaUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Source',
        'file' => UploadedFile::fake()->image('source.jpg'),
    ]);

    $asset = MediaAsset::query()->firstOrFail();

    $this->actingAs($user)
        ->post(route('app.media.duplicate', $asset))
        ->assertRedirect();

    $copy = MediaAsset::query()->where('name', 'Source copy')->firstOrFail();

    expect($copy->id)->not->toBe($asset->id)
        ->and($copy->storage_path)->not->toBe($asset->storage_path);

    Storage::disk($copy->storage_disk)->assertExists($copy->storage_path);
    Storage::disk($asset->storage_disk)->assertExists($asset->storage_path);

    $this->actingAs($user)->delete(route('app.media.destroy', $copy));

    Storage::disk($asset->storage_disk)->assertExists($asset->storage_path);
});

test('duplicate works for text and link', function () {
    [$user, $workspace] = mediaUser();

    $text = MediaAsset::factory()->text('Hello')->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Announcement',
    ]);
    $link = MediaAsset::factory()->link('https://example.com')->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Site',
    ]);

    $this->actingAs($user)->post(route('app.media.duplicate', $text))->assertRedirect();
    $this->actingAs($user)->post(route('app.media.duplicate', $link))->assertRedirect();

    expect(MediaAsset::query()->where('name', 'Announcement copy')->exists())->toBeTrue()
        ->and(MediaAsset::query()->where('name', 'Site copy')->exists())->toBeTrue();
});

test('search filter and sort work', function () {
    [$user, $workspace] = mediaUser();

    MediaAsset::factory()->text()->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Alpha Notice',
        'text_content' => 'prayer times',
        'created_at' => now()->subDay(),
    ]);
    MediaAsset::factory()->link('https://example.com/retail')->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Zulu Link',
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('app.media', ['q' => 'prayer']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.name', 'Alpha Notice'));

    $this->actingAs($user)
        ->get(route('app.media', ['type' => 'link']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('media.data', 1)->where('media.data.0.type', 'link'));

    $this->actingAs($user)
        ->get(route('app.media', ['sort' => 'name_asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('media.data.0.name', 'Alpha Notice')
            ->where('media.data.1.name', 'Zulu Link'));
});

test('storage paths are workspace scoped and not client filenames', function () {
    [$user, $workspace] = mediaUser();

    $this->actingAs($user)->post(route('app.media.store'), [
        'type' => MediaType::Image->value,
        'name' => 'Safe',
        'file' => UploadedFile::fake()->image('../../evil.jpg'),
    ]);

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->storage_path)->toStartWith(MediaStorage::directory($workspace->id).'/')
        ->and($asset->storage_path)->not->toContain('..')
        ->and($asset->original_filename)->toContain('evil.jpg');
});

test('delete text and link removes database rows only', function () {
    [$user, $workspace] = mediaUser();

    $text = MediaAsset::factory()->text('Hello')->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Text Delete',
    ]);
    $link = MediaAsset::factory()->link('https://example.com')->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Link Delete',
    ]);

    $this->actingAs($user)
        ->delete(route('app.media.destroy', $text))
        ->assertRedirect();

    $this->actingAs($user)
        ->delete(route('app.media.destroy', $link))
        ->assertRedirect();

    expect(MediaAsset::query()->find($text->id))->toBeNull()
        ->and(MediaAsset::query()->find($link->id))->toBeNull();
});

test('delete handles missing storage file safely', function () {
    [$user, $workspace] = mediaUser();

    $asset = MediaAsset::factory()->forWorkspace($workspace)->createdBy($user)->create([
        'name' => 'Orphan Path',
        'type' => MediaType::Image,
        'storage_disk' => 'public',
        'storage_path' => 'workspaces/'.$workspace->id.'/media/missing-file.jpg',
        'original_filename' => 'missing-file.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size_bytes' => 100,
    ]);

    Storage::disk('public')->assertMissing($asset->storage_path);

    $this->actingAs($user)
        ->delete(route('app.media.destroy', $asset))
        ->assertRedirect();

    expect(MediaAsset::query()->find($asset->id))->toBeNull();
});
