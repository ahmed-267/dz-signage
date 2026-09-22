<?php

namespace Database\Factories;

use App\Enums\ScreenOperationalStatus;
use App\Models\Screen;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screen>
 */
class ScreenFactory extends Factory
{
    protected $model = Screen::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->city().' Screen',
            'location_id' => null,
            'orientation' => fake()->randomElement(['landscape', 'portrait', null]),
            'operational_status' => ScreenOperationalStatus::Active,
            'created_by' => User::factory(),
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
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'operational_status' => ScreenOperationalStatus::Inactive,
        ]);
    }
}
