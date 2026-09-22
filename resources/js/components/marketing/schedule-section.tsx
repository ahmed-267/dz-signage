import { motion } from 'motion/react';
import {
    GlobeIcon,
    LayersIcon,
    MoonIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import ProductFrame from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

const DAY_START = 6;
const DAY_END = 22;
const SLOTS = (DAY_END - DAY_START) * 2; // half-hour columns

/** Half-hour grid position for a decimal hour window. */
function slot(from: number, to: number) {
    return {
        gridColumnStart: Math.round((from - DAY_START) * 2) + 1,
        gridColumnEnd: Math.round((to - DAY_START) * 2) + 1,
    };
}

const WINDOWS = [
    {
        name: 'Morning Welcome',
        meta: '07:00 – 11:30 · Mon–Fri · Priority 1',
        from: 7,
        to: 11.5,
        className: 'bg-info/20 text-info border-info/40',
    },
    {
        name: 'Lunch Menu',
        meta: '11:30 – 14:30 · Mon–Fri · Priority 10',
        from: 11.5,
        to: 14.5,
        className: 'bg-primary/20 text-primary border-primary/40',
    },
    {
        name: 'Evening Promo',
        meta: '17:00 – 21:00 · Daily · Priority 5',
        from: 17,
        to: 21,
        className: 'bg-accent/20 text-accent border-accent/40',
    },
] as const;

const NOTES = [
    {
        icon: LayersIcon,
        title: 'Priority decides, not luck',
        body: 'When two active schedules overlap, the higher priority content plays. Overlaps are warnings on save — never blockers.',
    },
    {
        icon: GlobeIcon,
        title: 'Real timezones',
        body: 'Every schedule carries its own IANA timezone, so a window means the same wall-clock time at each location.',
    },
    {
        icon: MoonIcon,
        title: 'Overnight windows work',
        body: 'End times past midnight are supported, with inclusive starts and exclusive ends so handovers never double-play.',
    },
    {
        icon: TriangleAlertIcon,
        title: 'A fallback underneath',
        body: 'When no schedule matches, the always-on Deployment keeps playing. A TV is never left blank by accident.',
    },
] as const;

export default function ScheduleSection() {
    const reduced = usePrefersReducedMotion();
    const nowPercent = ((13 + 1 / 3 - DAY_START) / (DAY_END - DAY_START)) * 100;

    return (
        <MarketingSection>
            <SectionHeading
                eyebrow="Schedules"
                title="Right content, right time, without anyone on site"
                description="Schedules pin a published Playlist version to TVs for a wall-clock window. Timing is decided once, on the server."
            />

            <motion.div
                {...reveal(reduced)}
                variants={fadeUp(reduced, 24)}
                className="mt-14"
            >
                <ProductFrame
                    label="rmsignage.app/app/schedules"
                    badge={
                        <span className="text-muted-foreground font-mono text-[10px] tracking-wide uppercase">
                            Europe/London
                        </span>
                    }
                    bodyClassName="overflow-x-auto p-4 sm:p-6"
                >
                    <div className="min-w-[620px]">
                        {/* Hour ruler */}
                        <div
                            aria-hidden
                            className="text-muted-foreground grid font-mono text-[9px] tracking-wide"
                            style={{
                                gridTemplateColumns: `repeat(${SLOTS}, minmax(0, 1fr))`,
                            }}
                        >
                            {Array.from({
                                length: (DAY_END - DAY_START) / 2 + 1,
                            }).map((_, index) => (
                                <span
                                    key={index}
                                    style={{
                                        gridColumnStart: index * 4 + 1,
                                        gridColumnEnd: index * 4 + 5,
                                    }}
                                >
                                    {String(DAY_START + index * 2).padStart(
                                        2,
                                        '0',
                                    )}
                                    :00
                                </span>
                            ))}
                        </div>

                        <div className="relative mt-2">
                            <div
                                aria-hidden
                                className="border-border/60 pointer-events-none absolute inset-0 grid border-t"
                                style={{
                                    gridTemplateColumns: `repeat(${(DAY_END - DAY_START) / 2}, minmax(0, 1fr))`,
                                }}
                            >
                                {Array.from({
                                    length: (DAY_END - DAY_START) / 2,
                                }).map((_, index) => (
                                    <span
                                        key={index}
                                        className="border-border/40 border-l"
                                    />
                                ))}
                            </div>

                            <motion.span
                                aria-hidden
                                initial={reduced ? undefined : { opacity: 0 }}
                                whileInView={
                                    reduced ? undefined : { opacity: 1 }
                                }
                                viewport={{ once: true }}
                                transition={{ delay: 0.4 }}
                                className="bg-warning/70 absolute top-0 bottom-0 z-10 w-px"
                                style={{ left: `${nowPercent}%` }}
                            />

                            <div className="relative flex flex-col gap-2 pt-3">
                                {WINDOWS.map((window) => (
                                    <div
                                        key={window.name}
                                        className="grid items-center"
                                        style={{
                                            gridTemplateColumns: `repeat(${SLOTS}, minmax(0, 1fr))`,
                                        }}
                                    >
                                        <motion.div
                                            initial={
                                                reduced
                                                    ? undefined
                                                    : { scaleX: 0, opacity: 0 }
                                            }
                                            whileInView={
                                                reduced
                                                    ? undefined
                                                    : { scaleX: 1, opacity: 1 }
                                            }
                                            viewport={{
                                                once: true,
                                                amount: 0.5,
                                            }}
                                            transition={{
                                                duration: 0.5,
                                                ease: 'easeOut',
                                            }}
                                            style={{
                                                ...slot(window.from, window.to),
                                                originX: 0,
                                            }}
                                            className={`min-w-0 rounded-md border px-2.5 py-2 ${window.className}`}
                                        >
                                            <p className="truncate text-xs font-semibold">
                                                {window.name}
                                            </p>
                                            <p className="text-muted-foreground truncate font-mono text-[9px] tracking-wide">
                                                {window.meta}
                                            </p>
                                        </motion.div>
                                    </div>
                                ))}

                                <div className="border-border/60 mt-2 border-t pt-2">
                                    <div className="border-border/70 bg-muted/50 flex items-center justify-between gap-3 rounded-md border border-dashed px-2.5 py-2">
                                        <p className="text-xs font-semibold">
                                            Always-on Deployment
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[9px] tracking-wide uppercase">
                                            Fallback when no schedule matches
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </ProductFrame>
            </motion.div>

            <motion.div
                {...reveal(reduced)}
                variants={stagger(reduced)}
                className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
            >
                {NOTES.map(({ icon: Icon, title, body }) => (
                    <motion.div
                        key={title}
                        variants={fadeUp(reduced, 14)}
                        className="border-border/70 bg-card flex flex-col gap-2.5 rounded-xl border p-5"
                    >
                        <span className="bg-primary/10 text-primary flex size-8 items-center justify-center rounded-lg">
                            <Icon className="size-4" />
                        </span>
                        <h3 className="text-sm font-semibold">{title}</h3>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {body}
                        </p>
                    </motion.div>
                ))}
            </motion.div>
        </MarketingSection>
    );
}
