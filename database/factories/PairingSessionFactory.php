<?php

namespace Database\Factories;

use App\Models\PairingSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PairingSession>
 */
class PairingSessionFactory extends Factory
{
    protected $model = PairingSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = 'DZ-ABCD';

        return [
            'public_id' => (string) Str::ulid(),
            'code_hash' => PairingSession::hashCode($code),
            'expires_at' => now()->addMinutes(10),
            'claimed_at' => null,
            'claimed_by' => null,
            'screen_id' => null,
            'device_meta' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'pending_device_token_ciphertext' => null,
        ];
    }

    public function withCode(string $code): static
    {
        return $this->state(fn () => [
            'code_hash' => PairingSession::hashCode($code),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function claimed(): static
    {
        return $this->state(fn () => [
            'claimed_at' => now(),
        ]);
    }
}
