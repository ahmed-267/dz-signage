<?php

namespace App\Policies;

use App\Models\ScreenDesign;
use App\Models\User;

class ScreenDesignPolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canViewTemplates() ?? false);
    }

    public function view(User $user, ScreenDesign $screenDesign): bool
    {
        return $this->inCurrentWorkspace($user, $screenDesign);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ScreenDesign $screenDesign): bool
    {
        return $this->inCurrentWorkspace($user, $screenDesign)
            && $this->canManage($user);
    }

    public function delete(User $user, ScreenDesign $screenDesign): bool
    {
        return $this->update($user, $screenDesign);
    }

    public function publish(User $user, ScreenDesign $screenDesign): bool
    {
        return $this->update($user, $screenDesign);
    }

    public function duplicate(User $user, ScreenDesign $screenDesign): bool
    {
        return $this->update($user, $screenDesign);
    }

    private function canManage(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManageScreenDesigns() ?? false);
    }

    private function inCurrentWorkspace(User $user, ScreenDesign $screenDesign): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $screenDesign->workspace_id === (int) $workspace->id;
    }
}
