import { motion } from 'motion/react';
import {
    CheckIcon,
    MonitorIcon,
    QrCodeIcon,
    SendIcon,
    TvIcon,
} from 'lucide-react';
import { ScreenFrame, StatusPill } from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    ambientLoop,
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';

const STEPS = [
    {
        icon: TvIcon,
        title: 'Open the Player on the display',
        body: 'Any browser-capable TV, stick, or mini PC loads the Player page. Nothing to install from an app store.',
    },
    {
        icon: QrCodeIcon,
        title: 'A pairing code appears',
        body: 'The display shows a short code and a QR link. Codes are single-use, rate limited, and expire after ten minutes.',
    },
    {
        icon: CheckIcon,
        title: 'Claim it in your Workspace',
        body: 'Type the code or scan the QR from your Paired TVs page. The TV becomes Connected and takes a licence.',
    },
    {
        icon: SendIcon,
        title: 'Publish to it',
        body: 'Send a published Screen Design or Playlist. The device token is issued once and never shown as a pairing code.',
    },
] as const;

/** Decorative QR pattern — deterministic so it renders identically everywhere. */
const QR_ROWS = [
    '111010111',
    '100010001',
    '101010101',
    '000111000',
    '110101011',
    '000110100',
    '101010101',
    '100011001',
    '111011111',
];

export default function PairingSection() {
    const reduced = usePrefersReducedMotion();
    const pulse = ambientLoop(reduced, 2.6);

    return (
        <MarketingSection>
            <div className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
                <div>
                    <SectionHeading
                        align="left"
                        eyebrow="Pairing"
                        title="Connect a display in about a minute"
                        description="No site visit, no USB stick, no per-device configuration file."
                    />

                    <motion.ol
                        {...reveal(reduced)}
                        variants={stagger(reduced)}
                        className="mt-10 flex flex-col gap-5"
                    >
                        {STEPS.map(({ icon: Icon, title, body }, index) => (
                            <motion.li
                                key={title}
                                variants={fadeUp(reduced, 14)}
                                className="flex gap-4"
                            >
                                <span className="bg-primary/10 text-primary relative flex size-9 shrink-0 items-center justify-center rounded-lg">
                                    <Icon className="size-4.5" />
                                    <span className="bg-primary text-primary-foreground absolute -top-1.5 -left-1.5 flex size-4 items-center justify-center rounded-full font-mono text-[9px] font-bold">
                                        {index + 1}
                                    </span>
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
                    </motion.ol>
                </div>

                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced, 24)}
                    className="flex flex-col gap-5"
                >
                    <ScreenFrame stand>
                        <div className="flex h-full flex-col items-center justify-center gap-4 bg-slate-950 p-4">
                            <p className="font-mono text-[9px] tracking-[0.24em] text-cyan-300/80 uppercase">
                                Pair this screen
                            </p>
                            <div className="flex items-center gap-4">
                                <div
                                    aria-hidden
                                    className="grid grid-cols-9 gap-[2px] rounded bg-white p-1.5"
                                >
                                    {QR_ROWS.flatMap((row, rowIndex) =>
                                        row
                                            .split('')
                                            .map((cell, cellIndex) => (
                                                <span
                                                    key={`${rowIndex}-${cellIndex}`}
                                                    className={
                                                        cell === '1'
                                                            ? 'size-[3px] bg-slate-950 sm:size-[4px]'
                                                            : 'size-[3px] bg-white sm:size-[4px]'
                                                    }
                                                />
                                            )),
                                    )}
                                </div>
                                <div className="text-left">
                                    <p className="font-mono text-xl font-bold tracking-[0.12em] text-white sm:text-3xl">
                                        DZ-4K7P
                                    </p>
                                    <p className="mt-1.5 font-mono text-[8px] tracking-wider text-white/45 uppercase sm:text-[10px]">
                                        Expires in 09:41
                                    </p>
                                </div>
                            </div>
                            <p className="text-[9px] text-white/40 sm:text-[11px]">
                                Scan or enter this code in your Workspace
                            </p>
                        </div>
                    </ScreenFrame>

                    <div className="border-border/70 bg-card flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                        <div className="flex items-center gap-2.5">
                            <span className="bg-success/10 text-success flex size-8 items-center justify-center rounded-lg">
                                <MonitorIcon className="size-4" />
                            </span>
                            <div>
                                <p className="text-sm font-medium">Lobby TV</p>
                                <p className="text-muted-foreground font-mono text-[10px] tracking-wide uppercase">
                                    Paired just now
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusPill
                                tone="online"
                                icon={<CheckIcon className="size-3" />}
                            >
                                Connected
                            </StatusPill>
                            <StatusPill
                                tone="live"
                                icon={
                                    <motion.span
                                        animate={
                                            pulse
                                                ? { opacity: [1, 0.3, 1] }
                                                : undefined
                                        }
                                        transition={pulse ?? undefined}
                                        className="size-1.5 rounded-full bg-current"
                                    />
                                }
                            >
                                Live
                            </StatusPill>
                        </div>
                    </div>
                </motion.div>
            </div>
        </MarketingSection>
    );
}
