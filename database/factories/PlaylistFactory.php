<?php

namespace Database\Factories;

use App\Enums\PlaylistStatus;
use App\Enums\TemplateOrientation;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    protected $model = Playlist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true),
            'description' => null,
            'orientation' => null,
            'status' => PlaylistStatus::Draft,
            'created_by' => User::factory(),
            'updated_by' => null,
            'published_version_id' => null,
            'published_at' => null,
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
        ]);
    }

    public function portrait(): static
    {
        return $this->state(fn () => [
            'orientation' => TemplateOrientation::Portrait,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => PlaylistStatus::Archived,
        ]);
    }

    public function withDraftVersion(?User $user = null): static
    {
        return $this->afterCreating(function (Playlist $playlist) use ($user): void {
            $playlist->versions()->create([
                'version_number' => 1,
                'created_by' => $user !== null ? $user->id : $playlist->created_by,
                'published_at' => null,
            ]);
        });
    }
}
