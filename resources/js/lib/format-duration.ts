/**
 * Human playlist duration — "45s", "1m 7s", "1h 5m".
 */
export function formatDuration(seconds: number | null | undefined): string {
    if (seconds == null || !Number.isFinite(seconds) || seconds < 0) {
        return '—';
    }

    const total = Math.round(seconds);

    if (total < 60) {
        return `${total}s`;
    }

    if (total < 3600) {
        const minutes = Math.floor(total / 60);
        const rest = total % 60;
        return rest === 0 ? `${minutes}m` : `${minutes}m ${rest}s`;
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    return minutes === 0 ? `${hours}h` : `${hours}h ${minutes}m`;
}

/**
 * Clock duration — "0:45", "1:07", "1:02:03".
 */
export function formatDurationClock(
    seconds: number | null | undefined,
): string {
    if (seconds == null || !Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const total = Math.round(seconds);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const rest = total % 60;

    if (hours > 0) {
        return `${hours}:${minutes.toString().padStart(2, '0')}:${rest
            .toString()
            .padStart(2, '0')}`;
    }

    return `${minutes}:${rest.toString().padStart(2, '0')}`;
}
