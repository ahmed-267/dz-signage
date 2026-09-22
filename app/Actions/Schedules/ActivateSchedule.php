<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Support\Schedules\ScheduleValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes a schedule live. `activated_at` is refreshed on every activation
 * because it is the tie-break for equal priorities: the most recently
 * activated schedule wins.
 */
class ActivateSchedule
{
    public function __construct(private readonly ScheduleValidator $validator) {}

    public function handle(User $user, Schedule $schedule): Schedule
    {
        if ($schedule->status === ScheduleStatus::Active) {
            throw ValidationException::withMessages([
                'schedule' => 'This schedule is already active.',
            ]);
        }

        if ($schedule->status === ScheduleStatus::Archived) {
            throw ValidationException::withMessages([
                'schedule' => 'Archived schedules cannot be activated. Duplicate it instead.',
            ]);
        }

        return DB::transaction(function () use ($user, $schedule) {
            $schedule->loadMissing(['playlist', 'playlistVersion.items', 'screens']);

            $this->validator->assertReadyForActivation($schedule);

            $schedule->forceFill([
                'status' => ScheduleStatus::Active,
                'activated_at' => now(),
                'updated_by' => $user->id,
            ])->save();

            return $schedule->fresh(['playlist', 'playlistVersion', 'screens']) ?? $schedule;
        });
    }
}
