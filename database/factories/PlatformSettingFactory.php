<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'platform_name',
            'value' => 'RMSignage',
            'updated_by' => null,
        ];
    }

    public function updatedBy(User $user): static
    {
        return $this->state(fn () => [
            'updated_by' => $user->id,
        ]);
    }
}
