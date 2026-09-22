<?php

use App\Enums\MediaType;
use App\Enums\ScreenOperationalStatus;
use App\Enums\WorkspaceRole;
use App\Models\BrandKit;
use App\Models\Deployment;
use App\Models\Location;
use App\Models\MediaAsset;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\WorkspaceBillingOverride;
use App\Models\WorkspaceMember;
use App\Support\Demo\ProductionDemoAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config([
        'rmsignage_demo.email' => 'demo@rmsignage.com',
        'rmsignage_demo.workspace_slug' => 'rmsignage-demo-north-bean',
        'rmsignage_demo.workspace_name' => 'North & Bean Café',
        'rmsignage_demo.seed_tag' => 'rmsignage-production-demo',
    ]);

    putenv('RMSIGNAGE_DEMO_EMAIL=demo@rmsignage.com');
    putenv('RMSIGNAGE_DEMO_PASSWORD=demo-secret-password-for-tests');
    $_ENV['RMSIGNAGE_DEMO_EMAIL'] = 'demo@rmsignage.com';
    $_ENV['RMSIGNAGE_DEMO_PASSWORD'] = 'demo-secret-password-for-tests';
    $_SERVER['RMSIGNAGE_DEMO_EMAIL'] = 'demo@rmsignage.com';
    $_SERVER['RMSIGNAGE_DEMO_PASSWORD'] = 'demo-secret-password-for-tests';
});

afterEach(function () {
    putenv('RMSIGNAGE_DEMO_PASSWORD');
    unset($_ENV['RMSIGNAGE_DEMO_PASSWORD'], $_SERVER['RMSIGNAGE_DEMO_PASSWORD']);
});

test('production seed refuses without allow-production flag', function () {
    $previous = app()->environment();
    app()->detectEnvironment(fn () => 'production');

    try {
        $exit = Artisan::call('rmsignage:seed-demo-account');
        $output = Artisan::output();

        expect($exit)->toBe(1)
            ->and($output)->toContain('Refusing to seed the demo account in production')
            ->and($output)->toContain('--allow-production');
    } finally {
        app()->detectEnvironment(fn () => $previous);
    }
});

test('missing demo password fails clearly without logging secrets', function () {
    putenv('RMSIGNAGE_DEMO_PASSWORD=');
    $_ENV['RMSIGNAGE_DEMO_PASSWORD'] = '';
    $_SERVER['RMSIGNAGE_DEMO_PASSWORD'] = '';

    $exit = Artisan::call('rmsignage:seed-demo-account');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('RMSIGNAGE_DEMO_PASSWORD is missing')
        ->and($output)->not->toContain('demo-secret-password-for-tests');
});

