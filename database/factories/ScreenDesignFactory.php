<?php

namespace Database\Factories;

use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Models\ScreenDesign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScreenDesign>
 */
class ScreenDesignFactory extends Factory
{
    protected $model = ScreenDesign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orientation = fake()->randomElement(TemplateOrientation::cases());

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
            'orientation' => $orientation,
            'canvas_width' => $orientation->canvasWidth(),
            'canvas_height' => $orientation->canvasHeight(),
            'source_template_id' => null,
            'source_template_version_id' => null,
            'status' => ScreenDesignStatus::Draft,
            'thumbnail_path' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'published_version_id' => null,
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    public function landscape(): static
    {
        return $this->state(fn () => [
            'orientation' => TemplateOrientation::Landscape,
            'canvas_width' => TemplateOrientation::Landscape->canvasWidth(),
            'canvas_height' => TemplateOrientation::Landscape->canvasHeight(),
        ]);
    }

    public function portrait(): static
    {
        return $this->state(fn () => [
            'orientation' => TemplateOrientation::Portrait,
            'canvas_width' => TemplateOrientation::Portrait->canvasWidth(),
            'canvas_height' => TemplateOrientation::Portrait->canvasHeight(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function withDraftVersion(?User $user = null, ?array $schema = null): static
    {
        return $this->afterCreating(function (ScreenDesign $design) use ($user, $schema): void {
            $design->versions()->create([
                'version_number' => 1,
                'schema' => $schema ?? LayoutSchema::blank(
                    $design->orientation,
                    TemplateTheme::Blank,
                ),
                'created_by' => $user !== null ? $user->id : $design->created_by,
                'published_at' => null,
            ]);
        });
    }
}
