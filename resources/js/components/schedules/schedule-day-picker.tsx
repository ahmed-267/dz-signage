import {
    dayShortLabel,
    ISO_DAYS,
} from '@/components/schedules/schedule-format';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { ScheduleConfig, ScheduleDayLabels } from '@/types/schedule';

type ScheduleDayPickerProps = {
    /** Selected ISO weekdays: 1 = Monday … 7 = Sunday. */
    value: number[];
    onChange: (days: number[]) => void;
    labels: ScheduleDayLabels;
    shortLabels: ScheduleDayLabels;
    config: ScheduleConfig;
    disabled?: boolean;
};

function sameDays(a: number[], b: number[]): boolean {
    if (a.length !== b.length) {
        return false;
    }

    const sortedB = [...b].sort((x, y) => x - y);
    return [...a]
        .sort((x, y) => x - y)
        .every((day, index) => day === sortedB[index]);
}

/**
 * Day-of-week toggles plus the Every day / Weekdays / Weekends shortcuts.
 * Each day is a real button, so the whole picker is keyboard reachable and
 * reports its state through `aria-pressed`.
 */
export function ScheduleDayPicker({
    value,
    onChange,
    labels,
    shortLabels,
    config,
    disabled = false,
}: ScheduleDayPickerProps) {
    const shortcuts = [
        { label: 'Every day', days: [...ISO_DAYS] as number[] },
        { label: 'Weekdays', days: config.weekdays },
        { label: 'Weekends', days: config.weekends },
    ];

    function toggle(day: number) {
        onChange(
            value.includes(day)
                ? value.filter((selected) => selected !== day)
                : [...value, day].sort((a, b) => a - b),
        );
    }

    return (
        <div className="space-y-2" data-test="schedule-days">
            <div
                role="group"
                aria-label="Days of the week"
                className="flex flex-wrap gap-1.5"
            >
                {ISO_DAYS.map((day) => {
                    const selected = value.includes(day);

                    return (
                        <button
                            key={day}
                            type="button"
                            disabled={disabled}
                            aria-pressed={selected}
                            aria-label={labels[String(day)] ?? String(day)}
                            data-test={`schedule-day-${day}`}
                            onClick={() => toggle(day)}
                            className={cn(
                                'focus-visible:border-ring focus-visible:ring-ring/50 h-9 min-w-11 rounded-md border px-3 text-sm font-medium transition-colors outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                                selected
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-input hover:bg-muted bg-transparent',
                            )}
                        >
                            {dayShortLabel(day, shortLabels)}
                        </button>
                    );
                })}
            </div>
            <div className="flex flex-wrap gap-1.5">
                {shortcuts.map((shortcut) => (
                    <Button
                        key={shortcut.label}
                        type="button"
                        size="sm"
                        variant={
                            sameDays(value, shortcut.days)
                                ? 'secondary'
                                : 'ghost'
                        }
                        disabled={disabled}
                        data-test={`schedule-days-${shortcut.label
                            .toLowerCase()
                            .replace(/\s+/g, '-')}`}
                        onClick={() =>
                            onChange([...shortcut.days].sort((a, b) => a - b))
                        }
                    >
                        {shortcut.label}
                    </Button>
                ))}
            </div>
        </div>
    );
}
