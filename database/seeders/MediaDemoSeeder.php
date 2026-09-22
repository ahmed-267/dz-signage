<?php

namespace Database\Seeders;

use App\Enums\MediaType;
use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MediaDemoSeeder extends Seeder
{
    /**
     * Lightweight text/link demo media across industries (no binary files).
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'demo@media.dz-signage.test'],
            [
                'name' => 'Media Demo User',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'demo-media-workspace'],
            [
                'name' => 'Demo Media Workspace',
                'industry' => WorkspaceIndustry::Corporate,
                'country' => 'United Kingdom',
                'timezone' => 'Europe/London',
            ],
        );

        WorkspaceMember::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
            ],
            ['role' => WorkspaceRole::Owner],
        );

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        $samples = [
            [
                'type' => MediaType::Text,
                'name' => 'Friday Prayer Notice',
                'text_content' => "Jumu'ah begins at 1:30 PM. Please arrive early.",
            ],
            [
                'type' => MediaType::Text,
                'name' => 'Retail Opening Hours',
                'text_content' => 'Open today 9:00–20:00. Click & collect available.',
            ],
            [
                'type' => MediaType::Text,
                'name' => 'Corporate Welcome',
                'text_content' => 'Welcome to HQ. Visitors please check in at reception.',
            ],
            [
                'type' => MediaType::Text,
                'name' => 'Healthcare Waiting Room Notice',
                'text_content' => 'Please sanitise your hands and wear a mask in clinical areas.',
            ],
            [
                'type' => MediaType::Text,
                'name' => 'Restaurant Special',
                'text_content' => 'Today\'s chef special: grilled sea bass with lemon butter.',
            ],
            [
                'type' => MediaType::Link,
                'name' => 'Organisation Website',
                'url' => 'https://example.com',
            ],
            [
                'type' => MediaType::Link,
                'name' => 'Live Event Stream',
                'url' => 'https://example.com/live',
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
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'metadata' => [
                        'seed' => true,
                        'slug' => Str::slug($sample['name']),
                    ],
                ],
            );
        }
    }
}
