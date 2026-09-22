/**
 * Display helpers for billing surfaces. Status values themselves come from
 * `App\Support\Billing\BillingEntitlement` — never re-derive them here.
 */

/** Maps a billing status onto a `StatusBadge` tone (text + icon, not colour only). */
export function billingStatusTone(status: string | null | undefined): string {
    switch (status) {
        case 'active':
        case 'trialing':
            return 'healthy';
        case 'past_due':
        case 'incomplete':
        case 'canceling':
            return 'degraded';
        case 'canceled':
        case 'unpaid':
        case 'incomplete_expired':
            return 'unavailable';
        default:
            return 'neutral';
    }
}

/** Invoice status tone (Stripe invoice statuses, not subscription statuses). */
export function invoiceStatusTone(status: string | null | undefined): string {
    switch (status) {
        case 'paid':
            return 'healthy';
        case 'open':
        case 'draft':
            return 'degraded';
        case 'uncollectible':
            return 'unavailable';
        default:
            return 'neutral';
    }
}

/** Date only — billing periods and invoice dates never need a clock time. */
export function formatBillingDate(iso: string | null | undefined): string {
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

export function intervalLabel(interval: string | null | undefined): string {
    switch (interval) {
        case 'monthly':
            return 'Monthly';
        case 'yearly':
            return 'Yearly';
        default:
            return 'Unknown interval';
    }
}

/** Suffix used next to a real Stripe amount, e.g. "£12.00 per Screen / month". */
export function intervalSuffix(interval: string | null | undefined): string {
    switch (interval) {
        case 'monthly':
            return 'month';
        case 'yearly':
            return 'year';
        default:
            return 'billing period';
    }
}
