<?php

namespace Database\Factories;

use App\Enums\AiGenerationStatus;
use App\Enums\AiGenerationType;
use App\Models\AiGeneration;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    protected $model = AiGeneration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'type' => AiGenerationType::Text,
            'status' => AiGenerationStatus::Completed,
            'provider' => 'fake',
            'model' => 'fake-text-v1',
            'prompt' => fake()->sentence(),
            'options' => [],
            'output' => ['text' => 'Sample AI copy'],
            'completed_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AiGenerationStatus::Failed,
            'error_code' => 'provider_error',
            'error_message' => 'This request could not be generated. Try adjusting your prompt.',
            'output' => null,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'type' => AiGenerationType::Image,
            'model' => 'fake-image-v1',
            'output' => [
                'mime_type' => 'image/png',
                'extension' => 'png',
                'width' => 1920,
                'height' => 1080,
                'aspect' => 'landscape',
            ],
        ]);
    }
}
