import { motion } from 'motion/react';
import {
    CheckCircle2Icon,
    ClockIcon,
    HistoryIcon,
    RefreshCwIcon,
    RotateCcwIcon,
} from 'lucide-react';
import ProductFrame, { StatusPill } from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    type MarketingImageKey,
    marketingImageAttrs,
} from '@/lib/marketing-images';
import {
    ambientLoop,
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

const NOTES = [
    {
        title: 'Publishing a design is not the same as going live',
        body: 'Publish Design finalises a version. Publish to TV is the separate, deliberate step that puts it on a display.',
    },
    {
        title: 'Editing never changes a live TV by accident',
        body: 'A deployment pins the exact published version. Your next round of edits stays off-air until you republish.',
    },
    {
        title: 'Live status comes from the devices themselves',
        body: 'Live, Updating, and Waiting are derived from Player check-ins — not from an optimistic "sent" flag.',
    },
] as const;

const ROWS: {
    name: string;
    kind: string;
    target: string;
    tone: 'live' | 'warning' | 'scheduled';
    label: string;
    icon: 'live' | 'updating' | 'waiting';
    imageKey: MarketingImageKey;
}[] = [
    {
        name: 'Lobby Welcome',
        kind: 'Screen Design · v4',
        target: '4 TVs',
        tone: 'live',
        label: 'Live',
        icon: 'live',
        imageKey: 'corporate',
    },
    {
        name: 'Store Promo Loop',
        kind: 'Playlist · v2',
        target: '6 TVs',
        tone: 'warning',
        label: 'Updating',
        icon: 'updating',
        imageKey: 'retail',
    },
    {
        name: 'Café Special',
        kind: 'Screen Design · v1',
        target: '2 TVs',
        tone: 'scheduled',
        label: 'Waiting',
        icon: 'waiting',
        imageKey: 'cafe',
    },
];

export default function PublishingSection() {
    const reduced = usePrefersReducedMotion();
    const pulse = ambientLoop(reduced, 2.4);
    const spin = reduced
        ? null
        : { duration: 2.6, repeat: Infinity, ease: 'linear' as const };

    return (
        <MarketingSection>
            <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <SectionHeading
                        align="left"
                        eyebrow="Publishing"
                        title="Know what is actually on each TV right now"
                        description="One operational centre for Live, Scheduled, and History — with Republish a click away."
                    />

                    <motion.ul
                        {...reveal(reduced)}
                        variants={stagger(reduced)}
                        className="mt-10 flex flex-col gap-5"
                    >
                        {NOTES.map(({ title, body }) => (
                            <motion.li
                                key={title}
                                variants={fadeUp(reduced, 14)}
                                className="flex gap-4"
                            >
                                <CheckCircle2Icon className="text-primary mt-0.5 size-5 shrink-0" />
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

                <motion.div {...reveal(reduced)} variants={fadeUp(reduced, 24)}>
                    <ProductFrame
                        label="rmsignage.app/app/publishing"
                        bodyClassName="p-4 sm:p-5"
                    >
                        {/* Static mock of the publishing centre, not a control. */}
                        <div className="border-border/70 bg-muted/60 mb-4 flex gap-1 rounded-lg border p-1">
                            {[
                                { label: 'Live', icon: CheckCircle2Icon },
                                { label: 'Scheduled', icon: ClockIcon },
                                { label: 'History', icon: HistoryIcon },
                            ].map(({ label, icon: Icon }, index) => (
                                <span
                                    key={label}
                                    className={
                                        index === 0
                                            ? 'bg-card flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold shadow-sm'
                                            : 'text-muted-foreground flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium'
                                    }
                                >
                                    <Icon className="size-3.5" />
                                    {label}
                                </span>
                            ))}
                        </div>

                        <ul className="flex flex-col gap-2">
                            {ROWS.map((row) => (
                                <li
                                    key={row.name}
                                    className="border-border/70 bg-background flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex min-w-0 items-center gap-3">
                                        <div className="relative size-12 shrink-0 overflow-hidden rounded-md bg-slate-950">
                                            <img
                                                {...marketingImageAttrs({
                                                    imageKey: row.imageKey,
                                                })}
                                                className="size-full object-cover"
                                            />
                                        </div>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {row.name}
                                            </p>
                                            <p className="text-muted-foreground truncate font-mono text-[10px] tracking-wide uppercase">
                                                {row.kind} · {row.target}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <StatusPill
                                            tone={row.tone}
                                            icon={
                                                row.icon === 'live' ? (
                                                    <motion.span
                                                        animate={
                                                            pulse
                                                                ? {
                                                                      opacity: [
                                                                          1,
                                                                          0.3,
                                                                          1,
                                                                      ],
                                                                  }
                                                                : undefined
                                                        }
                                                        transition={
                                                            pulse ?? undefined
                                                        }
                                                        className="size-1.5 rounded-full bg-current"
                                                    />
                                                ) : row.icon === 'updating' ? (
                                                    <motion.span
                                                        animate={
                                                            spin
                                                                ? {
                                                                      rotate: 360,
                                                                  }
                                                                : undefined
                                                        }
                                                        transition={
                                                            spin ?? undefined
                                                        }
                                                        className="flex"
                                                    >
                                                        <RefreshCwIcon className="size-3" />
                                                    </motion.span>
                                                ) : (
                                                    <ClockIcon className="size-3" />
                                                )
                                            }
                                        >
                                            {row.label}
                                        </StatusPill>
                                        <span
                                            className="border-border/70 text-muted-foreground hidden items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-medium sm:flex"
                                            aria-hidden
                                        >
                                            <RotateCcwIcon className="size-3" />
                                            Republish
                                        </span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </ProductFrame>
                </motion.div>
            </div>
        </MarketingSection>
    );
}
