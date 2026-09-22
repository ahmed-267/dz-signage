<?php

namespace App\Actions\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\Screen;
use App\Support\Schedules\ScheduleDays;
use App\Support\Schedules\ScheduleDefaults;
use Illuminate\Support\Carbon;

/**
 * Finds active schedules that overlap a schedule on shared screens.
 *
 * Overlaps are a warning, never a block: priority exists precisely so two
 * schedules may cover the same moment. The report tells the user which one
 * would win.
 *
 * Windows are compared on a UTC minute-of-week timeline so schedules in
 * different timezones can be compared at all. The current UTC offset of each
 * timezone is used, which is exact for same-timezone comparisons and close
 * enough across a DST boundary for a warning.
 */
class DetectScheduleConflicts
{
    private const MINUTES_PER_WEEK = 7 * 24 * 60;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(Schedule $schedule): array
    {
        $screenIds = $schedule->relationLoaded('screens')
            ? $schedule->screens->pluck('id')->all()
            : $schedule->screens()->pluck('screens.id')->all();

        if ($screenIds === [] || $schedule->days() === []) {
            return [];
        }

        $candidates = Schedule::query()
            ->where('workspace_id', $schedule->workspace_id)
            ->where('status', ScheduleStatus::Active)
            ->when(
                $schedule->exists,
                fn ($query) => $query->whereKeyNot($schedule->id),
            )
            ->whereHas('screens', fn ($query) => $query->whereIn('screens.id', $screenIds))
            ->with(['playlist:id,name', 'screens:id,name'])
            ->orderByPrecedence()
            ->get();

        $ownIntervals = $this->intervals($schedule);
        $conflicts = [];

        foreach ($candidates as $other) {
            $sharedScreens = $other->screens->whereIn('id', $screenIds)->values();

            if ($sharedScreens->isEmpty()
                || ! $this->datesOverlap($schedule, $other)
                || ! $this->windowsOverlap($ownIntervals, $this->intervals($other))
            ) {
                continue;
            }

            $conflicts[] = [
                'id' => $other->id,
                'name' => $other->name,
                'status' => $other->displayStatus(),
                'status_label' => $other->displayStatusLabel(),
                'priority' => $other->priority,
                'playlist_name' => $other->playlist?->name,
                'timezone' => $other->timezone,
                'start_time' => $other->start_time,
                'end_time' => $other->end_time,
                'days_of_week' => $other->days(),
                'day_labels' => array_map(
                    fn (int $day): string => ScheduleDays::shortLabels()[$day],
                    $other->days(),
                ),
                'screen_ids' => $sharedScreens->pluck('id')->all(),
                'screen_names' => $sharedScreens->pluck('name')->all(),
                // True when the overlapping schedule would win the overlap.
                'other_wins' => $other->outranks($schedule),
                'same_priority' => (int) $other->priority === (int) $schedule->priority,
            ];
        }

        return $conflicts;
    }

    /**
     * Preview overlaps for an unsaved / dirty schedule draft.
     *
     * @param  list<int>  $screenIds
     * @param  array<string, mixed>  $attributes
     * @return list<array<string, mixed>>
     */
    public function forDraft(int $workspaceId, array $attributes, array $screenIds, ?int $exceptId = null): array
    {
        $schedule = new Schedule([
            'workspace_id' => $workspaceId,
            'name' => (string) ($attributes['name'] ?? 'Draft'),
            'timezone' => (string) ($attributes['timezone'] ?? 'UTC'),
            'start_date' => $attributes['start_date'] ?? null,
            'end_date' => $attributes['end_date'] ?? null,
            'start_time' => (string) ($attributes['start_time'] ?? '09:00:00'),
            'end_time' => (string) ($attributes['end_time'] ?? '17:00:00'),
            'days_of_week' => ScheduleDays::normalize($attributes['days_of_week'] ?? []),
            'priority' => (int) ($attributes['priority'] ?? ScheduleDefaults::defaultPriority()),
            'status' => ScheduleStatus::Active,
        ]);

        if ($exceptId !== null) {
            $schedule->id = $exceptId;
            $schedule->exists = true;
        }

        $screens = Screen::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $screenIds)
            ->get(['id', 'name']);

        $schedule->setRelation('screens', $screens);

        return $this->handle($schedule);
    }

    /**
     * Half-open `[start, end)` intervals on a UTC minute-of-week timeline.
     * Intervals that run past the end of the week are split in two.
     *
     * @return list<array{int, int}>
     */
    private function intervals(Schedule $schedule): array
    {
        $offsetMinutes = intdiv(Carbon::now($schedule->timezone)->getOffset(), 60);
        $duration = $schedule->durationMinutes();
        $intervals = [];

        foreach ($schedule->days() as $day) {
            $localStart = (($day - 1) * 1440) + $schedule->startMinutes();
            $start = (($localStart - $offsetMinutes) % self::MINUTES_PER_WEEK + self::MINUTES_PER_WEEK)
                % self::MINUTES_PER_WEEK;
            $end = $start + $duration;

            if ($end <= self::MINUTES_PER_WEEK) {
                $intervals[] = [$start, $end];

                continue;
            }

            $intervals[] = [$start, self::MINUTES_PER_WEEK];
            $intervals[] = [0, $end - self::MINUTES_PER_WEEK];
        }

        return $intervals;
    }

    /**
     * @param  list<array{int, int}>  $a
     * @param  list<array{int, int}>  $b
     */
    private function windowsOverlap(array $a, array $b): bool
    {
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                if ($aStart < $bEnd && $bStart < $aEnd) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Open-ended date ranges (null start or end) run forever in that direction.
     */
    private function datesOverlap(Schedule $a, Schedule $b): bool
    {
        $aStart = $a->start_date?->format('Y-m-d');
        $aEnd = $a->end_date?->format('Y-m-d');
        $bStart = $b->start_date?->format('Y-m-d');
        $bEnd = $b->end_date?->format('Y-m-d');

        if ($aStart !== null && $bEnd !== null && $aStart > $bEnd) {
            return false;
        }

        if ($bStart !== null && $aEnd !== null && $bStart > $aEnd) {
            return false;
        }

        return true;
    }
}
