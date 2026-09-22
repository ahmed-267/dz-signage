<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $this->inCurrentWorkspace($user, $deployment);
    }

    public function create(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canPublishContent() ?? false);
    }

    public function publish(User $user): bool
    {
        return $this->create($user);
    }

    public function republish(User $user, Deployment $deployment): bool
    {
        return $this->inCurrentWorkspace($user, $deployment)
            && $this->create($user);
    }

    private function inCurrentWorkspace(User $user, Deployment $deployment): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $deployment->workspace_id === (int) $workspace->id;
    }
}
