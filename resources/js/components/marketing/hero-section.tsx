import { Link, usePage } from '@inertiajs/react';
import { motion } from 'motion/react';
import {
    ArrowRightIcon,
    CalendarClockIcon,
    CheckIcon,
    MonitorPlayIcon,
    WifiIcon,
} from 'lucide-react';
import ProductFrame, {
    MockLine,
    StatusPill,
} from '@/components/marketing/product-frame';
import { Button } from '@/components/ui/button';
import { marketingImageAttrs } from '@/lib/marketing-images';
import {
    ambientLoop,
    fadeUp,
    scrollToSection,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { register } from '@/routes';
import { dashboard } from '@/routes/app';

const WORKFLOW_CHIPS = [
    'Screen Designs',
    'Playlists',
    'Schedules',
    'Publishing',
];

export default function HeroSection({ canRegister }: { canRegister: boolean }) {
    const { auth } = usePage().props;
    const reduced = usePrefersReducedMotion();
    const glow = ambientLoop(reduced, 14);

    return (
        <section
            data-test="marketing-hero"
            data-reduced-motion={reduced ? 'true' : 'false'}
            className="relative overflow-hidden px-6 pt-16 pb-20 sm:pt-24 sm:pb-28"
        >
            {/* Depth: token-driven glow + grid, kept subtle in both themes. */}
            <motion.div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_70%_55%_at_50%_-10%,_color-mix(in_oklch,var(--primary)_20%,transparent),_transparent_70%)]"
                animate={glow ? { opacity: [0.75, 1, 0.75] } : undefined}
                transition={glow ?? undefined}
            />
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 [background-image:linear-gradient(var(--border)_1px,transparent_1px),linear-gradient(90deg,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,black,transparent)] [background-size:56px_56px] opacity-[0.18]"
            />

            <motion.div
                initial="hidden"
                animate="visible"
                variants={stagger(reduced, 0.08)}
                className="relative mx-auto flex w-full max-w-3xl flex-col items-center text-center"
            >
                <motion.span
                    variants={fadeUp(reduced)}
                    className="border-border/70 bg-card/70 text-muted-foreground inline-flex items-center gap-2 rounded-full border px-3 py-1 font-mono text-[11px] tracking-[0.14em] uppercase backdrop-blur"
                >
                    <span className="bg-primary size-1.5 rounded-full" />
                    Digital signage platform
                </motion.span>

                <motion.h1
                    variants={fadeUp(reduced)}
                    className="font-display mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-5xl md:text-6xl"
                >
                    Digital signage,{' '}
                    <span className="text-primary">made simple.</span>
                </motion.h1>

                <motion.p
                    variants={fadeUp(reduced)}
                    className="text-muted-foreground mt-5 max-w-xl text-base leading-relaxed text-pretty sm:text-lg"
                    data-test="marketing-hero-supporting"
                >
                    Create screen designs and playlists, schedule, and publish
                    to any Screen remotely from your RMSignage business.
                </motion.p>

                <motion.div
                    variants={fadeUp(reduced)}
                    className="mt-9 flex flex-col gap-3 sm:flex-row"
                >
                    {auth.user ? (
                        <Button
                            data-test="marketing-hero-open-dashboard"
                            size="lg"
                            asChild
                        >
                            <Link href={dashboard()}>
                                Open Dashboard
                                <ArrowRightIcon />
                            </Link>
                        </Button>
                    ) : (
                        canRegister && (
                            <Button
                                data-test="marketing-hero-get-started"
                                size="lg"
                                asChild
                            >
                                <Link href={register()}>
                                    Get Started
                                    <ArrowRightIcon />
                                </Link>
                            </Button>
                        )
                    )}
                    <Button
                        data-test="marketing-hero-explore"
                        size="lg"
                        variant="outline"
                        onClick={() => scrollToSection('product', reduced)}
                    >
                        Explore the Platform
                    </Button>
                </motion.div>

                <motion.ul
                    variants={fadeUp(reduced)}
                    className="text-muted-foreground mt-8 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm"
                >
                    {WORKFLOW_CHIPS.map((chip) => (
                        <li key={chip} className="flex items-center gap-1.5">
                            <CheckIcon className="text-primary size-3.5" />
                            {chip}
                        </li>
                    ))}
                </motion.ul>
            </motion.div>

            <HeroVisual reduced={reduced} />
        </section>
    );
}

