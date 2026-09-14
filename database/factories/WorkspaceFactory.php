<?php

namespace Database\Factories;

use App\Enums\WorkspaceIndustry;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'industry' => fake()->randomElement(WorkspaceIndustry::cases()),
            'country' => 'United Kingdom',
            'timezone' => 'Europe/London',
            'logo_path' => null,
        ];
    }
}
