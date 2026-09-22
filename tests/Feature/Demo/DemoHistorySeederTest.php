<?php

use App\Enums\WorkspaceRole;
use App\Models\PlaybackEvent;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoWorkspaceSeeder;
use Database\Seeders\PlatformTemplatesSeeder;
use Illuminate\Support\Facades\Artisan;

test('demo history seeder creates multi-month stats playback and dashboard metrics', function () {
    config(['app.env' => 'testing']);

    $this->seed(PlatformTemplatesSeeder::class);

    $owner = User::factory()->create(['email' => 'owner@dz.local']);
    attachWorkspace($owner, null, WorkspaceRole::Owner);

    $seeder = app(DemoWorkspaceSeeder::class);
    $seeder->freshDemo = true;
    $seeder->run();

    $owner->refresh();

    $workspace = Workspace::query()->where('slug', 'local-dev-workspace')->first();
    expect($workspace)->not->toBeNull()
        ->and($owner->current_workspace_id)->toBe($workspace->id);

    $screenIds = Screen::query()->where('workspace_id', $workspace->id)->pluck('id');
    expect($screenIds)->toHaveCount(5);

    expect(ScreenDevice::query()->whereIn('screen_id', $screenIds)->whereNull('revoked_at')->count())
        ->toBe(5);

    $statDates = ScreenDailyStat::query()
        ->where('workspace_id', $workspace->id)
        ->distinct()
        ->orderBy('stat_date')
        ->pluck('stat_date');

    expect($statDates->count())->toBeGreaterThan(60);

    $spanDays = $statDates->first()->diffInDays($statDates->last());
    expect($spanDays)->toBeGreaterThan(60);

    expect(PlaybackEvent::query()->where('workspace_id', $workspace->id)->count())
        ->toBeGreaterThan(50)
        ->toBeLessThan(2000);

    $this->actingAs($owner)
        ->get(route('app.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('app/dashboard')
            ->where('empty', false)
            ->where('metrics.screens.total', fn ($total) => $total > 0)
            ->has('recentDeployments')
            ->has('availabilitySeries', 30)
            ->has('contentActivity')
        );
});

test('dz:seed-demo artisan command refuses production', function () {
    $this->app['env'] = 'production';

    $exit = Artisan::call('dz:seed-demo');

    expect($exit)->toBe(1)
        ->and(Artisan::output())->toContain('refuses to run in production');
});
