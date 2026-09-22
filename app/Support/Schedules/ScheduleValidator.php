<?php

namespace App\Support\Schedules;

use App\Enums\PlaylistStatus;
use App\Models\Schedule;
use Illuminate\Validation\ValidationException;

/**
 * Single source for "is this schedule complete enough to go live".
 *
 * Used by ActivateSchedule, and again by SaveSchedule when editing a schedule
 * that is already active, so a live schedule can never be edited into an
 * unplayable state.
 */
class ScheduleValidator
{
    public static function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * @throws ValidationException
     */
    public function assertReadyForActivation(Schedule $schedule): void
    {
        $this->assertTiming($schedule);
        $this->assertContent($schedule);
        $this->assertScreens($schedule);
    }

    private function assertTiming(Schedule $schedule): void
    {
        if (! self::isValidTimezone($schedule->timezone)) {
            throw ValidationException::withMessages([
                'timezone' => 'Choose a valid timezone for this schedule.',
            ]);
        }

        if ($schedule->days() === []) {
            throw ValidationException::withMessages([
                'days_of_week' => 'Select at least one day of the week.',
            ]);
        }

        if ($schedule->startMinutes() === $schedule->endMinutes()) {
            throw ValidationException::withMessages([
                'end_time' => 'Start and end time cannot be the same.',
            ]);
        }

        if (
            $schedule->start_date !== null
            && $schedule->end_date !== null
            && $schedule->end_date->format('Y-m-d') < $schedule->start_date->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        if (
            $schedule->end_date !== null
            && $schedule->end_date->format('Y-m-d') < $schedule->localNow()->format('Y-m-d')
        ) {
            throw ValidationException::withMessages([
                'end_date' => 'This schedule has already ended. Extend the end date before activating it.',
            ]);
        }

        $min = ScheduleDefaults::minPriority();
        $max = ScheduleDefaults::maxPriority();

        if ($schedule->priority < $min || $schedule->priority > $max) {
            throw ValidationException::withMessages([
                'priority' => "Priority must be between {$min} and {$max}.",
            ]);
        }
    }

    private function assertContent(Schedule $schedule): void
    {
        $schedule->loadMissing(['playlist', 'playlistVersion.items']);
        $playlist = $schedule->playlist;
        $version = $schedule->playlistVersion;

        if ($playlist === null || $version === null) {
            throw ValidationException::withMessages([
                'playlist_id' => 'Choose a published playlist for this schedule.',
            ]);
        }

        if ((int) $playlist->workspace_id !== (int) $schedule->workspace_id) {
            throw ValidationException::withMessages([
                'playlist_id' => 'That playlist belongs to another workspace.',
            ]);
        }

        if ((int) $version->playlist_id !== (int) $playlist->id) {
            throw ValidationException::withMessages([
                'playlist_id' => 'This schedule is pinned to a version of a different playlist.',
            ]);
        }

        if (! $version->isPublished()) {
            throw ValidationException::withMessages([
                'playlist_id' => "\"{$playlist->name}\" is pinned to an unpublished version. Publish the playlist first.",
            ]);
        }

        if ($playlist->status === PlaylistStatus::Archived) {
            throw ValidationException::withMessages([
                'playlist_id' => "\"{$playlist->name}\" is archived and cannot be scheduled.",
            ]);
        }

        if (! $version->items->contains(fn ($item) => $item->is_active)) {
            throw ValidationException::withMessages([
                'playlist_id' => "\"{$playlist->name}\" has no active items to play.",
            ]);
        }
    }

    private function assertScreens(Schedule $schedule): void
    {
        $schedule->loadMissing('screens');
        $screens = $schedule->screens;

        if ($screens->isEmpty()) {
            throw ValidationException::withMessages([
                'screen_ids' => 'Select at least one screen for this schedule.',
            ]);
        }

        foreach ($screens as $screen) {
            if ((int) $screen->workspace_id !== (int) $schedule->workspace_id) {
                throw ValidationException::withMessages([
                    'screen_ids' => 'One or more screens belong to another workspace.',
                ]);
            }
        }
    }
}
