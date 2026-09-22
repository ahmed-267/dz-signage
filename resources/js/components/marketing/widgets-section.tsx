import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import {
    CalendarDaysIcon,
    ClockIcon,
    CloudSunIcon,
    NewspaperIcon,
    TimerIcon,
} from 'lucide-react';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

function WidgetCard({
    icon: Icon,
    name,
    note,
    children,
}: {
    icon: typeof ClockIcon;
    name: string;
    note: string;
    children: ReactNode;
}) {
    const reduced = usePrefersReducedMotion();

    return (
        <motion.li
            variants={fadeUp(reduced, 14)}
            className="border-border/70 bg-card hover:border-primary/40 flex flex-col gap-3 rounded-xl border p-4 transition-colors"
        >
            <div className="flex items-center gap-2">
                <span className="bg-primary/10 text-primary flex size-7 items-center justify-center rounded-md">
                    <Icon className="size-3.5" />
                </span>
                <h3 className="text-sm font-semibold">{name}</h3>
            </div>
            <div className="flex h-[92px] flex-col justify-center rounded-lg bg-slate-950 p-3">
                {children}
            </div>
            <p className="text-muted-foreground text-xs leading-relaxed">
                {note}
            </p>
        </motion.li>
    );
}

export default function WidgetsSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection>
            <SectionHeading
                eyebrow="Widgets"
                title="Live data, placed like any other element"
                description="Widgets sit on the same canvas as your text and media, and render through the same engine in the editor, the preview, and the Player."
            />

            <motion.ul
                {...reveal(reduced)}
                variants={stagger(reduced, 0.05)}
                className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <WidgetCard
                    icon={ClockIcon}
                    name="Clock"
                    note="Digital or analogue, in the timezone you choose."
                >
                    <p className="font-mono text-2xl leading-none font-bold text-white">
                        14:32
                    </p>
                    <p className="mt-1.5 font-mono text-[9px] tracking-widest text-cyan-300/70 uppercase">
                        Europe/London
                    </p>
                </WidgetCard>

                <WidgetCard
                    icon={CloudSunIcon}
                    name="Weather"
                    note="Fetched server-side and cached, with an offline snapshot."
                >
                    <div className="flex items-center gap-2.5">
                        <CloudSunIcon className="size-7 text-amber-300" />
                        <div>
                            <p className="font-mono text-xl leading-none font-bold text-white">
                                18°
                            </p>
                            <p className="mt-1 text-[9px] text-white/50">
                                Partly cloudy
                            </p>
                        </div>
                    </div>
                </WidgetCard>

                <WidgetCard
                    icon={TimerIcon}
                    name="Countdown"
                    note="Counts down to a date and time, then shows your message."
                >
                    <div className="flex items-end gap-2">
                        {[
                            ['02', 'days'],
                            ['14', 'hrs'],
                            ['08', 'min'],
                        ].map(([value, unit]) => (
                            <div key={unit}>
                                <p className="font-mono text-lg leading-none font-bold text-white">
                                    {value}
                                </p>
                                <p className="mt-1 font-mono text-[8px] tracking-widest text-white/40 uppercase">
                                    {unit}
                                </p>
                            </div>
                        ))}
                    </div>
                </WidgetCard>

                <WidgetCard
                    icon={NewspaperIcon}
                    name="News"
                    note="Any RSS feed, validated against SSRF before it is fetched."
                >
                    <p className="font-mono text-[8px] tracking-widest text-cyan-300/70 uppercase">
                        RSS feed
                    </p>
                    <div className="mt-2 flex flex-col gap-1.5">
                        <span className="h-1.5 w-full rounded-full bg-white/25" />
                        <span className="h-1.5 w-4/5 rounded-full bg-white/15" />
                        <span className="h-1.5 w-2/3 rounded-full bg-white/10" />
                    </div>
                </WidgetCard>

                <WidgetCard
                    icon={CalendarDaysIcon}
                    name="Calendar"
                    note="Upcoming entries from a public ICS calendar URL."
                >
                    <div className="flex flex-col gap-1.5">
                        {[
                            ['09:30', 'Team stand-up'],
                            ['13:00', 'Client visit'],
                            ['16:15', 'Induction'],
                        ].map(([time, title]) => (
                            <div key={time} className="flex items-center gap-2">
                                <span className="font-mono text-[9px] text-cyan-300/70">
                                    {time}
                                </span>
                                <span className="truncate text-[9px] text-white/60">
                                    {title}
                                </span>
                            </div>
                        ))}
                    </div>
                </WidgetCard>
            </motion.ul>

            <motion.p
                {...reveal(reduced)}
                variants={fadeUp(reduced)}
                className="text-muted-foreground mt-8 text-center text-sm"
            >
                Alert, Info Card, and Embed widgets are available too — and
                Clock, Countdown, Alert, and Info Card keep working with no
                network at all.
            </motion.p>
        </MarketingSection>
    );
}
