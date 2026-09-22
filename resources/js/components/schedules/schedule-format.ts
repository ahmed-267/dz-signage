import type {
    ScheduleDayLabels,
    ScheduleDisplayStatus,
    ScheduleWindow,
} from '@/types/schedule';

/** ISO-8601 weekday order — Monday first, matching `ScheduleDays`. */
export const ISO_DAYS = [1, 2, 3, 4, 5, 6, 7] as const;

/** `HH:MM:SS` (or `HH:MM`) → the `HH:MM` a time input expects. */
export function toTimeInput(time: string | null | undefined): string {
    if (!time) {
        return '';
    }

    const match = /^(\d{1,2}):(\d{2})/.exec(time);
    if (!match) {
        return '';
    }

    return `${match[1].padStart(2, '0')}:${match[2]}`;
}

export function formatTimeRange(start: string, end: string): string {
    return `${toTimeInput(start)} – ${toTimeInput(end)}`;
}

/** Minutes since midnight, used for calendar layout and overnight hints. */
export function timeToMinutes(time: string | null | undefined): number {
    const normalized = toTimeInput(time);
    if (!normalized) {
        return 0;
    }

    const [hours, minutes] = normalized.split(':');
    return Number(hours) * 60 + Number(minutes);
}

/**
 * A window whose end is earlier than its start runs into the next day. The
 * server owns this rule (`Schedule::crossesMidnight()`); this mirrors it only
 * so the form can hint before saving.
 */
export function crossesMidnight(start: string, end: string): boolean {
    return timeToMinutes(start) > timeToMinutes(end);
}

export function formatMinutes(total: number): string {
    if (!Number.isFinite(total) || total <= 0) {
        return '—';
    }

    const hours = Math.floor(total / 60);
    const minutes = total % 60;

    if (hours === 0) {
        return `${minutes}m`;
    }

    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
}

export function dayShortLabel(day: number, labels: ScheduleDayLabels): string {
    return labels[String(day)] ?? String(day);
}

/**
 * "Every day" / "Weekdays" / "Weekends" when the selection matches exactly,
 * otherwise the short day names.
 */
export function describeDays(
    days: number[],
    shortLabels: ScheduleDayLabels,
): string {
    if (days.length === 0) {
        return 'No days selected';
    }

    if (days.length === 7) {
        return 'Every day';
    }

    const sorted = [...days].sort((a, b) => a - b).join(',');

    if (sorted === '1,2,3,4,5') {
        return 'Mon–Fri';
    }

    if (sorted === '6,7') {
        return 'Weekends';
    }

    // Contiguous weekday ranges: Mon–Wed, etc.
    if (days.length >= 3) {
        const unique = [...new Set(days)].sort((a, b) => a - b);
        let contiguous = true;
        for (let i = 1; i < unique.length; i++) {
            if (unique[i] !== unique[i - 1] + 1) {
                contiguous = false;
                break;
            }
        }
        if (contiguous) {
            return `${dayShortLabel(unique[0], shortLabels)}–${dayShortLabel(unique[unique.length - 1], shortLabels)}`;
        }
    }

    return days.map((day) => dayShortLabel(day, shortLabels)).join(', ');
}

export function statusBadgeVariant(
    status: ScheduleDisplayStatus,
): 'success' | 'warning' | 'neutral' | 'info' | 'secondary' {
    switch (status) {
        case 'active':
            return 'success';
        case 'paused':
            return 'warning';
        case 'draft':
            return 'info';
        case 'ended':
        case 'archived':
            return 'neutral';
        default:
            return 'secondary';
    }
}

/**
 * Window label in the schedule's own timezone. The server already converted,
 * so this only trims the redundant date when both ends share it.
 */
export function formatWindow(window: ScheduleWindow | null): string {
    if (!window) {
        return '—';
    }

    const [startDate, startTime] = window.starts_at_local.split(' ');
    const [endDate, endTime] = window.ends_at_local.split(' ');

    return startDate === endDate
        ? `${startDate} · ${startTime}–${endTime}`
        : `${startDate} ${startTime} → ${endDate} ${endTime}`;
}
