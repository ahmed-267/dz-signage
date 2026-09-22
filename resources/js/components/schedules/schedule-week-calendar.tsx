import { ChevronLeft, ChevronRight, MoonStar } from 'lucide-react';
import { formatMinutes } from '@/components/schedules/schedule-format';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type {
    ScheduleCalendar,
    ScheduleCalendarBlock,
    ScheduleCalendarDay,
    ScheduleDisplayStatus,
} from '@/types/schedule';

type ScheduleWeekCalendarProps = {
    calendar: ScheduleCalendar;
    /** Week to load, as a `YYYY-MM-DD` date inside that week. */
    onWeekChange: (week: string) => void;
    onSelect?: (scheduleId: number) => void;
};

const MINUTES_PER_DAY = 1440;
const GRID_HEIGHT_PX = 576;
const HOUR_STEP = 3;

function blockTone(status: ScheduleDisplayStatus): string {
    switch (status) {
        case 'active':
            return 'border-success/60 bg-success/10';
        case 'paused':
            return 'border-warning/60 bg-warning/10';
        case 'draft':
            return 'border-info/60 bg-info/10';
        default:
            return 'border-border bg-muted';
    }
}

/**
 * Greedy lane assignment so overlapping occurrences sit side by side instead of
 * hiding each other. Blocks arrive in precedence order, so the winning schedule
 * keeps the leftmost lane.
 */
function assignLanes(
    blocks: ScheduleCalendarBlock[],
): { block: ScheduleCalendarBlock; lane: number; lanes: number }[] {
    const laneEnds: number[] = [];
    const placed = blocks.map((block) => {
        const lane = laneEnds.findIndex((end) => end <= block.start_minutes);
        const index = lane === -1 ? laneEnds.length : lane;
        laneEnds[index] = Math.min(block.end_minutes, MINUTES_PER_DAY);

        return { block, lane: index };
    });

    const lanes = Math.max(1, laneEnds.length);

    return placed.map((entry) => ({ ...entry, lanes }));
}

