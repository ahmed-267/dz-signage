/**
 * Human "last seen" text for Screen presence.
 * Online/Offline itself is decided server-side by ScreenPresence — this is display only.
 */
export function formatLastSeen(iso: string | null | undefined): string {
    if (!iso) {
        return 'Never';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return 'Never';
    }

    const seconds = Math.round((Date.now() - date.getTime()) / 1000);

    if (seconds < 0) {
        return 'Just now';
    }

    if (seconds < 45) {
        return 'Just now';
    }

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return minutes <= 1 ? '1 minute ago' : `${minutes} minutes ago`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return hours === 1 ? '1 hour ago' : `${hours} hours ago`;
    }

    const days = Math.round(hours / 24);
    if (days < 30) {
        return days === 1 ? '1 day ago' : `${days} days ago`;
    }

    const months = Math.round(days / 30);
    if (months < 12) {
        return months === 1 ? '1 month ago' : `${months} months ago`;
    }

    const years = Math.round(months / 12);
    return years === 1 ? '1 year ago' : `${years} years ago`;
}

/**
 * Date + time for troubleshooting rows (heartbeat history, deployments).
 */
export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