function HeroVisual({ reduced }: { reduced: boolean }) {
    const float = ambientLoop(reduced, 6);
    const pulse = ambientLoop(reduced, 2.4);

    return (
        <motion.div
            initial={reduced ? undefined : { opacity: 0, y: 28 }}
            animate={reduced ? undefined : { opacity: 1, y: 0 }}
            transition={reduced ? undefined : { duration: 0.7, delay: 0.25 }}
            className="relative mx-auto mt-16 w-full max-w-5xl"
        >
            <ProductFrame
                label="rmsignage.app/app/screen-designs/cafe-iced-latte/edit"
                badge={
                    <StatusPill
                        tone="live"
                        icon={
                            <span className="size-1.5 rounded-full bg-current" />
                        }
                    >
                        Draft saved
                    </StatusPill>
                }
                bodyClassName="flex"
            >
                {/* Editor tool rail */}
                <div
                    aria-hidden
                    className="border-border/70 hidden w-12 shrink-0 flex-col items-center gap-3 border-r py-4 sm:flex"
                >
                    {Array.from({ length: 5 }).map((_, index) => (
                        <span
                            key={index}
                            className={
                                index === 0
                                    ? 'bg-primary/15 ring-primary/40 size-7 rounded-md ring-1'
                                    : 'bg-muted size-7 rounded-md'
                            }
                        />
                    ))}
                </div>

                {/* Canvas — café iced coffee promo Screen Design mock */}
                <div className="bg-muted/40 min-w-0 flex-1 p-4 sm:p-6">
                    <div className="relative overflow-hidden rounded-lg bg-slate-950 shadow-inner">
                        <div className="relative aspect-video w-full">
                            <img
                                {...marketingImageAttrs({
                                    imageKey: 'cafe',
                                    priority: true,
                                })}
                                className="absolute inset-0 size-full object-cover"
                            />
                            <div className="absolute inset-0 bg-gradient-to-r from-black/75 via-black/35 to-transparent" />

                            <div className="relative flex h-full flex-col justify-between p-5 sm:p-8">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="font-mono text-[10px] tracking-[0.2em] text-amber-200/90 uppercase sm:text-xs">
                                            Café · Ground floor
                                        </p>
                                        <p className="font-display mt-2 text-xl leading-tight font-semibold text-white sm:text-3xl md:text-4xl">
                                            Iced Latte
                                            <br />
                                            <span className="text-amber-300">
                                                40% OFF
                                            </span>
                                        </p>
                                        <p className="mt-2 max-w-[16rem] text-xs text-white/70 sm:text-sm">
                                            This weekend only · Ask at the
                                            counter
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-white/15 bg-black/40 px-3 py-2 text-right backdrop-blur">
                                        <p className="font-mono text-lg font-semibold text-white sm:text-2xl">
                                            14:32
                                        </p>
                                        <p className="font-mono text-[9px] tracking-widest text-white/50 uppercase">
                                            Clock widget
                                        </p>
                                    </div>
                                </div>

                                <div className="grid max-w-md grid-cols-3 gap-2 sm:gap-3">
                                    <div className="rounded-lg border border-amber-400/25 bg-amber-500/20 p-2 backdrop-blur-sm sm:p-3">
                                        <p className="font-mono text-[9px] tracking-wider text-amber-200 uppercase">
                                            Weather
                                        </p>
                                        <p className="mt-1 text-sm font-semibold text-white">
                                            18°
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-white/15 bg-black/35 p-2 backdrop-blur-sm sm:p-3">
                                        <p className="font-mono text-[9px] tracking-wider text-white/55 uppercase">
                                            Until
                                        </p>
                                        <p className="mt-1 text-sm font-semibold text-white">
                                            Sun
                                        </p>
                                    </div>
                                    <div className="rounded-lg border border-white/15 bg-black/35 p-2 backdrop-blur-sm sm:p-3">
                                        <p className="font-mono text-[9px] tracking-wider text-white/55 uppercase">
                                            Promo
                                        </p>
                                        <p className="mt-1 text-sm font-semibold text-white">
                                            Live
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Selection handles hint at the drag/resize editor. */}
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-4 rounded-md ring-1 ring-amber-400/30 sm:inset-6"
                        />
                    </div>

                    <div className="text-muted-foreground mt-4 flex flex-wrap items-center gap-3 font-mono text-[10px] tracking-wide uppercase">
                        <span className="border-border/70 bg-card rounded border px-2 py-1">
                            1920 × 1080
                        </span>
                        <span className="border-border/70 bg-card rounded border px-2 py-1">
                            Landscape
                        </span>
                        <span className="border-border/70 bg-card rounded border px-2 py-1">
                            LayoutSchema v1
                        </span>
                    </div>
                </div>

                {/* Properties rail */}
                <div
                    aria-hidden
                    className="border-border/70 hidden w-44 shrink-0 flex-col gap-4 border-l p-4 lg:flex"
                >
                    <MockLine className="h-2 w-20" tone="strong" />
                    <div className="flex flex-col gap-2">
                        <MockLine className="h-7 w-full" />
                        <MockLine className="h-7 w-full" />
                        <MockLine className="h-7 w-2/3" />
                    </div>
                    <MockLine className="h-2 w-16" tone="strong" />
                    <div className="flex flex-col gap-2">
                        <MockLine className="h-7 w-full" />
                        <MockLine className="h-7 w-3/4" />
                    </div>
                    <MockLine className="mt-auto h-8 w-full" tone="primary" />
                </div>
            </ProductFrame>

            {/* Floating panels: the states that matter after publishing. */}
            <motion.div
                animate={float ? { y: [0, -8, 0] } : undefined}
                transition={float ?? undefined}
                className="border-border/80 bg-card/95 absolute top-1/3 -left-2 hidden items-center gap-2.5 rounded-lg border px-3 py-2 shadow-xl backdrop-blur sm:flex xl:-left-8"
            >
                <span className="bg-success/10 text-success flex size-7 items-center justify-center rounded-md">
                    <WifiIcon className="size-4" />
                </span>
                <div className="text-left">
                    <p className="text-xs font-medium">Lobby TV</p>
                    <p className="text-success font-mono text-[10px] tracking-wide uppercase">
                        Online
                    </p>
                </div>
            </motion.div>

            <motion.div
                animate={float ? { y: [0, 9, 0] } : undefined}
                transition={float ? { ...float, delay: 1.1 } : undefined}
                className="border-border/80 bg-card/95 absolute -right-2 bottom-1/4 hidden items-center gap-2.5 rounded-lg border px-3 py-2 shadow-xl backdrop-blur sm:flex xl:-right-8"
            >
                <span className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-md">
                    <MonitorPlayIcon className="size-4" />
                </span>
                <div className="text-left">
                    <p className="text-xs font-medium">Publishing</p>
                    <p className="text-primary flex items-center gap-1.5 font-mono text-[10px] tracking-wide uppercase">
                        <motion.span
                            animate={
                                pulse ? { opacity: [1, 0.35, 1] } : undefined
                            }
                            transition={pulse ?? undefined}
                            className="bg-primary size-1.5 rounded-full"
                        />
                        Live
                    </p>
                </div>
            </motion.div>

            <motion.div
                animate={float ? { y: [0, -6, 0] } : undefined}
                transition={float ? { ...float, delay: 0.6 } : undefined}
                className="border-border/80 bg-card/95 absolute -bottom-7 left-1/2 hidden -translate-x-1/2 items-center gap-2.5 rounded-lg border px-3 py-2 shadow-xl backdrop-blur md:flex"
            >
                <span className="bg-info/10 text-info flex size-7 items-center justify-center rounded-md">
                    <CalendarClockIcon className="size-4" />
                </span>
                <div className="text-left">
                    <p className="text-xs font-medium">Lunch Menu</p>
                    <p className="text-muted-foreground font-mono text-[10px] tracking-wide uppercase">
                        11:30 – 14:30 · Mon–Fri
                    </p>
                </div>
            </motion.div>
        </motion.div>
    );
}
