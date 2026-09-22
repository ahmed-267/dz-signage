<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenDailyStat>
 */
class ScreenDailyStatFactory extends Factory
{
    protected $model = ScreenDailyStat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'screen_id' => Screen::factory(),
            'stat_date' => now()->toDateString(),
            'online_seconds' => 3600,
            'offline_seconds' => 0,
            'playback_seconds' => 1800,
            'content_play_count' => 3,
            'error_count' => 0,
            'heartbeat_count' => 80,
        ];
    }
}
