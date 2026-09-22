<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\PlatformSetting;
use App\Support\ProductBrand;
use Illuminate\Database\Seeder;

class PlatformOpsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BillingPlanSeeder::class);

        $flags = [
            [
                'key' => 'widgets_enabled',
                'name' => 'Widgets',
                'description' => 'Enable LayoutSchema widget elements and widget data endpoints.',
                'enabled' => true,
            ],
            [
                'key' => 'offline_player_enabled',
                'name' => 'Offline Player',
                'description' => 'Enable Player offline package sync and service worker caching.',
                'enabled' => true,
            ],
            [
                'key' => 'ai_content_generation',
                'name' => 'AI Content Generation',
                'description' => 'Enable AI text, image, and Screen Design generation in Media and the Editor.',
                'enabled' => true,
            ],
        ];

        foreach ($flags as $flag) {
            FeatureFlag::query()->updateOrCreate(
                ['key' => $flag['key']],
                $flag,
            );
        }

        $settings = [
            'platform_name' => ProductBrand::NAME,
            'support_email' => 'support@dz.local',
        ];

        foreach ($settings as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
