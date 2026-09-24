<?php

namespace Database\Seeders;

use App\Enums\MediaType;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Demo\E2eArtifactCleaner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ready-to-use local accounts for manual testing (no email delivery required).
 *
 * Run: php artisan db:seed --class=LocalDevSeeder
 * Or:  php artisan db:seed  (when APP_ENV=local)
 */
class LocalDevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('LocalDevSeeder refuses to run in production.');

            return;
        }

        $owner = $this->user(
            email: 'owner@dz.local',
            name: 'Local Owner',
            admin: false,
        );

        $viewer = $this->user(
            email: 'viewer@dz.local',
            name: 'Local Viewer',
            admin: false,
        );

        $superAdmin = $this->user(
            email: 'admin@dz.local',
            name: 'Local Super Admin',
            admin: true,
        );

        $platformAdmin = $this->user(
            email: 'platform@dz.local',
            name: 'Local Admin',
            admin: false,
            platformRole: PlatformRole::PlatformAdmin,
        );

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'local-dev-workspace'],
            [
                'name' => 'Local Dev Workspace',
                'industry' => WorkspaceIndustry::Corporate,
                'country' => 'United Kingdom',
                'timezone' => 'Europe/London',
            ],
        );

        $this->member($workspace, $owner, WorkspaceRole::Owner);
        $this->member($workspace, $viewer, WorkspaceRole::Viewer);
        $this->member($workspace, $superAdmin, WorkspaceRole::Admin);
        $this->member($workspace, $platformAdmin, WorkspaceRole::Admin);

        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();
        $viewer->forceFill(['current_workspace_id' => $workspace->id])->save();
        $superAdmin->forceFill(['current_workspace_id' => $workspace->id])->save();
        $platformAdmin->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->seedSampleMedia($workspace, $owner);
        $this->cleanupE2eArtifacts();
        $this->call(PlatformTemplatesSeeder::class);
        $this->call(PlatformOpsSeeder::class);
        $this->call(DemoWorkspaceSeeder::class);

        $this->command->info('Local accounts ready (password for all: password)');
        $this->command->table(
            ['Email', 'Role', 'Notes'],
            [
                ['owner@dz.local', 'Workspace Owner', 'Full app + Media'],
                ['viewer@dz.local', 'Viewer', 'Read-only Media'],
                ['admin@dz.local', 'Super Admin + Workspace Admin', '/admin + workspace'],
                ['platform@dz.local', 'Platform Admin', '/admin ops (no role/settings/flags write)'],
            ],
        );
    }

    private function user(
        string $email,
        string $name,
        bool $admin,
        ?PlatformRole $platformRole = null,
    ): User {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $resolvedRole = $platformRole
            ?? ($admin ? PlatformRole::SuperAdmin : null);

        $user->forceFill([
            'name' => $name,
            'email_verified_at' => now(),
            'is_admin' => $resolvedRole === PlatformRole::SuperAdmin,
            'platform_role' => $resolvedRole,
            'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
        ])->save();

        if (! $user->wasRecentlyCreated) {
            // Ensure password stays usable if the row already existed with another hash.
            $user->forceFill(['password' => 'password'])->save();
        }

        return $user->fresh() ?? $user;
    }

    private function member(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        WorkspaceMember::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            ['role' => $role],
        );
    }

    private function seedSampleMedia(Workspace $workspace, User $owner): void
    {
        $samples = [
            [
                'type' => MediaType::Text,
                'name' => 'Welcome Notice',
                'text_content' => 'Welcome to RMSignage local development.',
            ],
            [
                'type' => MediaType::Text,
                'name' => 'Friday Prayer Notice',
                'text_content' => "Jumu'ah begins at 1:30 PM.",
            ],
            [
                'type' => MediaType::Link,
                'name' => 'Organisation Website',
                'url' => 'https://example.com',
            ],
        ];

        foreach ($samples as $sample) {
            MediaAsset::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $sample['name'],
                ],
                [
                    'type' => $sample['type'],
                    'text_content' => $sample['text_content'] ?? null,
                    'url' => $sample['url'] ?? null,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'metadata' => [
                        'seed' => 'local-dev',
                        'slug' => Str::slug($sample['name']),
                    ],
                ],
            );
        }
    }

    /**
     * Remove disposable E2E / Playwright clutter from local libraries.
     * Does not delete legitimate Untitled designs or non-E2E templates.
     */
    private function cleanupE2eArtifacts(): void
    {
        E2eArtifactCleaner::run();
    }
}
