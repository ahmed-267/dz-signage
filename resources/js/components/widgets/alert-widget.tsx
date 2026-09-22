import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import { WidgetShell } from '@/components/widgets/widget-shell';
import type { AlertWidgetConfig } from '@/lib/widgets/types';
import type { LayoutElementProps } from '@/types/layout-schema';
import { cn } from '@/lib/utils';

type AlertWidgetProps = {
    config: AlertWidgetConfig;
    elementProps?: LayoutElementProps;
};

const SEVERITY_STYLES: Record<
    AlertWidgetConfig['severity'],
    { bg: string; label: string }
> = {
    info: { bg: 'rgba(59, 130, 246, 0.22)', label: 'Info' },
    warning: { bg: 'rgba(245, 158, 11, 0.24)', label: 'Warning' },
    error: { bg: 'rgba(239, 68, 68, 0.24)', label: 'Error' },
    success: { bg: 'rgba(34, 197, 94, 0.22)', label: 'Success' },
};

function SeverityIcon({ icon }: { icon: AlertWidgetConfig['icon'] }) {
    const props = { className: 'size-[0.9em] shrink-0', 'aria-hidden': true };
    switch (icon) {
        case 'warning':
            return <AlertTriangle {...props} />;
        case 'error':
            return <XCircle {...props} />;
        case 'success':
            return <CheckCircle2 {...props} />;
        default:
            return <Info {...props} />;
    }
}

export function AlertWidget({ config, elementProps }: AlertWidgetProps) {
    const severity = SEVERITY_STYLES[config.severity] ?? SEVERITY_STYLES.info;
    const align =
        config.alignment === 'center'
            ? 'items-center text-center'
            : config.alignment === 'right'
              ? 'items-end text-right'
              : 'items-start text-left';

    return (
        <WidgetShell
            props={{
                ...elementProps,
                fill:
                    typeof elementProps?.fill === 'string'
                        ? elementProps.fill
                        : severity.bg,
            }}
            className={cn('justify-center gap-2', align)}
        >
            <div
                className={cn(
                    'flex gap-2',
                    config.alignment === 'center'
                        ? 'justify-center'
                        : config.alignment === 'right'
                          ? 'justify-end'
                          : 'justify-start',
                )}
                style={{ fontSize: '0.45em', fontWeight: 600 }}
            >
                <SeverityIcon icon={config.icon} />
                <span className="tracking-wide uppercase opacity-90">
                    {severity.label}
                </span>
            </div>
            {config.title ? (
                <div className="leading-tight" style={{ fontSize: '0.7em' }}>
                    {config.title}
                </div>
            ) : null}
            {config.message ? (
                <div
                    className="opacity-90"
                    style={{ fontSize: '0.45em', fontWeight: 500 }}
                >
                    {config.message}
                </div>
            ) : null}
        </WidgetShell>
    );
}
