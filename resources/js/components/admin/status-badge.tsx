import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    type LucideIcon,
    XCircle,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';

type StatusTone =
    | 'healthy'
    | 'degraded'
    | 'unavailable'
    | 'neutral'
    | 'warning'
    | 'info';

const TONE_META: Record<
    StatusTone,
    {
        variant:
            | 'success'
            | 'warning'
            | 'destructive'
            | 'neutral'
            | 'info'
            | 'secondary';
        icon: LucideIcon;
    }
> = {
    healthy: { variant: 'success', icon: CheckCircle2 },
    degraded: { variant: 'warning', icon: AlertTriangle },
    unavailable: { variant: 'destructive', icon: XCircle },
    warning: { variant: 'warning', icon: AlertTriangle },
    info: { variant: 'info', icon: CircleDashed },
    neutral: { variant: 'neutral', icon: CircleDashed },
};

type StatusBadgeProps = {
    label: string;
    tone?: StatusTone | string | null;
    className?: string;
    'data-test'?: string;
};

function resolveTone(tone?: StatusTone | string | null): StatusTone {
    if (!tone) {
        return 'neutral';
    }

    const normalized = tone.toLowerCase().replace(/\s+/g, '_');

    if (
        normalized === 'healthy' ||
        normalized === 'ok' ||
        normalized === 'success' ||
        normalized === 'resolved' ||
        normalized === 'active' ||
        normalized === 'enabled'
    ) {
        return 'healthy';
    }

    if (
        normalized === 'degraded' ||
        normalized === 'warning' ||
        normalized === 'attention' ||
        normalized === 'pending' ||
        normalized === 'open'
    ) {
        return 'degraded';
    }

    if (
        normalized === 'unavailable' ||
        normalized === 'error' ||
        normalized === 'failed' ||
        normalized === 'critical' ||
        normalized === 'down'
    ) {
        return 'unavailable';
    }

    if (normalized === 'info') {
        return 'info';
    }

    if (normalized === 'warning') {
        return 'warning';
    }

    return 'neutral';
}

/**
 * Status badge with text + icon (not colour-only).
 */
export function StatusBadge({
    label,
    tone,
    className,
    'data-test': dataTest,
}: StatusBadgeProps) {
    const resolved = resolveTone(tone);
    const meta = TONE_META[resolved];
    const Icon = meta.icon;

    return (
        <Badge
            variant={meta.variant}
            className={className}
            data-test={dataTest}
        >
            <Icon aria-hidden />
            <span>{label}</span>
        </Badge>
    );
}
