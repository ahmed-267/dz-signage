import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import MarketingFooter from '@/components/marketing/marketing-footer';
import MarketingNavbar from '@/components/marketing/marketing-navbar';
import {
    fadeUp,
    reveal,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { cn } from '@/lib/utils';

export default function MarketingLayout({
    canRegister,
    children,
}: {
    canRegister: boolean;
    children: ReactNode;
}) {
    return (
        <div className="bg-background text-foreground flex min-h-dvh flex-col">
            <a
                href="#marketing-main"
                className="bg-primary text-primary-foreground focus-visible:ring-ring sr-only focus-visible:not-sr-only focus-visible:fixed focus-visible:top-4 focus-visible:left-4 focus-visible:z-100 focus-visible:rounded-md focus-visible:px-4 focus-visible:py-2 focus-visible:text-sm focus-visible:font-medium focus-visible:ring-2"
            >
                Skip to content
            </a>
            <MarketingNavbar canRegister={canRegister} />
            <main id="marketing-main" className="flex-1">
                {children}
            </main>
            <MarketingFooter canRegister={canRegister} />
        </div>
    );
}

/**
 * Consistent vertical rhythm + anchor offset for every landing section.
 * `id` is optional: only the six navigable sections carry anchors.
 */
export function MarketingSection({
    id,
    className,
    containerClassName,
    children,
    ...props
}: React.ComponentProps<'section'> & { containerClassName?: string }) {
    return (
        <section
            id={id}
            className={cn(
                'relative scroll-mt-20 px-6 py-20 sm:py-24',
                className,
            )}
            {...props}
        >
            <div className={cn('mx-auto w-full max-w-6xl', containerClassName)}>
                {children}
            </div>
        </section>
    );
}

export function SectionHeading({
    eyebrow,
    title,
    description,
    align = 'center',
    className,
}: {
    eyebrow?: string;
    title: ReactNode;
    description?: ReactNode;
    align?: 'center' | 'left';
    className?: string;
}) {
    const reduced = usePrefersReducedMotion();

    return (
        <motion.div
            {...reveal(reduced)}
            variants={fadeUp(reduced)}
            className={cn(
                'flex flex-col gap-4',
                align === 'center'
                    ? 'mx-auto max-w-2xl text-center'
                    : 'max-w-2xl',
                className,
            )}
        >
            {eyebrow && (
                <span className="text-primary font-mono text-xs font-semibold tracking-[0.2em] uppercase">
                    {eyebrow}
                </span>
            )}
            <h2 className="font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                {title}
            </h2>
            {description && (
                <p className="text-muted-foreground text-base leading-relaxed text-pretty">
                    {description}
                </p>
            )}
        </motion.div>
    );
}
