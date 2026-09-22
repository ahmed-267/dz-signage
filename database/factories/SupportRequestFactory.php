<?php

namespace Database\Factories;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestPriority;
use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    protected $model = SupportRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'subject' => fake()->sentence(4),
            'category' => SupportRequestCategory::General,
            'message' => fake()->paragraph(),
            'priority' => SupportRequestPriority::Medium,
            'status' => SupportRequestStatus::Open,
            'admin_notes' => null,
            'assigned_to' => null,
            'resolved_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => SupportRequestStatus::Open,
            'resolved_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => SupportRequestStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }
}
