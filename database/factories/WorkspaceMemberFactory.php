<?php

namespace Database\Factories;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkspaceMember>
 */
class WorkspaceMemberFactory extends Factory
{
    protected $model = WorkspaceMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'role' => WorkspaceRole::Viewer,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => WorkspaceRole::Owner]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => WorkspaceRole::Admin]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forWorkspace(Workspace $workspace): static
    {
        return $this->state(fn () => ['workspace_id' => $workspace->id]);
    }
}
