<?php

namespace Database\Factories;

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\User;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenDesignVersion>
 */
class ScreenDesignVersionFactory extends Factory
{
    protected $model = ScreenDesignVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screen_design_id' => ScreenDesign::factory(),
            'version_number' => 1,
            'schema' => LayoutSchema::blank(TemplateOrientation::Landscape, TemplateTheme::Blank),
            'created_by' => User::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'published_at' => now(),
        ]);
    }
}
