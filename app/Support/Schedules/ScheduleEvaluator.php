<?php

namespace App\Support\Schedules;

use App\Enums\ScheduleStatus;
use App\Models\Schedule;
use App\Models\Screen;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The only place that decides whether a Schedule is playing.
 *
 * Rules implemented here (do not re-implement them in controllers or React):
 *
 * - Windows are wall-clock in the schedule's own IANA timezone.
 * - A window is inclusive of its start minute and exclusive of its end minute
 *   (`>= start && < end`), so a Breakfast 07:00–12:00 window hands over to a
 *   Lunch 12:00–15:00 window cleanly at 12:00 with no double match.
 * - A window whose end is earlier than its start crosses midnight. The evening
 *   portion is anchored to today's local date; the early-morning tail is
 *   anchored to *yesterday's* local date, so day-of-week and date-range
 *   eligibility follow the day the window opened.
 * - Precedence: highest `priority` wins; ties break on the most recent
 *   `activated_at`, then on the higher `id`. See `Schedule::outranks()` and
 *   `Schedule::scopeOrderByPrecedence()`.
 * - Derived "Ended" (active, past its `end_date`) simply fails the date-range
 *   check and therefore never matches.
 *
 * `$at` is resolved once per public entry point so a single evaluation can
 * never straddle two instants.
 */
class ScheduleEvaluator
{
    /**
     * How far ahead `nextWindow()` looks for the next occurrence.
     */
    public const LOOKAHEAD_DAYS = 14;

    /**
     * Whether the schedule is playing at `$at`. Only Active schedules play:
     * draft, paused and archived schedules never match.
     */
    public function matches(Schedule $schedule, CarbonInterface $at): bool
    {
        if ($schedule->status !== ScheduleStatus::Active) {
            return false;
        }

        return $this->window($schedule, $at) !== null;
    }

    /**
     * The occurrence covering `$at`, ignoring status. This is the pure timing
     * calculation — callers that care about "is it live" want `matches()`.
     */
    public function window(Schedule $schedule, ?CarbonInterface $at = null): ?ScheduleWindow
    {
        $at ??= now();

        if (! $this->hasUsableWindow($schedule)) {
            return null;
        }

        $local = $schedule->localNow($at);
        $minutes = ($local->hour * 60) + $local->minute;
        $start = $schedule->startMinutes();
        $end = $schedule->endMinutes();
        $today = $local->copy()->startOfDay();

        if (! $schedule->crossesMidnight()) {
            if ($minutes < $start || $minutes >= $end) {
                return null;
            }

            $anchor = $today;
        } elseif ($minutes >= $start) {
            $anchor = $today;
        } elseif ($minutes < $end) {
            // Early-morning tail: eligibility belongs to the day it opened.
            $anchor = $today->copy()->subDay();
        } else {
            return null;
        }

        if (! $this->isEligibleDate($schedule, $anchor)) {
            return null;
        }

        return $this->windowForAnchor($schedule, $anchor);
    }

    /**
     * The next occurrence starting strictly after `$at`, or null when the
     * schedule has none within the lookahead horizon.
     */
    public function nextWindow(Schedule $schedule, ?CarbonInterface $at = null): ?ScheduleWindow
    {
        $at ??= now();

        if (! $this->hasUsableWindow($schedule)) {
            return null;
        }

        $today = $schedule->localNow($at)->startOfDay();

        for ($offset = 0; $offset <= self::LOOKAHEAD_DAYS; $offset++) {
            $anchor = $today->copy()->addDays($offset);

            if (! $this->isEligibleDate($schedule, $anchor)) {
                continue;
            }

            $window = $this->windowForAnchor($schedule, $anchor);

            if ($window->startsAt->gt($at)) {
                return $window;
            }
        }

        return null;
    }

    /**
     * The occurrence that opens on a given local calendar date, or null when
     * that date is not eligible. Used to lay schedules out on a week grid.
     */
    public function occurrenceOn(Schedule $schedule, CarbonInterface|string $localDate): ?ScheduleWindow
    {
        if (! $this->hasUsableWindow($schedule)) {
            return null;
        }

        $date = $localDate instanceof CarbonInterface ? $localDate->format('Y-m-d') : $localDate;
        $anchor = Carbon::parse($date, $schedule->timezone)->startOfDay();

        if (! $this->isEligibleDate($schedule, $anchor)) {
            return null;
        }

        return $this->windowForAnchor($schedule, $anchor);
    }

