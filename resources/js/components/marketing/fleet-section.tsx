import { motion } from 'motion/react';
import {
    CheckCircle2Icon,
    PauseCircleIcon,
    RefreshCwIcon,
    WifiIcon,
    WifiOffIcon,
} from 'lucide-react';
import ProductFrame, {
    MockLine,
    StatusPill,
    type StatusTone,
} from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    type MarketingImageKey,
    marketingImageAttrs,
} from '@/lib/marketing-images';
import {
    fadeUp,
    reveal,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

type ScreenContent =
    | { eyebrow: string; title: string; imageKey: MarketingImageKey }
    | { dark: string };

type FleetScreen = {
    name: string;
    location: string;
    orientation: 'landscape' | 'portrait';
    network: { tone: StatusTone; label: string; offline?: boolean };
    sync: {
        tone: StatusTone;
        label: string;
        kind: 'ok' | 'updating' | 'paused';
    };
    /** What the display is showing, or why it is showing nothing. */
    content: ScreenContent;
};

const SCREENS: FleetScreen[] = [
    {
        name: 'Lobby TV',
        location: 'Northgate · Level 1',
        orientation: 'landscape',
        network: { tone: 'online', label: 'Online' },
        sync: { tone: 'live', label: 'Up to date', kind: 'ok' },
        content: {
            eyebrow: 'Reception',
            title: 'Welcome to Northgate',
            imageKey: 'corporate',
        },
    },
    {
        name: 'Café Board',
        location: 'Northgate · Ground',
        orientation: 'landscape',
        network: { tone: 'online', label: 'Online' },
        sync: { tone: 'live', label: 'Up to date', kind: 'ok' },
        content: {
            eyebrow: 'Until 15:00',
            title: "Today's Specials",
            imageKey: 'cafe',
        },
    },
    {
        name: 'Entrance Portrait',
        location: 'Riverside · Foyer',
        orientation: 'portrait',
        network: { tone: 'online', label: 'Online' },
        sync: { tone: 'warning', label: 'Updating', kind: 'updating' },
        content: {
            eyebrow: 'Foyer',
            title: 'Visitor Sign-In',
            imageKey: 'hotel',
        },
    },
    {
        name: 'Meeting Room 2',
        location: 'Northgate · Level 3',
        orientation: 'landscape',
        network: { tone: 'offline', label: 'Offline', offline: true },
        sync: { tone: 'scheduled', label: 'Last known content', kind: 'ok' },
        content: { dark: 'Last seen 14m ago' },
    },
    {
        name: 'Store Window',
        location: 'Elm Park · Retail',
        orientation: 'portrait',
        network: { tone: 'online', label: 'Online' },
        sync: { tone: 'live', label: 'Up to date', kind: 'ok' },
        content: {
            eyebrow: 'This week',
            title: 'Mid-Season Sale',
            imageKey: 'retail',
        },
    },
    {
        name: 'Warehouse Gate',
        location: 'Trade Quarter',
        orientation: 'landscape',
        network: { tone: 'offline', label: 'Offline', offline: true },
        sync: { tone: 'offline', label: 'Inactive', kind: 'paused' },
        content: { dark: 'Deactivated by a business admin' },
    },
];

const SYNC_ICONS = {
    ok: CheckCircle2Icon,
    updating: RefreshCwIcon,
    paused: PauseCircleIcon,
} as const;

/** Thumbnail of what the display is actually showing right now. */
function ScreenThumbnail({ screen }: { screen: FleetScreen }) {
    if ('dark' in screen.content) {
        return (
            <div className="flex h-full flex-col items-center justify-center gap-1.5 bg-slate-950">
                <WifiOffIcon className="size-4 text-white/25" />
                <span className="font-mono text-[8px] tracking-widest text-white/35 uppercase">
                    {screen.content.dark}
                </span>
            </div>
        );
    }

    const design = (
        <div className="relative flex h-full flex-col justify-between overflow-hidden bg-slate-950">
            <img
                {...marketingImageAttrs({
                    imageKey: screen.content.imageKey,
                })}
                className="absolute inset-0 size-full object-cover"
            />
            <div className="relative flex h-full flex-col justify-between bg-gradient-to-t from-black/80 via-black/25 to-black/20 p-2.5">
                <p className="font-mono text-[7px] tracking-[0.18em] text-cyan-200/90 uppercase">
                    {screen.content.eyebrow}
                </p>
                <p className="font-display text-[11px] leading-tight font-semibold text-white">
                    {screen.content.title}
                </p>
            </div>
        </div>
    );

    // Portrait Screens are letterboxed, exactly as the Screens library shows them.
    if (screen.orientation === 'portrait') {
        return (
            <div className="flex h-full items-center justify-center bg-black">
                <div className="aspect-[9/16] h-full overflow-hidden">
                    {design}
                </div>
            </div>
        );
    }

    return design;
}

export default function FleetSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection className="border-border/70 bg-muted/30 border-y">
            <SectionHeading
                eyebrow="TVs"
                title="See every display without walking the building"
                description="Presence comes from real Player heartbeats — no synthetic uptime, no invented CPU graphs. Operational, pairing, network, and content sync are tracked separately so you always know which one is wrong."
            />

            <motion.div
                {...reveal(reduced)}
                variants={fadeUp(reduced, 24)}
                className="mt-14"
            >
                <ProductFrame
                    label="rmsignage.app/app/screens"
                    badge={
                        <span className="text-muted-foreground font-mono text-[10px] tracking-wide uppercase">
                            6 TVs
                        </span>
                    }
                    bodyClassName="p-4 sm:p-6"
                >
                    <div className="mb-5 flex flex-wrap items-center gap-2">
                        {['All', 'Online', 'Offline', 'Out of sync'].map(
                            (filter, index) => (
                                <span
                                    key={filter}
                                    className={
                                        index === 0
                                            ? 'bg-primary text-primary-foreground rounded-full px-3 py-1 text-xs font-medium'
                                            : 'border-border/70 text-muted-foreground rounded-full border px-3 py-1 text-xs font-medium'
                                    }
                                >
                                    {filter}
                                </span>
                            ),
                        )}
                        <span className="border-border/70 bg-muted/60 ml-auto hidden rounded-md border px-3 py-1.5 sm:block">
                            <MockLine className="h-2 w-24" />
                        </span>
                    </div>

                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {SCREENS.map((screen) => {
                            const SyncIcon = SYNC_ICONS[screen.sync.kind];

                            return (
                                <li
                                    key={screen.name}
                                    className="border-border/70 bg-background flex flex-col gap-3 rounded-lg border p-3"
                                >
                                    <div className="relative aspect-video w-full overflow-hidden rounded bg-slate-950">
                                        <ScreenThumbnail screen={screen} />
                                    </div>

                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {screen.name}
                                        </p>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {screen.location} ·{' '}
                                            <span className="font-mono tracking-wide uppercase">
                                                {screen.orientation}
                                            </span>
                                        </p>
                                    </div>

                                    <div className="flex flex-wrap gap-1.5">
                                        <StatusPill
                                            tone={screen.network.tone}
                                            icon={
                                                screen.network.offline ? (
                                                    <WifiOffIcon className="size-3" />
                                                ) : (
                                                    <WifiIcon className="size-3" />
                                                )
                                            }
                                        >
                                            {screen.network.label}
                                        </StatusPill>
                                        <StatusPill
                                            tone={screen.sync.tone}
                                            icon={
                                                <SyncIcon className="size-3" />
                                            }
                                        >
                                            {screen.sync.label}
                                        </StatusPill>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </ProductFrame>
            </motion.div>
        </MarketingSection>
    );
}
