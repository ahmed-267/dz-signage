<?php

namespace Database\Factories;

use App\Enums\PlaylistTransition;
use App\Enums\PlaylistTransitionSpeed;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlaylistItem>
 */
class PlaylistItemFactory extends Factory
{
    protected $model = PlaylistItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playlist_version_id' => PlaylistVersion::factory(),
            'screen_design_id' => ScreenDesign::factory(),
            'screen_design_version_id' => ScreenDesignVersion::factory(),
            'position' => 1,
            'duration_seconds' => (int) config('playlists.default_duration_seconds', 10),
            'loop_count' => (int) config('playlists.default_loop_count', 1),
            'transition' => PlaylistTransition::Fade,
            'transition_speed' => PlaylistTransitionSpeed::Normal,
            'is_active' => true,
        ];
    }

    public function forVersion(PlaylistVersion $version): static
    {
        return $this->state(fn () => [
            'playlist_version_id' => $version->id,
        ]);
    }

    public function forDesign(ScreenDesign $design, ?ScreenDesignVersion $version = null): static
    {
        $version ??= $design->publishedVersion;

        return $this->state(fn () => [
            'screen_design_id' => $design->id,
            'screen_design_version_id' => $version?->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
