<?php

namespace App\Support\Schedules;

use App\Models\Schedule;
use Illuminate\Support\Carbon;

/**
 * One concrete occurrence of a Schedule, resolved to absolute UTC instants.
 *
 * `anchorDate` is the local date whose weekday and date range made the
 * occurrence eligible. For a window that crosses midnight that is the evening
 * date, so the early-morning tail belongs to the previous day.
 */
final class ScheduleWindow
{
    public function __construct(
        public readonly Schedule $schedule,
        public readonly Carbon $startsAt,
        public readonly Carbon $endsAt,
        public readonly Carbon $anchorDate,
    ) {}

    /**
     * Stable identifier for the occurrence, used in player version labels so a
     * changeover is visible to polling players.
     */
    public function key(): string
    {
        return $this->startsAt->copy()->utc()->format('Ymd\THis\Z');
    }

    public function startsAtUtc(): Carbon
    {
        return $this->startsAt->copy()->utc();
    }

    public function endsAtUtc(): Carbon
    {
        return $this->endsAt->copy()->utc();
    }
}
