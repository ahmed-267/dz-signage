<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Stops a schedule from playing without losing its configuration.
 * `activated_at` is kept as activation history.
 */
class PauseSchedule
{
    public function handle(User $user, Schedule $schedule): Schedule
    {
        if ($schedule->status !== ScheduleStatus::Active) {
            throw ValidationException::withMessages([
                'schedule' => 'Only an active schedule can be paused.',
            ]);
        }

        $schedule->forceFill([
            'status' => ScheduleStatus::Paused,
            'updated_by' => $user->id,
        ])->save();

        return $schedule;
    }
}
