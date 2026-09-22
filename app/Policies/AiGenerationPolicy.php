<?php

namespace App\Policies;

use App\Models\AiGeneration;
use App\Models\User;

class AiGenerationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canUseAi($user);
    }

    public function view(User $user, AiGeneration $generation): bool
    {
        return $this->sameCurrentWorkspace($user, $generation) && $this->canUseAi($user);
    }

    public function create(User $user): bool
    {
        return $this->canUseAi($user);
    }

    public function saveMedia(User $user, AiGeneration $generation): bool
    {
        $workspace = $user->currentWorkspace;

        return $this->sameCurrentWorkspace($user, $generation)
            && ($user->roleIn($workspace)?->canManageMedia() ?? false);
    }

    private function canUseAi(User $user): bool
    {
        $workspace = $user->currentWorkspace;
        if ($workspace === null || ! $user->belongsToWorkspace($workspace)) {
            return false;
        }

        $role = $user->roleIn($workspace);

        return ($role?->canManageMedia() ?? false)
            || ($role?->canManageScreenDesigns() ?? false);
    }

    private function sameCurrentWorkspace(User $user, AiGeneration $generation): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $generation->workspace_id === (int) $workspace->id;
    }
}
