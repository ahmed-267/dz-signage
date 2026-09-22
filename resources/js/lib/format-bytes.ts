export function formatBytes(bytes: number | null | undefined): string {
    if (bytes == null || Number.isNaN(bytes)) {
        return '—';
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    const kb = bytes / 1024;
    if (kb < 1024) {
        return `${kb < 10 ? kb.toFixed(1) : Math.round(kb)} KB`;
    }

    const mb = kb / 1024;
    return `${mb < 10 ? mb.toFixed(1) : Math.round(mb)} MB`;
}

export function formatRelativeDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
