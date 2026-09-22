<?php

namespace Database\Factories;

use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeatureFlag>
 */
class FeatureFlagFactory extends Factory
{
    protected $model = FeatureFlag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = Str::lower(Str::random(8));

        return [
            'key' => 'flag_'.$suffix,
            'name' => 'Flag '.$suffix,
            'description' => fake()->sentence(),
            'enabled' => false,
        ];
    }

    public function enabled(bool $enabled = true): static
    {
        return $this->state(fn () => [
            'enabled' => $enabled,
        ]);
    }
}
