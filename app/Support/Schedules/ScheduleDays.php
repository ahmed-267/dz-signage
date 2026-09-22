<?php

namespace App\Support\Schedules;

/**
 * Single source for weekday values (ISO-8601): 1 = Monday … 7 = Sunday.
 */
final class ScheduleDays
{
    public const MONDAY = 1;

    public const TUESDAY = 2;

    public const WEDNESDAY = 3;

    public const THURSDAY = 4;

    public const FRIDAY = 5;

    public const SATURDAY = 6;

    public const SUNDAY = 7;

    /**
     * @return list<int>
     */
    public static function all(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
            self::SATURDAY,
            self::SUNDAY,
        ];
    }

    /**
     * @return list<int>
     */
    public static function weekdays(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
        ];
    }

    /**
     * @return list<int>
     */
    public static function weekends(): array
    {
        return [self::SATURDAY, self::SUNDAY];
    }

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::MONDAY => 'Monday',
            self::TUESDAY => 'Tuesday',
            self::WEDNESDAY => 'Wednesday',
            self::THURSDAY => 'Thursday',
            self::FRIDAY => 'Friday',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function shortLabels(): array
    {
        return [
            self::MONDAY => 'Mon',
            self::TUESDAY => 'Tue',
            self::WEDNESDAY => 'Wed',
            self::THURSDAY => 'Thu',
            self::FRIDAY => 'Fri',
            self::SATURDAY => 'Sat',
            self::SUNDAY => 'Sun',
        ];
    }

    /**
     * Accepts whatever a request, a config file or a JSON column hands over,
     * and returns sorted, unique, valid ISO weekdays.
     *
     * @param  array<array-key, mixed>  $days
     * @return list<int>
     */
    public static function normalize(array $days): array
    {
        $allowed = self::all();
        $normalized = [];

        foreach ($days as $day) {
            $value = (int) $day;
            if (in_array($value, $allowed, true)) {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    public static function isValid(int $day): bool
    {
        return in_array($day, self::all(), true);
    }
}