/** Minute-of-day (possibly past 1440 for overnight windows) as `HH:MM`. */
function minutesToClock(minutes: number): string {
    const hours = Math.floor(minutes / 60) % 24;
    return `${String(hours).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
}

function blockTitle(block: ScheduleCalendarBlock): string {
    const range = `${minutesToClock(block.start_minutes)}–${minutesToClock(block.end_minutes)}`;
    const overnight = block.crosses_midnight ? ' (next day)' : '';

    return `${block.name} · ${range}${overnight} · priority ${block.priority}`;
}

/**
 * Week grid of schedule occurrences, laid out in the workspace timezone by
 * `ScheduleController::calendar()`. The grid is the desktop view; small
 * viewports get the same data as a day-by-day agenda, which stays readable on
 * a phone.
 */
export function ScheduleWeekCalendar({
    calendar,
    onWeekChange,
    onSelect,
}: ScheduleWeekCalendarProps) {
    const hourMarks = Array.from(
        { length: Math.floor(24 / HOUR_STEP) + 1 },
        (_, index) => index * HOUR_STEP * 60,
    );

    return (
        <div className="space-y-3" data-test="schedule-calendar">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium">
                        {calendar.week_start} – {calendar.week_end}
                    </p>
                    <p className="text-muted-foreground text-xs">
                        Times shown in {calendar.timezone}
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="schedule-calendar-prev"
                        onClick={() => onWeekChange(calendar.previous_week)}
                    >
                        <ChevronLeft className="size-4" />
                        Previous
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="schedule-calendar-today"
                        onClick={() => onWeekChange('')}
                    >
                        This week
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        data-test="schedule-calendar-next"
                        onClick={() => onWeekChange(calendar.next_week)}
                    >
                        Next
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>

            <div className="border-border bg-card hidden overflow-hidden rounded-xl border md:block">
                <div className="border-border grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b">
                    <div />
                    {calendar.days.map((day) => (
                        <div
                            key={day.date}
                            className={cn(
                                'border-border border-l px-2 py-2 text-center',
                                day.is_today && 'bg-muted/60',
                            )}
                        >
                            <p className="text-sm font-medium">
                                {day.short_label}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {day.date.slice(5)}
                            </p>
                        </div>
                    ))}
                </div>
                <div
                    className="relative grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]"
                    style={{ height: `${GRID_HEIGHT_PX}px` }}
                >
                    <div className="relative">
                        {hourMarks.slice(0, -1).map((minutes) => (
                            <span
                                key={minutes}
                                className="text-muted-foreground absolute right-2 -translate-y-1/2 font-mono text-[10px]"
                                style={{
                                    top: `${(minutes / MINUTES_PER_DAY) * 100}%`,
                                }}
                            >
                                {minutesToClock(minutes)}
                            </span>
                        ))}
                    </div>
                    {calendar.days.map((day) => (
                        <div
                            key={day.date}
                            data-test={`schedule-calendar-day-${day.date}`}
                            data-block-count={day.blocks.length}
                            className={cn(
                                'border-border relative border-l',
                                day.is_today && 'bg-muted/40',
                            )}
                        >
                            {hourMarks.slice(1, -1).map((minutes) => (
                                <div
                                    key={minutes}
                                    className="border-border/60 absolute inset-x-0 border-t"
                                    style={{
                                        top: `${(minutes / MINUTES_PER_DAY) * 100}%`,
                                    }}
                                />
                            ))}
                            {assignLanes(day.blocks).map(
                                ({ block, lane, lanes }) => {
                                    const clampedEnd = Math.min(
                                        block.end_minutes,
                                        MINUTES_PER_DAY,
                                    );
                                    const top =
                                        (block.start_minutes /
                                            MINUTES_PER_DAY) *
                                        100;
                                    const height = Math.max(
                                        2.5,
                                        ((clampedEnd - block.start_minutes) /
                                            MINUTES_PER_DAY) *
                                            100,
                                    );

                                    return (
                                        <button
                                            key={`${block.schedule_id}-${block.starts_at}`}
                                            type="button"
                                            title={blockTitle(block)}
                                            data-test={`schedule-calendar-block-${block.schedule_id}`}
                                            onClick={() =>
                                                onSelect?.(block.schedule_id)
                                            }
                                            className={cn(
                                                'focus-visible:ring-ring/50 absolute overflow-hidden rounded-md border px-1.5 py-1 text-left outline-none focus-visible:ring-[3px]',
                                                blockTone(block.status),
                                            )}
                                            style={{
                                                top: `${top}%`,
                                                height: `${height}%`,
                                                left: `calc(${(lane / lanes) * 100}% + 2px)`,
                                                width: `calc(${100 / lanes}% - 4px)`,
                                            }}
                                        >
                                            <span className="block truncate text-[11px] font-medium">
                                                {block.name}
                                            </span>
                                            <span className="text-muted-foreground block truncate text-[10px]">
                                                {block.playlist_name ??
                                                    'No playlist'}
                                            </span>
                                        </button>
                                    );
                                },
                            )}
                        </div>
                    ))}
                </div>
            </div>

            <div className="space-y-3 md:hidden">
                {calendar.days.map((day) => (
                    <AgendaDay key={day.date} day={day} onSelect={onSelect} />
                ))}
            </div>
        </div>
    );
}

function AgendaDay({
    day,
    onSelect,
}: {
    day: ScheduleCalendarDay;
    onSelect?: (scheduleId: number) => void;
}) {
    return (
        <div
            className="border-border bg-card overflow-hidden rounded-xl border"
            data-test={`schedule-agenda-day-${day.date}`}
        >
            <div
                className={cn(
                    'border-border flex items-center justify-between border-b px-3 py-2',
                    day.is_today && 'bg-muted/60',
                )}
            >
                <p className="text-sm font-medium">{day.label}</p>
                <p className="text-muted-foreground font-mono text-xs">
                    {day.date}
                </p>
            </div>
            {day.blocks.length === 0 ? (
                <p className="text-muted-foreground px-3 py-3 text-xs">
                    Nothing scheduled.
                </p>
            ) : (
                <ul className="divide-border divide-y">
                    {day.blocks.map((block) => (
                        <li key={`${block.schedule_id}-${block.starts_at}`}>
                            <button
                                type="button"
                                className="hover:bg-muted/50 flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left"
                                data-test={`schedule-agenda-block-${block.schedule_id}`}
                                onClick={() => onSelect?.(block.schedule_id)}
                            >
                                <span className="min-w-0">
                                    <span className="block truncate text-sm font-medium">
                                        {block.name}
                                    </span>
                                    <span className="text-muted-foreground block truncate text-xs">
                                        {block.playlist_name ?? 'No playlist'}
                                    </span>
                                </span>
                                <span className="flex shrink-0 items-center gap-1.5">
                                    {block.crosses_midnight ? (
                                        <MoonStar
                                            className="text-muted-foreground size-3.5"
                                            aria-label="Runs past midnight"
                                        />
                                    ) : null}
                                    <Badge variant="neutral">
                                        {minutesToClock(block.start_minutes)}
                                    </Badge>
                                    <span className="text-muted-foreground font-mono text-xs">
                                        {formatMinutes(
                                            block.end_minutes -
                                                block.start_minutes,
                                        )}
                                    </span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
