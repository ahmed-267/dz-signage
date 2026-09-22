import { motion } from 'motion/react';
import {
    CalendarClockIcon,
    LayoutTemplateIcon,
    ListOrderedIcon,
    MonitorIcon,
    QrCodeIcon,
    SendIcon,
    TvIcon,
} from 'lucide-react';
import { ScreenFrame } from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    type MarketingImageKey,
    marketingImageAttrs,
} from '@/lib/marketing-images';
import {
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

const STEPS: {
    icon: typeof LayoutTemplateIcon;
    title: string;
    body: string;
    imageKey: MarketingImageKey;
}[] = [
    {
        icon: LayoutTemplateIcon,
        title: 'Design',
        body: 'Start from a Template or a blank canvas. Drop in Media and Widgets, then Publish Design to finalise a version.',
        imageKey: 'cafe',
    },
    {
        icon: ListOrderedIcon,
        title: 'Playlist',
        body: 'Order published Screen Design versions into a Playlist, with per-item duration and transitions.',
        imageKey: 'restaurant',
    },
    {
        icon: CalendarClockIcon,
        title: 'Schedule',
        body: 'Pin a published Playlist version to TVs for a wall-clock window — days, dates, timezone, overnight.',
        imageKey: 'retail',
    },
    {
        icon: SendIcon,
        title: 'Publish',
        body: 'Publish to TV deploys the published version. Later edits stay off-air until you republish.',
        imageKey: 'corporate',
    },
    {
        icon: MonitorIcon,
        title: 'Display',
        body: 'Paired TVs pick up changes on their next check-in and keep playing if the network drops.',
        imageKey: 'hotel',
    },
];

const CHAIN = [
    'Media',
    'Templates',
    'Screen Designs',
    'Playlists',
    'Schedules',
    'Publishing',
    'TVs',
];

const REACH_TV = [
    {
        icon: TvIcon,
        title: 'Open the Player',
        body: 'Any browser-capable TV, stick, or mini PC — no app-store install.',
    },
    {
        icon: QrCodeIcon,
        title: 'Pair with a code',
        body: 'The display shows a PIN and QR. Claim it from Paired TVs in about a minute.',
    },
    {
        icon: MonitorIcon,
        title: 'Run a fleet',
        body: 'See Online / Offline, Connected, and content sync from real heartbeats across every location.',
    },
] as const;

export default function WorkflowSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection id="product">
            <SectionHeading
                eyebrow="How it works"
                title="From blank canvas to live TV in five steps"
                description="Each stage is a real object in the platform — versioned, reviewable, and reversible."
            />

            <motion.ol
                {...reveal(reduced)}
                variants={stagger(reduced)}
                className="mt-14 grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                {STEPS.map(({ icon: Icon, title, body, imageKey }, index) => (
                    <motion.li
                        key={title}
                        variants={fadeUp(reduced)}
                        className="border-border/70 bg-card hover:border-primary/40 relative flex flex-col gap-3 rounded-xl border p-5 transition-colors"
                    >
                        <ScreenFrame>
                            <div className="relative size-full">
                                <img
                                    {...marketingImageAttrs({ imageKey })}
                                    className="absolute inset-0 size-full object-cover"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/55 to-transparent" />
                                <span className="absolute right-2 bottom-2 rounded bg-black/50 px-1.5 py-0.5 font-mono text-[8px] tracking-wide text-white uppercase">
                                    {title}
                                </span>
                            </div>
                        </ScreenFrame>
                        <div className="flex items-center justify-between">
                            <span className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg">
                                <Icon className="size-4.5" />
                            </span>
                            <span className="text-muted-foreground/60 font-mono text-xs font-semibold">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                        </div>
                        <h3 className="font-display text-lg font-semibold">
                            {title}
                        </h3>
                        <p className="text-muted-foreground text-sm leading-relaxed">
                            {body}
                        </p>
                        {index < STEPS.length - 1 && (
                            <span
                                aria-hidden
                                className="via-border absolute top-1/2 -right-4 hidden h-px w-4 bg-gradient-to-r from-transparent to-transparent lg:block"
                            />
                        )}
                    </motion.li>
                ))}
            </motion.ol>

            <motion.div
                {...reveal(reduced)}
                variants={fadeUp(reduced)}
                className="border-border/70 bg-muted/40 mt-10 flex flex-wrap items-center justify-center gap-x-2 gap-y-2 rounded-xl border px-5 py-4"
            >
                {CHAIN.map((item, index) => (
                    <span key={item} className="flex items-center gap-2">
                        <span className="font-mono text-[11px] tracking-wide uppercase">
                            {item}
                        </span>
                        {index < CHAIN.length - 1 && (
                            <span
                                aria-hidden
                                className="text-muted-foreground/50 text-xs"
                            >
                                →
                            </span>
                        )}
                    </span>
                ))}
            </motion.div>

            <motion.div
                {...reveal(reduced)}
                variants={stagger(reduced, 0.05)}
                className="mt-14"
            >
                <SectionHeading
                    eyebrow="Reach every TV"
                    title="Pair once, run the fleet"
                    description="Open the Player URL on the display, claim it with a PIN or QR, then publish. Heartbeats keep Online / Offline and sync honest across locations."
                />
                <div className="mt-8 grid gap-4 sm:grid-cols-3">
                    {REACH_TV.map(({ icon: Icon, title, body }) => (
                        <motion.div
                            key={title}
                            variants={fadeUp(reduced)}
                            className="border-border/70 bg-card flex flex-col gap-2 rounded-xl border p-5"
                        >
                            <span className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-lg">
                                <Icon className="size-4.5" />
                            </span>
                            <h3 className="font-display text-base font-semibold">
                                {title}
                            </h3>
                            <p className="text-muted-foreground text-sm leading-relaxed">
                                {body}
                            </p>
                        </motion.div>
                    ))}
                </div>
            </motion.div>
        </MarketingSection>
    );
}