    /**
     * The upcoming occurrences of one schedule, for preview surfaces.
     *
     * @return list<ScheduleWindow>
     */
    public function upcomingWindows(Schedule $schedule, ?CarbonInterface $at = null, int $limit = 5): array
    {
        $at ??= now();
        $windows = [];
        $cursor = $at;

        for ($i = 0; $i < $limit; $i++) {
            $window = $this->nextWindow($schedule, $cursor);

            if ($window === null) {
                break;
            }

            $windows[] = $window;
            $cursor = $window->startsAt;
        }

        return $windows;
    }

    /**
     * The winning Schedule for a Screen at `$at`, or null when none matches.
     */
    public function resolveForScreen(Screen $screen, ?CarbonInterface $at = null): ?Schedule
    {
        return $this->resolveWindowForScreen($screen, $at)?->schedule;
    }

    /**
     * Same resolution as `resolveForScreen()`, keeping the matched occurrence
     * so callers can report validity without recomputing it.
     */
    public function resolveWindowForScreen(Screen $screen, ?CarbonInterface $at = null): ?ScheduleWindow
    {
        $at ??= now();

        // Candidates arrive in precedence order, so the first match wins.
        foreach ($this->activeSchedulesForScreen($screen) as $schedule) {
            $window = $this->window($schedule, $at);

            if ($window !== null) {
                return $window;
            }
        }

        return null;
    }

    /**
     * The next occurrence across every active schedule targeting the screen.
     *
     * @return array{schedule: Schedule, window: ScheduleWindow, starts_at: Carbon, ends_at: Carbon}|null
     */
    public function findNextForScreen(Screen $screen, ?CarbonInterface $at = null): ?array
    {
        $at ??= now();
        $best = null;

        foreach ($this->activeSchedulesForScreen($screen) as $schedule) {
            $window = $this->nextWindow($schedule, $at);

            if ($window === null) {
                continue;
            }

            if ($best === null
                || $window->startsAt->lt($best->startsAt)
                || ($window->startsAt->eq($best->startsAt) && $window->schedule->outranks($best->schedule))
            ) {
                $best = $window;
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'schedule' => $best->schedule,
            'window' => $best,
            'starts_at' => $best->startsAtUtc(),
            'ends_at' => $best->endsAtUtc(),
        ];
    }

    /**
     * Active schedules targeting the screen, in precedence order.
     *
     * @return Collection<int, Schedule>
     */
    public function activeSchedulesForScreen(Screen $screen): Collection
    {
        return Schedule::query()
            ->where('workspace_id', $screen->workspace_id)
            ->where('status', ScheduleStatus::Active)
            ->whereNotNull('playlist_version_id')
            ->whereHas('screens', fn ($query) => $query->whereKey($screen->id))
            ->with(['playlist:id,name,orientation,status', 'playlistVersion:id,playlist_id,version_number,published_at'])
            ->orderByPrecedence()
            ->get();
    }

    /**
     * Weekday and date-range eligibility for the local day a window opens on.
     */
    private function isEligibleDate(Schedule $schedule, Carbon $anchorDate): bool
    {
        if (! in_array($anchorDate->isoWeekday(), $schedule->days(), true)) {
            return false;
        }

        // Compare calendar dates, never instants: the schedule's local date and
        // the stored date columns live in different timezones.
        $anchor = $anchorDate->format('Y-m-d');

        if ($schedule->start_date !== null && $anchor < $schedule->start_date->format('Y-m-d')) {
            return false;
        }

        if ($schedule->end_date !== null && $anchor > $schedule->end_date->format('Y-m-d')) {
            return false;
        }

        return true;
    }

    private function windowForAnchor(Schedule $schedule, Carbon $anchorDate): ScheduleWindow
    {
        $startsAt = $this->localTime($schedule, $anchorDate, $schedule->startMinutes());
        $endDate = $schedule->crossesMidnight() ? $anchorDate->copy()->addDay() : $anchorDate;
        $endsAt = $this->localTime($schedule, $endDate, $schedule->endMinutes());

        return new ScheduleWindow($schedule, $startsAt, $endsAt, $anchorDate->copy());
    }

    /**
     * Wall-clock time on a local date, so DST shifts move the instant rather
     * than the advertised clock time.
     */
    private function localTime(Schedule $schedule, Carbon $date, int $minutes): Carbon
    {
        $clock = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);

        return Carbon::parse($date->format('Y-m-d').' '.$clock, $schedule->timezone);
    }

    /**
     * A zero-length window (start == end) can never play, and a schedule with
     * no selected weekdays never recurs.
     */
    private function hasUsableWindow(Schedule $schedule): bool
    {
        return $schedule->days() !== []
            && $schedule->startMinutes() !== $schedule->endMinutes();
    }
}
