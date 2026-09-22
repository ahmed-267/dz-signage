<?php

namespace Database\Factories;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Rendering\LayoutSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    protected $model = Template::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orientation = fake()->randomElement(TemplateOrientation::cases());
        $theme = fake()->randomElement(TemplateTheme::cases());

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(TemplateCategory::cases()),
            'industry' => fake()->optional()->randomElement(WorkspaceIndustry::values()),
            'orientation' => $orientation,
            'canvas_width' => $orientation->canvasWidth(),
            'canvas_height' => $orientation->canvasHeight(),
            'theme' => $theme,
            'status' => TemplateStatus::Draft,
            'thumbnail_path' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
            'published_version_id' => null,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'workspace_id' => null,
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => TemplateStatus::Published,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => TemplateStatus::Archived,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function withDraftVersion(?User $user = null, ?array $schema = null): static
    {
        return $this->afterCreating(function (Template $template) use ($user, $schema): void {
            $orientation = $template->orientation;
            $theme = $template->theme;

            $resolved = $schema ?? LayoutSchema::blank($orientation, $theme);
            if ($schema === null) {
                // Meaningful default so visual/render tests don't get an empty canvas by accident.
                $resolved['elements'] = [
                    [
                        'id' => 'factory-title',
                        'type' => 'text',
                        'name' => 'Sample Heading',
                        'x' => 80,
                        'y' => 80,
                        'width' => 800,
                        'height' => 80,
                        'zIndex' => 1,
                        'props' => [
                            'text' => 'Sample Heading',
                            'fontSize' => 48,
                            'fontWeight' => 700,
                            'color' => '#0F172A',
                        ],
                    ],
                ];
            }

            $template->versions()->create([
                'version_number' => 1,
                'schema' => $resolved,
                'created_by' => $user !== null ? $user->id : $template->created_by,
                'published_at' => null,
            ]);
        });
    }
}
