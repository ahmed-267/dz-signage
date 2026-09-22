<?php

namespace Database\Factories;

use App\Models\SupportRequest;
use App\Models\SupportRequestNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequestNote>
 */
class SupportRequestNoteFactory extends Factory
{
    protected $model = SupportRequestNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_request_id' => SupportRequest::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => true,
        ];
    }
}
