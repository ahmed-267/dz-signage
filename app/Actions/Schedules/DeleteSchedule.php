<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deletes a schedule. Draft, paused and archived schedules are safe to remove.
 * A live schedule must be paused or archived first, so nobody deletes content
 * out from under a playing screen by accident.
 */
class DeleteSchedule
{
    public function handle(Schedule $schedule): void
    {
        if ($schedule->status === ScheduleStatus::Active) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule is live. Pause or archive it before deleting it.',
            ]);
        }

        DB::transaction(function () use ($schedule) {
            $schedule->screens()->detach();
            $schedule->delete();
        });
    }
}
