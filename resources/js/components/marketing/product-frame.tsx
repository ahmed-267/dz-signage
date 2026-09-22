import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * App-window chrome for product mocks on the landing page.
 * Frames can contain local marketing imagery under /images/marketing.
 */
export default function ProductFrame({
    label,
    badge,
    className,
    bodyClassName,
    children,
}: {
    label?: string;
    badge?: ReactNode;
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'border-border/80 bg-card ring-border/40 overflow-hidden rounded-xl border shadow-2xl ring-1 shadow-black/5 dark:shadow-black/40',
                className,
            )}
        >
            <div className="border-border/70 bg-muted/60 flex items-center gap-3 border-b px-3 py-2.5">
                <div className="flex items-center gap-1.5" aria-hidden>
                    <span className="bg-border size-2.5 rounded-full" />
                    <span className="bg-border size-2.5 rounded-full" />
                    <span className="bg-border size-2.5 rounded-full" />
                </div>
                {label && (
                    <span className="border-border/70 bg-background/70 text-muted-foreground truncate rounded-md border px-2 py-0.5 font-mono text-[10px] tracking-wide">
                        {label}
                    </span>
                )}
                {badge && <div className="ml-auto">{badge}</div>}
            </div>
            <div className={cn('bg-card', bodyClassName)}>{children}</div>
        </div>
    );
}

/** TV bezel for Screen / fleet / template mocks. */
export function ScreenFrame({
    orientation = 'landscape',
    className,
    children,
    stand = false,
}: {
    orientation?: 'landscape' | 'portrait';
    className?: string;
    children: ReactNode;
    stand?: boolean;
}) {
    return (
        <div className={cn('flex flex-col items-center', className)}>
            <div
                className={cn(
                    'w-full overflow-hidden rounded-lg bg-neutral-900 p-1.5 shadow-lg ring-1 ring-black/20 dark:bg-black',
                )}
            >
                <div
                    className={cn(
                        'relative w-full overflow-hidden rounded-md bg-slate-950',
                        orientation === 'landscape'
                            ? 'aspect-video'
                            : 'aspect-[9/16]',
                    )}
                >
                    {children}
                </div>
            </div>
            {stand && (
                <>
                    <span
                        aria-hidden
                        className="h-2.5 w-8 bg-neutral-800 dark:bg-neutral-900"
                    />
                    <span
                        aria-hidden
                        className="h-1 w-16 rounded-full bg-neutral-800 dark:bg-neutral-900"
                    />
                </>
            )}
        </div>
    );
}

const STATUS_TONES = {
    online: 'border-success/40 bg-success/10 text-success',
    offline: 'border-border bg-muted text-muted-foreground',
    live: 'border-primary/40 bg-primary/10 text-primary',
    scheduled: 'border-info/40 bg-info/10 text-info',
    warning: 'border-warning/40 bg-warning/10 text-warning',
} as const;

export type StatusTone = keyof typeof STATUS_TONES;

/**
 * Status chip that always pairs an icon with a text label, so state is never
 * communicated by colour alone.
 */
export function StatusPill({
    tone,
    icon,
    children,
    className,
}: {
    tone: StatusTone;
    icon?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 font-mono text-[10px] font-semibold tracking-wide uppercase',
                STATUS_TONES[tone],
                className,
            )}
        >
            {icon}
            {children}
        </span>
    );
}

/** Neutral skeleton bar for mock content blocks. */
export function MockLine({
    className,
    tone = 'default',
}: {
    className?: string;
    tone?: 'default' | 'strong' | 'primary';
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'block rounded-full',
                tone === 'primary'
                    ? 'bg-primary/60'
                    : tone === 'strong'
                      ? 'bg-foreground/25'
                      : 'bg-foreground/10',
                className,
            )}
        />
    );
}
