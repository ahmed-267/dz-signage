import { motion } from 'motion/react';
import {
    CloudOffIcon,
    DatabaseIcon,
    RefreshCwIcon,
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';
import { ScreenFrame, StatusPill } from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

const POINTS = [
    {
        icon: DatabaseIcon,
        title: 'The package is stored on the device',
        body: 'Each Player keeps its active content package locally, along with the media files its designs actually need.',
    },
    {
        icon: CloudOffIcon,
        title: 'Losing the network changes nothing on screen',
        body: 'Playback continues from the cached package instead of falling back to a blank screen or an error page.',
    },
    {
        icon: RefreshCwIcon,
        title: 'Schedules keep switching offline',
        body: 'Upcoming windows are precomputed by the server, so a scheduled changeover still happens while the connection is down.',
    },
    {
        icon: WifiIcon,
        title: 'It reconciles on reconnect',
        body: 'When the network returns, the Player checks in, picks up anything it missed, and swaps packages atomically.',
    },
] as const;

const TIMELINE = [
    {
        time: '09:02',
        label: 'Online',
        tone: 'online' as const,
        note: 'Package synced',
    },
    {
        time: '09:14',
        label: 'Offline',
        tone: 'offline' as const,
        note: 'Still playing cached content',
    },
    {
        time: '09:41',
        label: 'Online',
        tone: 'online' as const,
        note: 'Reconciled automatically',
    },
];

export default function OfflineSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection className="border-border/70 bg-muted/30 border-y">
            <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced, 24)}
                    className="order-2 flex flex-col gap-5 lg:order-1"
                >
                    <ScreenFrame stand>
                        <div className="flex h-full flex-col justify-between bg-gradient-to-br from-cyan-500/20 via-slate-950 to-slate-950 p-4 sm:p-6">
                            <div className="flex items-start justify-between gap-3">
                                <p className="font-mono text-[9px] tracking-[0.2em] text-cyan-300/80 uppercase sm:text-[11px]">
                                    Café Board
                                </p>
                                <span className="flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-2 py-0.5 font-mono text-[8px] tracking-wider text-white/60 uppercase backdrop-blur sm:text-[9px]">
                                    <WifiOffIcon className="size-2.5" />
                                    Offline · cached
                                </span>
                            </div>
                            <div>
                                <p className="font-display text-lg leading-tight font-semibold text-white sm:text-2xl">
                                    Today&apos;s Specials
                                </p>
                                <p className="mt-1 text-[10px] text-white/50 sm:text-xs">
                                    Served until 15:00
                                </p>
                            </div>
                            <div className="flex gap-1.5">
                                <span className="h-1 w-10 rounded-full bg-cyan-400/60" />
                                <span className="h-1 w-5 rounded-full bg-white/20" />
                                <span className="h-1 w-3 rounded-full bg-white/10" />
                            </div>
                        </div>
                    </ScreenFrame>

                    <ol className="border-border/70 bg-card flex flex-col gap-2 rounded-xl border p-4">
                        {TIMELINE.map(({ time, label, tone, note }) => (
                            <li
                                key={time}
                                className="flex flex-wrap items-center gap-2.5"
                            >
                                <span className="text-muted-foreground w-11 shrink-0 font-mono text-[11px]">
                                    {time}
                                </span>
                                <StatusPill
                                    tone={tone}
                                    icon={
                                        tone === 'online' ? (
                                            <WifiIcon className="size-3" />
                                        ) : (
                                            <WifiOffIcon className="size-3" />
                                        )
                                    }
                                >
                                    {label}
                                </StatusPill>
                                <span className="text-muted-foreground text-xs">
                                    {note}
                                </span>
                            </li>
                        ))}
                    </ol>
                </motion.div>

                <div className="order-1 lg:order-2">
                    <SectionHeading
                        align="left"
                        eyebrow="Reliability"
                        title="A dropped connection is not a blank screen"
                        description="Displays live in cafés, corridors, and shop windows — places where Wi-Fi is not a guarantee."
                    />

                    <motion.ul
                        {...reveal(reduced)}
                        variants={stagger(reduced)}
                        className="mt-10 flex flex-col gap-5"
                    >
                        {POINTS.map(({ icon: Icon, title, body }) => (
                            <motion.li
                                key={title}
                                variants={fadeUp(reduced, 14)}
                                className="flex gap-4"
                            >
                                <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                                    <Icon className="size-4.5" />
                                </span>
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        {title}
                                    </h3>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        {body}
                                    </p>
                                </div>
                            </motion.li>
                        ))}
                    </motion.ul>
                </div>
            </div>
        </MarketingSection>
    );
}
