<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Copies a schedule as a Draft, including its pinned playlist version and
 * screen targets. The copy is never live until someone activates it.
 */
class DuplicateSchedule
{
    public function handle(User $user, Schedule $schedule): Schedule
    {
        return DB::transaction(function () use ($user, $schedule) {
            $copy = Schedule::query()->create([
                'workspace_id' => $schedule->workspace_id,
                'name' => $schedule->name.' Copy',
                'description' => $schedule->description,
                'playlist_id' => $schedule->playlist_id,
                'playlist_version_id' => $schedule->playlist_version_id,
                'timezone' => $schedule->timezone,
                'start_date' => $schedule->start_date?->format('Y-m-d'),
                'end_date' => $schedule->end_date?->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'days_of_week' => $schedule->days(),
                'priority' => $schedule->priority,
                'status' => ScheduleStatus::Draft,
                'activated_at' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $copy->screens()->sync($schedule->screens()->pluck('screens.id')->all());

            return $copy->fresh(['playlist', 'playlistVersion', 'screens']) ?? $copy;
        });
    }
}
