<?php

use App\Models\User;
use App\Support\ProductBrand;

test('product brand name is RMSignage', function () {
    expect(ProductBrand::NAME)->toBe('RMSignage')
        ->and(ProductBrand::name())->toBe('RMSignage')
        ->and(ProductBrand::adminName())->toBe('RMSignage Admin');
});

test('customer-visible UI sources do not contain DZ Signage', function () {
    $roots = [
        resource_path('js'),
        resource_path('views'),
        app_path('Http/Middleware/HandleInertiaRequests.php'),
        app_path('Support/ProductBrand.php'),
        app_path('Support/Platform/PlatformSettingsStore.php'),
        config_path('app.php'),
    ];

    $forbidden = 'DZ Signage';
    $hits = [];

    $scanFile = function (string $path) use (&$hits, $forbidden): void {
        $contents = file_get_contents($path);
        if ($contents !== false && str_contains($contents, $forbidden)) {
            $hits[] = $path;
        }
    };

    foreach ($roots as $root) {
        if (is_file($root)) {
            $scanFile($root);

            continue;
        }

        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['tsx', 'ts', 'jsx', 'js', 'php', 'blade.php', 'css', 'html'], true)
                && ! str_ends_with($file->getFilename(), '.blade.php')
            ) {
                continue;
            }

            $scanFile($file->getPathname());
        }
    }

    expect($hits)->toBeEmpty('Found forbidden "DZ Signage" in: '.implode(', ', $hits));
});

test('shared inertia name prop is ProductBrand RMSignage', function () {
    $user = User::factory()->create();
    attachWorkspace($user);

    $this->actingAs($user)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('name', 'RMSignage'));
});
