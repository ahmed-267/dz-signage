<?php

namespace Database\Factories;

use App\Enums\PlaybackEventType;
use App\Models\PlaybackEvent;
use App\Models\Screen;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaybackEvent>
 */
class PlaybackEventFactory extends Factory
{
    protected $model = PlaybackEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'screen_id' => Screen::factory(),
            'type' => PlaybackEventType::ContentStarted,
            'occurred_at' => now(),
            'duration_seconds' => null,
        ];
    }
}
