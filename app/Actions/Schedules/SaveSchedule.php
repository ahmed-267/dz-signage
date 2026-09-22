<?php

namespace App\Actions\Schedules;

use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Models\Playlist;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\User;
use App\Support\Schedules\ScheduleDays;
use App\Support\Schedules\ScheduleDefaults;
use App\Support\Schedules\ScheduleValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves schedule fields. Every key is optional, so partial draft saves work.
 *
 * Choosing a playlist always pins its current `published_version_id`. A later
 * playlist version does not reach an existing schedule until someone re-picks
 * the playlist here.
 */
class SaveSchedule
{
    public function __construct(private readonly ScheduleValidator $validator) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, Schedule $schedule, array $data): Schedule
    {
        if ($schedule->status === ScheduleStatus::Archived) {
            throw ValidationException::withMessages([
                'schedule' => 'Archived schedules cannot be edited. Duplicate it to make changes.',
            ]);
        }

        return DB::transaction(function () use ($user, $schedule, $data) {
            $attributes = ['updated_by' => $user->id];

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);

                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => 'Schedule name is required.',
                    ]);
                }

                $attributes['name'] = $name;
            }

            if (array_key_exists('description', $data)) {
                $description = $data['description'] !== null ? trim((string) $data['description']) : null;
                $attributes['description'] = $description === '' ? null : $description;
            }

            if (array_key_exists('playlist_id', $data)) {
                $attributes = [...$attributes, ...$this->playlistAttributes($schedule, $data['playlist_id'])];
            }

            if (array_key_exists('timezone', $data)) {
                $attributes['timezone'] = $this->timezone((string) $data['timezone']);
            }

            if (array_key_exists('priority', $data)) {
                $attributes['priority'] = $this->priority($data['priority']);
            }

            if (array_key_exists('days_of_week', $data)) {
                $days = $data['days_of_week'];
                $attributes['days_of_week'] = ScheduleDays::normalize(is_array($days) ? $days : []);
            }

            $attributes = [...$attributes, ...$this->timeAttributes($schedule, $data)];
            $attributes = [...$attributes, ...$this->dateAttributes($schedule, $data)];

            $schedule->forceFill($attributes)->save();

            if (array_key_exists('screen_ids', $data)) {
                $this->syncScreens($schedule, is_array($data['screen_ids']) ? $data['screen_ids'] : []);
            }

            $schedule = $schedule->fresh(['playlist', 'playlistVersion.items', 'screens']) ?? $schedule;

            // A live schedule must never be edited into an unplayable state.
            if ($schedule->status === ScheduleStatus::Active) {
                $this->validator->assertReadyForActivation($schedule);
            }

            return $schedule;
        });
    }

    /**
     * @return array<string, int|null>
     */
    private function playlistAttributes(Schedule $schedule, mixed $playlistId): array
    {
        if ($playlistId === null || $playlistId === '') {
            return ['playlist_id' => null, 'playlist_version_id' => null];
        }

        $playlist = Playlist::query()
            ->forWorkspace($schedule->workspace_id)
            ->whereKey((int) $playlistId)
            ->first();

        if ($playlist === null) {
            throw ValidationException::withMessages([
                'playlist_id' => 'That playlist is not available in this workspace.',
            ]);
        }

        if ($playlist->status === PlaylistStatus::Archived) {
            throw ValidationException::withMessages([
                'playlist_id' => "\"{$playlist->name}\" is archived and cannot be scheduled.",
            ]);
        }

        if ($playlist->published_version_id === null) {
            throw ValidationException::withMessages([
                'playlist_id' => "\"{$playlist->name}\" has no published version. Publish the playlist before scheduling it.",
            ]);
        }

        return [
            'playlist_id' => $playlist->id,
            'playlist_version_id' => $playlist->published_version_id,
        ];
    }

    private function timezone(string $timezone): string
    {
        $timezone = trim($timezone);

        if (! ScheduleValidator::isValidTimezone($timezone)) {
            throw ValidationException::withMessages([
                'timezone' => 'Choose a valid timezone for this schedule.',
            ]);
        }

        return $timezone;
    }

    private function priority(mixed $value): int
    {
        $priority = (int) $value;

        if (! ScheduleDefaults::isValidPriority($priority)) {
            $min = ScheduleDefaults::minPriority();
            $max = ScheduleDefaults::maxPriority();

            throw ValidationException::withMessages([
                'priority' => "Priority must be between {$min} and {$max}.",
            ]);
        }

        return $priority;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function timeAttributes(Schedule $schedule, array $data): array
    {
        $attributes = [];

        foreach (['start_time', 'end_time'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $time = ScheduleDefaults::normalizeTime((string) $data[$field]);

            if ($time === null) {
                throw ValidationException::withMessages([
                    $field => 'Enter a time as HH:MM.',
                ]);
            }

            $attributes[$field] = $time;
        }

        $start = $attributes['start_time'] ?? $schedule->start_time;
        $end = $attributes['end_time'] ?? $schedule->end_time;

        if ($attributes !== [] && Schedule::minutesFromTime($start) === Schedule::minutesFromTime($end)) {
            throw ValidationException::withMessages([
                'end_time' => 'Start and end time cannot be the same.',
            ]);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function dateAttributes(Schedule $schedule, array $data): array
    {
        $attributes = [];

        foreach (['start_date', 'end_date'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];

            if ($value === null || $value === '') {
                $attributes[$field] = null;

                continue;
            }

            try {
                $attributes[$field] = Carbon::parse((string) $value)->format('Y-m-d');
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    $field => 'Enter a date as YYYY-MM-DD.',
                ]);
            }
        }

        if ($attributes === []) {
            return $attributes;
        }

        $start = array_key_exists('start_date', $attributes)
            ? $attributes['start_date']
            : $schedule->start_date?->format('Y-m-d');

        $end = array_key_exists('end_date', $attributes)
            ? $attributes['end_date']
            : $schedule->end_date?->format('Y-m-d');

        if ($start !== null && $end !== null && $end < $start) {
            throw ValidationException::withMessages([
                'end_date' => 'The end date must be on or after the start date.',
            ]);
        }

        return $attributes;
    }

    /**
     * @param  array<int, mixed>  $screenIds
     */
    private function syncScreens(Schedule $schedule, array $screenIds): void
    {
        $ids = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $screenIds)));

        if ($ids === []) {
            $schedule->screens()->sync([]);

            return;
        }

        $screens = Screen::query()
            ->forWorkspace($schedule->workspace_id)
            ->whereIn('id', $ids)
            ->pluck('id');

        if ($screens->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'screen_ids' => 'One or more screens are not available in this workspace.',
            ]);
        }

        $schedule->screens()->sync($screens->all());
    }
}
