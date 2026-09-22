<?php

namespace Database\Factories;

use App\Models\Screen;
use App\Models\ScreenDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScreenDevice>
 */
class ScreenDeviceFactory extends Factory
{
    protected $model = ScreenDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = Str::random(64);

        return [
            'screen_id' => Screen::factory(),
            'device_identifier' => 'dev_'.Str::lower((string) Str::ulid()),
            'device_token_hash' => ScreenDevice::hashToken($token),
            'device_name' => fake()->optional()->words(2, true),
            'platform_meta' => null,
            'paired_at' => now(),
            'revoked_at' => null,
            'last_seen_at' => null,
        ];
    }

    public function forScreen(Screen $screen): static
    {
        return $this->state(fn () => [
            'screen_id' => $screen->id,
        ]);
    }

    public function withToken(string $token): static
    {
        return $this->state(fn () => [
            'device_token_hash' => ScreenDevice::hashToken($token),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }

    public function seenRecently(): static
    {
        return $this->state(fn () => [
            'last_seen_at' => now()->subSeconds(30),
        ]);
    }
}
