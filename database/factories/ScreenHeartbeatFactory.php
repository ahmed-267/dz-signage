<?php

namespace Database\Factories;

use App\Enums\PlayerPlaybackState;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenHeartbeat>
 */
class ScreenHeartbeatFactory extends Factory
{
    protected $model = ScreenHeartbeat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screen_id' => Screen::factory(),
            'screen_device_id' => ScreenDevice::factory(),
            'recorded_at' => now(),
            'player_version' => '1.0.0',
            'user_agent' => 'Mozilla/5.0',
            'viewport_width' => 1920,
            'viewport_height' => 1080,
            'orientation' => 'landscape',
            'deployment_id' => null,
            'screen_design_version_id' => null,
            'playback_state' => PlayerPlaybackState::Ready->value,
            'error_code' => null,
            'metadata' => null,
        ];
    }
}
