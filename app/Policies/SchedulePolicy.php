<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;

class SchedulePolicy
{
    public function viewAny(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace);
    }

    public function view(User $user, Schedule $schedule): bool
    {
        return $this->inCurrentWorkspace($user, $schedule);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->inCurrentWorkspace($user, $schedule)
            && $this->canManage($user);
    }

    public function activate(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function pause(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function duplicate(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function archive(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    private function canManage(User $user): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && ($user->roleIn($workspace)?->canManageSchedules() ?? false);
    }

    private function inCurrentWorkspace(User $user, Schedule $schedule): bool
    {
        $workspace = $user->currentWorkspace;

        return $workspace !== null
            && $user->belongsToWorkspace($workspace)
            && (int) $schedule->workspace_id === (int) $workspace->id;
    }
}