test('seed creates expected demo business and is idempotent', function () {
    $otherOwner = User::factory()->create(['email' => 'real-customer@example.com']);
    $otherWorkspace = attachWorkspace($otherOwner, null, WorkspaceRole::Owner);
    $otherWorkspace->forceFill(['name' => 'Real Customer Business', 'slug' => 'real-customer-business'])->save();
    $otherMedia = MediaAsset::query()->create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'Customer Photo',
        'type' => MediaType::Image,
        'created_by' => $otherOwner->id,
        'updated_by' => $otherOwner->id,
        'metadata' => ['seed' => 'customer'],
    ]);

    expect(Artisan::call('rmsignage:seed-demo-account'))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('RMSignage demo account seeded successfully')
        ->and($output)->toContain('demo@rmsignage.com')
        ->and($output)->toContain('North & Bean Café')
        ->and($output)->not->toContain('demo-secret-password-for-tests');

    $workspace = ProductionDemoAccount::findWorkspace();
    expect($workspace)->not->toBeNull()
        ->and($workspace->name)->toBe('North & Bean Café');

    $owner = User::query()->where('email', 'demo@rmsignage.com')->first();
    expect($owner)->not->toBeNull();
    expect(
        WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $owner->id)
            ->where('role', WorkspaceRole::Owner)
            ->exists()
    )->toBeTrue();

    $firstMedia = MediaAsset::query()->where('workspace_id', $workspace->id)->count();
    $firstScreens = ScreenDesign::query()->where('workspace_id', $workspace->id)->count();
    $firstTvs = Screen::query()->where('workspace_id', $workspace->id)->count();
    $firstPlaylists = Playlist::query()->where('workspace_id', $workspace->id)->count();
    $firstSchedules = Schedule::query()->where('workspace_id', $workspace->id)->count();
    $firstLocations = Location::query()->where('workspace_id', $workspace->id)->count();
    $firstStats = ScreenDailyStat::query()->where('workspace_id', $workspace->id)->count();

    expect($firstLocations)->toBe(3)
        ->and($firstTvs)->toBe(5)
        ->and($firstScreens)->toBeGreaterThanOrEqual(8)
        ->and($firstScreens)->toBeLessThanOrEqual(12)
        ->and($firstPlaylists)->toBe(5)
        ->and($firstSchedules)->toBeGreaterThanOrEqual(4)
        ->and($firstMedia)->toBeGreaterThan(10)
        ->and($firstStats)->toBe(5 * 90);

    expect(
        ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($q): void {
                $q->where('name', 'like', 'Untitled%')
                    ->orWhere('name', 'like', 'Blank%')
                    ->orWhere('name', 'like', 'E2E %');
            })
            ->count()
    )->toBe(0);

    $brand = BrandKit::query()->where('workspace_id', $workspace->id)->first();
    expect($brand)->not->toBeNull()
        ->and($brand->name)->toBe('North & Bean Café')
        ->and($brand->tagline)->toBe('Coffee, brunch & good company')
        ->and($brand->logo_media_asset_id)->not->toBeNull();

    $roles = WorkspaceMember::query()
        ->where('workspace_id', $workspace->id)
        ->pluck('role')
        ->map(fn ($role) => $role instanceof WorkspaceRole ? $role->value : (string) $role)
        ->all();

    expect($roles)->toEqualCanonicalizing([
        WorkspaceRole::Owner->value,
        WorkspaceRole::Admin->value,
        WorkspaceRole::Designer->value,
        WorkspaceRole::ContentManager->value,
    ])
        ->and($roles)->not->toContain(WorkspaceRole::Viewer->value)
        ->and($roles)->not->toContain(WorkspaceRole::LocationManager->value);

    $devices = ScreenDevice::query()
        ->whereIn('screen_id', Screen::query()->where('workspace_id', $workspace->id)->pluck('id'))
        ->get();

    expect($devices)->not->toBeEmpty();
    foreach ($devices as $device) {
        $meta = $device->platform_meta ?? [];
        expect($meta['credentials_issued'] ?? null)->toBeFalse()
            ->and($meta['seed'] ?? null)->toBe('rmsignage-production-demo');
    }

    expect(WorkspaceBillingOverride::query()->where('workspace_id', $workspace->id)->exists())->toBeTrue();
    expect(
        DB::table('subscriptions')->where('workspace_id', $workspace->id)->count()
    )->toBe(0);

    // Re-run: no duplicates
    expect(Artisan::call('rmsignage:seed-demo-account'))->toBe(0);

    expect(MediaAsset::query()->where('workspace_id', $workspace->id)->count())->toBe($firstMedia)
        ->and(ScreenDesign::query()->where('workspace_id', $workspace->id)->count())->toBe($firstScreens)
        ->and(Screen::query()->where('workspace_id', $workspace->id)->count())->toBe($firstTvs)
        ->and(Playlist::query()->where('workspace_id', $workspace->id)->count())->toBe($firstPlaylists)
        ->and(Schedule::query()->where('workspace_id', $workspace->id)->count())->toBe($firstSchedules)
        ->and(Location::query()->where('workspace_id', $workspace->id)->count())->toBe($firstLocations)
        ->and(ScreenDailyStat::query()->where('workspace_id', $workspace->id)->count())->toBe($firstStats)
        ->and(User::query()->where('email', 'demo@rmsignage.com')->count())->toBe(1);

    // Other Business untouched
    expect($otherWorkspace->fresh()->name)->toBe('Real Customer Business')
        ->and(MediaAsset::query()->find($otherMedia->id))->not->toBeNull()
        ->and(MediaAsset::query()->where('workspace_id', $otherWorkspace->id)->count())->toBe(1);

    $statDates = ScreenDailyStat::query()
        ->where('workspace_id', $workspace->id)
        ->selectRaw('MIN(stat_date) as min_date, MAX(stat_date) as max_date')
        ->first();

    $span = Carbon::parse($statDates->min_date)
        ->diffInDays(Carbon::parse($statDates->max_date)) + 1;

    expect($span)->toBeGreaterThanOrEqual(85)
        ->and($span)->toBeLessThanOrEqual(91);

    expect(Deployment::query()->where('workspace_id', $workspace->id)->count())->toBeGreaterThan(0);
});

test('demo telemetry refresh only touches designated demo business', function () {
    expect(Artisan::call('rmsignage:seed-demo-account'))->toBe(0);

    $otherOwner = User::factory()->create();
    $other = attachWorkspace($otherOwner, null, WorkspaceRole::Owner);
    $foreignTv = Screen::factory()->forWorkspace($other)->createdBy($otherOwner)->create([
        'name' => 'Foreign TV',
        'operational_status' => ScreenOperationalStatus::Active,
    ]);
    $beforeSeen = now()->subDays(2);
    $foreignDevice = ScreenDevice::factory()->forScreen($foreignTv)->create([
        'device_identifier' => 'foreign-device-1',
        'last_seen_at' => $beforeSeen,
        'platform_meta' => ['seed' => 'not-demo'],
    ]);

    expect(Artisan::call('rmsignage:refresh-demo-telemetry'))->toBe(0);

    expect($foreignDevice->fresh()->last_seen_at->lt(now()->subDay()))->toBeTrue();

    $demoTv = Screen::query()
        ->where('workspace_id', ProductionDemoAccount::findWorkspace()->id)
        ->where('name', 'Counter TV')
        ->first();

    expect($demoTv)->not->toBeNull();
    $demoDevice = ScreenDevice::query()->where('screen_id', $demoTv->id)->first();
    expect($demoDevice)->not->toBeNull()
        ->and($demoDevice->last_seen_at->greaterThan(now()->subMinutes(5)))->toBeTrue();
});
