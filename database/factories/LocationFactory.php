<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->unique()->company().' Site',
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => fake()->city(),
            'region' => fake()->optional()->city(),
            'postcode' => fake()->postcode(),
            'country' => 'UK',
            'timezone' => 'Europe/London',
            'notes' => null,
            'archived_at' => null,
            'created_by' => User::factory(),
        ];
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => [
            'workspace_id' => $workspace->id,
            'timezone' => $workspace->timezone,
            'country' => $workspace->country === 'United Kingdom' ? 'UK' : $workspace->country,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'archived_at' => now(),
        ]);
    }
}
