<?php

namespace Database\Factories;

use App\Enums\PlatformErrorCategory;
use App\Models\PlatformError;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformError>
 */
class PlatformErrorFactory extends Factory
{
    protected $model = PlatformError::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => PlatformErrorCategory::Other,
            'workspace_id' => null,
            'screen_id' => null,
            'deployment_id' => null,
            'message' => fake()->sentence(),
            'metadata' => null,
            'occurred_at' => now(),
            'resolved_at' => null,
            'resolved_by' => null,
        ];
    }

    public function unresolved(): static
    {
        return $this->state(fn () => [
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
        ]);
    }

    public function widgetData(): static
    {
        return $this->state(fn () => [
            'category' => PlatformErrorCategory::WidgetData,
        ]);
    }
}
