import type { CSSProperties, ReactNode } from 'react';
import type { LayoutElementProps } from '@/types/layout-schema';
import { cn } from '@/lib/utils';

type WidgetShellProps = {
    props?: LayoutElementProps;
    className?: string;
    children: ReactNode;
};

export function WidgetShell({ props, className, children }: WidgetShellProps) {
    const opacity =
        typeof props?.opacity === 'number'
            ? Math.min(1, Math.max(0, props.opacity))
            : 1;
    const borderRadius =
        typeof props?.borderRadius === 'number' ? props.borderRadius : 12;
    const textAlign = props?.textAlign ?? 'center';

    const style: CSSProperties = {
        opacity,
        borderRadius,
        color: typeof props?.color === 'string' ? props.color : '#FFFFFF',
        backgroundColor:
            typeof props?.fill === 'string' ? props.fill : 'transparent',
        fontFamily:
            typeof props?.fontFamily === 'string'
                ? props.fontFamily
                : 'Outfit, system-ui, sans-serif',
        fontSize: typeof props?.fontSize === 'number' ? props.fontSize : 28,
        fontWeight: props?.fontWeight ?? 600,
        textAlign,
        justifyContent:
            textAlign === 'center'
                ? 'center'
                : textAlign === 'right'
                  ? 'flex-end'
                  : 'flex-start',
    };

    return (
        <div
            className={cn(
                'flex h-full w-full flex-col overflow-hidden px-3 py-2',
                className,
            )}
            style={style}
            data-test="widget-shell"
        >
            {children}
        </div>
    );
}
