<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Retires a schedule. Archiving is the preferred way to retire a live
 * schedule, because it keeps the record for reference while stopping playback.
 */
class ArchiveSchedule
{
    public function handle(User $user, Schedule $schedule): Schedule
    {
        if ($schedule->status === ScheduleStatus::Archived) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule is already archived.',
            ]);
        }

        $schedule->forceFill([
            'status' => ScheduleStatus::Archived,
            'updated_by' => $user->id,
        ])->save();

        return $schedule;
    }
}
