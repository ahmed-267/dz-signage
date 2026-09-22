<?php

namespace Database\Seeders;

use App\Enums\DeploymentStatus;
use App\Enums\PlaybackEventType;
use App\Enums\ScreenDesignStatus;
use App\Enums\WorkspaceRole;
use App\Models\Deployment;
use App\Models\MediaAsset;
use App\Models\PlaybackEvent;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Optional large-workspace load fixture for local performance checks.
 *
 * Run: php artisan db:seed --class=LargeWorkspaceSeeder
 *
 * Creates ~100 Screens and related content under owner@dzsignage.test workspace
 * (or a dedicated "Load Test Workspace"). Not used in production seeding.
 */
class LargeWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('LargeWorkspaceSeeder refused in production.');

            return;
        }

        $user = User::query()->where('email', 'owner@dzsignage.test')->first()
            ?? User::factory()->create([
                'name' => 'Load Owner',
                'email' => 'load-owner@dzsignage.test',
                'password' => Hash::make('password'),
            ]);

        $workspace = Workspace::query()->where('name', 'Load Test Workspace')->first();
        if ($workspace === null) {
            $workspace = Workspace::factory()->create([
                'name' => 'Load Test Workspace',
                'timezone' => 'UTC',
            ]);
            $workspace->members()->create([
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner,
            ]);
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $this->command->info('Seeding large workspace fixtures…');

        MediaAsset::factory()
            ->count(50)
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->create();

        $designs = collect();
        for ($i = 0; $i < 40; $i++) {
            $design = ScreenDesign::factory()
                ->forWorkspace($workspace)
                ->createdBy($user)
                ->withDraftVersion($user)
                ->create(['name' => "Load Design {$i}"]);
            $version = $design->latestVersion();
            $version->forceFill(['published_at' => now()->subDays($i % 10)])->save();
            $design->forceFill([
                'status' => ScreenDesignStatus::Published,
                'published_version_id' => $version->id,
            ])->save();
            $designs->push($design->fresh(['publishedVersion']));
        }

        $screens = Screen::factory()
            ->count(100)
            ->forWorkspace($workspace)
            ->sequence(fn ($seq) => ['name' => 'Load Screen '.($seq->index + 1)])
            ->create();

        $playlists = Playlist::factory()
            ->count(20)
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->create();

        Schedule::factory()
            ->count(15)
            ->forWorkspace($workspace)
            ->createdBy($user)
            ->create();

        foreach ($screens->take(80) as $index => $screen) {
            $design = $designs[$index % $designs->count()];
            Deployment::factory()
                ->forScreen($screen)
                ->forDesign($design, $design->publishedVersion)
                ->create([
                    'status' => $index % 7 === 0 ? DeploymentStatus::Pending : DeploymentStatus::Active,
                    'deployed_by' => $user->id,
                    'created_at' => now()->subDays($index % 14),
                ]);

            ScreenDailyStat::factory()->create([
                'workspace_id' => $workspace->id,
                'screen_id' => $screen->id,
                'stat_date' => now()->subDays($index % 7)->toDateString(),
                'online_seconds' => 20_000 + ($index * 10),
                'offline_seconds' => 1_000,
                'playback_seconds' => 5_000,
                'content_play_count' => 10 + ($index % 5),
            ]);

            PlaybackEvent::factory()->create([
                'workspace_id' => $workspace->id,
                'screen_id' => $screen->id,
                'type' => PlaybackEventType::ContentStarted,
                'screen_design_version_id' => $design->published_version_id,
                'occurred_at' => now()->subHours($index % 48),
                'duration_seconds' => 120,
                'idempotency_key' => 'load-'.$workspace->id.'-'.$screen->id.'-'.$index,
            ]);
        }

        $this->command->info(sprintf(
            'Done: %d screens, %d designs, %d playlists in workspace #%d',
            $screens->count(),
            $designs->count(),
            $playlists->count(),
            $workspace->id,
        ));
    }
}
