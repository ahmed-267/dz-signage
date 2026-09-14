<?php

namespace App\Actions\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;

class SwitchWorkspace
{
    public function handle(User $user, Workspace $workspace): Workspace
    {
        if (! $user->belongsToWorkspace($workspace)) {
            throw ValidationException::withMessages([
                'workspace' => 'You do not belong to this workspace.',
            ]);
        }

        $user->forceFill([
            'current_workspace_id' => $workspace->id,
        ])->save();

        return $workspace;
    }
}
