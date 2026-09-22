<?php

namespace App\Support\Schedules;

/**
 * Single source of truth for `config/schedules.php` values.
 */
final class ScheduleDefaults
{
    public static function defaultPriority(): int
    {
        return (int) config('schedules.default_priority', 5);
    }

    public static function minPriority(): int
    {
        return max(1, (int) config('schedules.min_priority', 1));
    }

    public static function maxPriority(): int
    {
        return (int) config('schedules.max_priority', 10);
    }

    public static function isValidPriority(int $priority): bool
    {
        return $priority >= self::minPriority() && $priority <= self::maxPriority();
    }

    public static function startTime(): string
    {
        return self::normalizeTime((string) config('schedules.default_start_time', '09:00')) ?? '09:00:00';
    }

    public static function endTime(): string
    {
        return self::normalizeTime((string) config('schedules.default_end_time', '17:00')) ?? '17:00:00';
    }

    /**
     * @return list<int>
     */
    public static function daysOfWeek(): array
    {
        $days = config('schedules.default_days_of_week', ScheduleDays::all());

        if (! is_array($days)) {
            return ScheduleDays::all();
        }

        return ScheduleDays::normalize($days);
    }

    /**
     * `HH:MM` / `HH:MM:SS` to the stored `HH:MM:00` form, or null when the
     * value is not a valid time of day.
     */
    public static function normalizeTime(string $time): ?string
    {
        $time = trim($time);

        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $time, $matches) !== 1) {
            return null;
        }

        return $matches[1].':'.$matches[2].':00';
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(): array
    {
        return [
            'default_priority' => self::defaultPriority(),
            'min_priority' => self::minPriority(),
            'max_priority' => self::maxPriority(),
            'default_start_time' => self::startTime(),
            'default_end_time' => self::endTime(),
            'default_days_of_week' => self::daysOfWeek(),
            'day_labels' => ScheduleDays::labels(),
            'day_short_labels' => ScheduleDays::shortLabels(),
            'weekdays' => ScheduleDays::weekdays(),
            'weekends' => ScheduleDays::weekends(),
        ];
    }
}
