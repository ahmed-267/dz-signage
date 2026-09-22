import type { ComponentProps } from 'react';
import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const OPTIONS: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light theme' },
    { value: 'dark', icon: Moon, label: 'Dark theme' },
    { value: 'system', icon: Monitor, label: 'Use system theme' },
];

/** Compact Light / Dark / System control for app, admin, and marketing headers. */
export function ThemeToggle({ className, ...props }: ComponentProps<'div'>) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="radiogroup"
            aria-label="Theme"
            data-test="theme-toggle"
            className={cn(
                'bg-muted inline-flex shrink-0 gap-0.5 rounded-lg p-0.5',
                className,
            )}
            {...props}
        >
            {OPTIONS.map(({ value, icon: Icon, label }) => {
                const selected = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        aria-label={label}
                        title={label}
                        onClick={() => updateAppearance(value)}
                        className={cn(
                            'focus-visible:border-ring focus-visible:ring-ring/50 inline-flex size-7 items-center justify-center rounded-md transition-colors outline-none focus-visible:ring-[3px]',
                            selected
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:bg-background/60 hover:text-foreground',
                        )}
                    >
                        <Icon className="size-3.5 shrink-0" aria-hidden />
                    </button>
                );
            })}
        </div>
    );
}
