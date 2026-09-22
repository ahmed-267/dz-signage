<?php

namespace App\Policies;

use App\Models\BrandKit;
use App\Models\User;

class BrandKitPolicy
{
    public function view(User $user, BrandKit $brandKit): bool
    {
        return $this->sameCurrentWorkspace($user, $brandKit)
            && ($user->roleIn($user->currentWorkspace)?->canViewBrandKit() ?? false);
    }

    public function update(User $user, BrandKit $brandKit): bool
    {
        return $this->sameCurrentWorkspace($user, $brandKit)
            && ($user->roleIn($user->currentWorkspace)?->canManageBrandKit() ?? false);
    }

    private function sameCurrentWorkspace(User $user, BrandKit $brandKit): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $brandKit->workspace_id === (int) $workspace->id;
    }
}
