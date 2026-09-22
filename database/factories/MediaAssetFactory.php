<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => MediaType::Text,
            'name' => fake()->words(3, true),
            'original_filename' => null,
            'storage_disk' => null,
            'storage_path' => null,
            'mime_type' => null,
            'extension' => null,
            'size_bytes' => null,
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'text_content' => fake()->sentence(),
            'url' => null,
            'metadata' => null,
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function text(?string $content = null): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Text,
            'text_content' => $content ?? fake()->paragraph(),
            'url' => null,
        ]);
    }

    public function link(?string $url = null): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Link,
            'text_content' => null,
            'url' => $url ?? fake()->url(),
        ]);
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
}
