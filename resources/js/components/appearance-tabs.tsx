import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type AppearanceTabsProps = HTMLAttributes<HTMLDivElement> & {
    /** Compact control for menus / tight layouts */
    size?: 'default' | 'sm';
};

export default function AppearanceTabs({
    className = '',
    size = 'default',
    ...props
}: AppearanceTabsProps) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <div
            role="radiogroup"
            aria-label="Theme"
            className={cn(
                'bg-muted inline-flex gap-1 rounded-lg p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => {
                const selected = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        aria-label={label}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'focus-visible:border-ring focus-visible:ring-ring/50 inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors outline-none focus-visible:ring-[3px]',
                            size === 'sm' ? 'size-8' : 'gap-1.5 px-3.5 py-1.5',
                            selected
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4 shrink-0" aria-hidden />
                        {size === 'default' ? <span>{label}</span> : null}
                    </button>
                );
            })}
        </div>
    );
}
