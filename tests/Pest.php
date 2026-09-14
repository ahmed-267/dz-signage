<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function attachWorkspace(User $user, ?Workspace $workspace = null, WorkspaceRole $role = WorkspaceRole::Owner): Workspace
{
    $workspace ??= Workspace::factory()->create();

    WorkspaceMember::query()->firstOrCreate(
        [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ],
        [
            'role' => $role,
        ],
    );

    $user->forceFill([
        'current_workspace_id' => $workspace->id,
    ])->save();

    return $workspace;
}
