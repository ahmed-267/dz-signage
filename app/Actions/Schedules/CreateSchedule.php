<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Schedules\ScheduleDefaults;
use Illuminate\Support\Facades\DB;

/**
 * Creates a Draft schedule seeded with workspace defaults, then applies any
 * form fields through SaveSchedule so validation lives in one place.
 */
class CreateSchedule
{
    public function __construct(private readonly SaveSchedule $save) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, Workspace $workspace, array $data = []): Schedule
    {
        return DB::transaction(function () use ($user, $workspace, $data) {
            $schedule = Schedule::query()->create([
                'workspace_id' => $workspace->id,
                'name' => 'Untitled Schedule',
                'description' => null,
                'playlist_id' => null,
                'playlist_version_id' => null,
                'timezone' => $workspace->timezone,
                'start_date' => null,
                'end_date' => null,
                'start_time' => ScheduleDefaults::startTime(),
                'end_time' => ScheduleDefaults::endTime(),
                'days_of_week' => ScheduleDefaults::daysOfWeek(),
                'priority' => ScheduleDefaults::defaultPriority(),
                'status' => ScheduleStatus::Draft,
                'activated_at' => null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            if ($data !== []) {
                $schedule = $this->save->handle($user, $schedule, $data);
            }

            return $schedule->fresh(['playlist', 'playlistVersion', 'screens']) ?? $schedule;
        });
    }
}
