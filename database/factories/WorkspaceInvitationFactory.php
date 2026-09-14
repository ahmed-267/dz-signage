<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceInvitation>
 */
class WorkspaceInvitationFactory extends Factory
{
    protected $model = WorkspaceInvitation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => WorkspaceRole::Viewer,
            'token_hash' => WorkspaceInvitation::hashToken(WorkspaceInvitation::generatePlainToken()),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'accepted_at' => now(),
        ]);
    }
}
