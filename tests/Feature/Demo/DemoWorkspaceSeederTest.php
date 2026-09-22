<?php

use App\Enums\MediaType;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\ScreenDesign;
use App\Models\ScreenHeartbeat;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Demo\DemoPdfFactory;
use Database\Seeders\DemoWorkspaceSeeder;

test('demo pdf factory produces a valid pdf header', function () {
    $pdf = DemoPdfFactory::document('Summer Menu.pdf', "Latte\nMocha");

    expect($pdf)->toStartWith('%PDF-1.4')
        ->and(strlen($pdf))->toBeGreaterThan(100);
});

test('demo workspace seeder is idempotent and seeds curated photo media', function () {
    config(['app.env' => 'testing']);

    $owner = User::factory()->create(['email' => 'owner@dz.local']);
    attachWorkspace($owner, null, WorkspaceRole::Owner);

    $this->seed(DemoWorkspaceSeeder::class);
    $firstMediaCount = MediaAsset::query()->count();
    $firstPlaylistCount = Playlist::query()->count();

    $this->seed(DemoWorkspaceSeeder::class);

    expect(MediaAsset::query()->count())->toBe($firstMediaCount)
        ->and(Playlist::query()->count())->toBe($firstPlaylistCount);

    $workspace = Workspace::query()->where('slug', 'local-dev-workspace')->first();
    expect($workspace)->not->toBeNull();

    $media = MediaAsset::query()->where('workspace_id', $workspace->id)->get();
    expect($media->where('type', MediaType::Image)->count())->toBeGreaterThanOrEqual(12)
        ->and($media->where('type', MediaType::Logo)->count())->toBeGreaterThanOrEqual(2)
        ->and($media->where('type', MediaType::Document)->count())->toBe(3)
        ->and($media->where('type', MediaType::Text)->count())->toBeGreaterThanOrEqual(3)
        ->and($media->where('type', MediaType::Link)->count())->toBeGreaterThanOrEqual(2);

    $latte = $media->firstWhere('name', 'Iced Latte.jpg');
    expect($latte)->not->toBeNull()
        ->and($latte->mime_type)->toBe('image/jpeg')
        ->and($latte->extension)->toBe('jpg')
        ->and($latte->size_bytes)->toBeGreaterThan(10_000);

    // No leftover Untitled / Design Img pollution in the demo workspace.
    expect(
        ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'like', 'Untitled%')
            ->count()
    )->toBe(0);

    $designNames = ScreenDesign::query()
        ->where('workspace_id', $workspace->id)
        ->orderBy('name')
        ->pluck('name')
        ->all();

    expect($designNames)->toEqualCanonicalizing([
        'Morning Coffee Promotion',
        'Lunch Menu Board',
        'Weekend Sale',
        'Lobby Welcome',
        'Hotel Welcome',
        'Friday Announcement',
        'Prayer Information',
        'Company News',
    ]);

    expect(
        MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where('name', 'like', 'Design Img%')
            ->count()
    )->toBe(0);

    $logo = $media->firstWhere('name', 'North & Bean Logo');
    expect($logo)->not->toBeNull()
        ->and($logo->extension)->toBe('svg')
        ->and($logo->mime_type)->toBe('image/svg+xml')
        ->and(is_file(resource_path('demo/logos/north-bean.svg')))->toBeTrue();

    // Wordmark SVGs must not use ellipse ring graphics.
    $svg = (string) file_get_contents(resource_path('demo/logos/north-bean.svg'));
    expect($svg)->toContain('<rect')
        ->and($svg)->not->toContain('<ellipse')
        ->and($svg)->not->toContain('<circle');

    $doc = $media->firstWhere('type', MediaType::Document);
    expect($doc)->not->toBeNull()
        ->and($doc->mime_type)->toBe('application/pdf')
        ->and($doc->size_bytes)->toBeGreaterThan(100);

    expect(Playlist::query()->where('workspace_id', $workspace->id)->count())->toBeGreaterThanOrEqual(4);

    expect(
        ScreenHeartbeat::query()
            ->where('metadata->seed', 'demo-workspace')
            ->count()
    )->toBeGreaterThan(0);
});
