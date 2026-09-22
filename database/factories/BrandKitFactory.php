<?php

namespace Database\Factories;

use App\Models\BrandKit;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandKit>
 */
class BrandKitFactory extends Factory
{
    protected $model = BrandKit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company(),
            'tagline' => fake()->optional()->sentence(4),
            'primary_color' => BrandKit::DEFAULT_PRIMARY,
            'secondary_color' => BrandKit::DEFAULT_SECONDARY,
            'accent_color' => BrandKit::DEFAULT_ACCENT,
            'background_color' => BrandKit::DEFAULT_BACKGROUND,
            'text_color' => BrandKit::DEFAULT_TEXT,
            'heading_font' => BrandKit::DEFAULT_HEADING_FONT,
            'body_font' => BrandKit::DEFAULT_BODY_FONT,
            'logo_media_asset_id' => null,
            'secondary_logo_media_asset_id' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
            'name' => $workspace->name,
        ]);
    }
}
