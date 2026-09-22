<?php

use App\Support\Demo\DemoPhotoCatalog;
use App\Support\Marketing\MarketingImageFactory;
use Illuminate\Support\Facades\File;

test('curated marketing photos exist as local jpeg files', function () {
    $cafe = public_path('images/marketing/cafe-iced-coffee.jpg');
    expect(File::exists($cafe))->toBeTrue()
        ->and(File::size($cafe))->toBeGreaterThan(10_000);

    $binary = File::get($cafe);
    expect(substr($binary, 0, 3))->toBe("\xFF\xD8\xFF"); // JPEG SOI
});

test('demo photo catalog ships real jpeg files', function () {
    DemoPhotoCatalog::assertPresent();

    $first = DemoPhotoCatalog::all()[0];
    $binary = (string) file_get_contents(DemoPhotoCatalog::path($first['file']));
    expect(substr($binary, 0, 3))->toBe("\xFF\xD8\xFF");
});

test('marketing generate assets syncs curated photos without remote hotlinks', function () {
    DemoPhotoCatalog::assertPresent();

    $this->artisan('marketing:generate-assets', ['--force' => true])
        ->assertSuccessful();

    foreach (MarketingImageFactory::catalog() as $spec) {
        $path = public_path('images/marketing/'.$spec['file']);
        expect(File::exists($path))->toBeTrue()
            ->and(File::size($path))->toBeGreaterThan(1000);
    }
});

test('marketing gd compose fallback still encodes png for tests', function () {
    $binary = MarketingImageFactory::compose(
        'ICED LATTE',
        '40% OFF THIS WEEKEND',
        [28, 18, 12],
        [194, 120, 62],
        'coffee',
        640,
        360,
        'CAFE SPECIAL',
    );

    expect($binary)->not->toBeEmpty()
        ->and(substr($binary, 0, 8))->toBe("\x89PNG\r\n\x1a\n");
});
