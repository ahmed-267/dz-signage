import type { ReactNode } from 'react';
import { motion } from 'motion/react';
import {
    CalendarClockIcon,
    CloudOffIcon,
    ImageIcon,
    LayoutTemplateIcon,
    ListOrderedIcon,
    PenToolIcon,
    SquareActivityIcon,
    UsersIcon,
    ZapIcon,
} from 'lucide-react';
import { MockLine, StatusPill } from '@/components/marketing/product-frame';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import { marketingImageAttrs } from '@/lib/marketing-images';
import {
    fadeUp,
    reveal,
    stagger,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { cn } from '@/lib/utils';

function FeatureCard({
    icon: Icon,
    title,
    body,
    className,
    children,
}: {
    icon: typeof PenToolIcon;
    title: string;
    body: string;
    className?: string;
    children?: ReactNode;
}) {
    const reduced = usePrefersReducedMotion();

    return (
        <motion.div
            variants={fadeUp(reduced)}
            className={cn(
                'border-border/70 bg-card hover:border-primary/40 flex flex-col gap-3 rounded-xl border p-6 transition-colors',
                className,
            )}
        >
            <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                <Icon className="size-4.5" />
            </span>
            <h3 className="font-display text-lg font-semibold">{title}</h3>
            <p className="text-muted-foreground text-sm leading-relaxed">
                {body}
            </p>
            {children}
        </motion.div>
    );
}

export default function FeatureShowcase() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection
            id="features"
            className="bg-muted/30 border-border/70 border-y"
        >
            <SectionHeading
                eyebrow="Platform"
                title="Everything a TV network actually needs"
                description="Design, schedule, publish, and keep TVs healthy — including offline cache and live widgets — in one business."
            />

            <motion.div
                {...reveal(reduced)}
                variants={stagger(reduced, 0.05)}
                className="mt-14 grid gap-4 lg:grid-cols-6"
            >
                <FeatureCard
                    icon={PenToolIcon}
                    title="Screen Design editor"
                    body="Drag, resize, and layer text, media, and widgets on a 1920×1080 or 1080×1920 canvas. Undo history included, drafts saved as you work."
                    className="lg:col-span-4"
                >
                    <div className="border-border/70 bg-muted/40 mt-2 grid flex-1 grid-cols-[auto_1fr_auto] gap-3 rounded-lg border p-3">
                        <div className="flex flex-col gap-1.5">
                            {Array.from({ length: 4 }).map((_, index) => (
                                <span
                                    key={index}
                                    aria-hidden
                                    className={
                                        index === 1
                                            ? 'bg-primary/20 ring-primary/40 size-6 rounded ring-1'
                                            : 'bg-foreground/5 size-6 rounded'
                                    }
                                />
                            ))}
                        </div>
                        <div className="relative overflow-hidden rounded bg-slate-950">
                            <img
                                {...marketingImageAttrs({ imageKey: 'cafe' })}
                                className="absolute inset-0 size-full object-cover opacity-80"
                            />
                            <div className="relative p-3">
                                <p className="font-mono text-[9px] tracking-wider text-amber-200/90 uppercase">
                                    Café promo
                                </p>
                                <p className="font-display mt-1 text-sm font-semibold text-white">
                                    Iced Latte · 40% OFF
                                </p>
                                <div className="mt-3 flex gap-1.5">
                                    <span className="rounded bg-black/45 px-1.5 py-0.5 font-mono text-[8px] text-white/80">
                                        Clock
                                    </span>
                                    <span className="rounded bg-black/45 px-1.5 py-0.5 font-mono text-[8px] text-white/80">
                                        Weather
                                    </span>
                                </div>
                            </div>
                            <span
                                aria-hidden
                                className="pointer-events-none absolute inset-2 rounded ring-1 ring-amber-400/30"
                            />
                        </div>
                        <div className="hidden w-24 flex-col gap-1.5 sm:flex">
                            <MockLine className="h-2 w-12" tone="strong" />
                            <MockLine className="h-5 w-full" />
                            <MockLine className="h-5 w-full" />
                            <MockLine className="h-5 w-2/3" />
                        </div>
                    </div>
                </FeatureCard>

                <FeatureCard
                    icon={LayoutTemplateIcon}
                    title="Templates"
                    body="Browse a curated, platform-maintained library, preview it live, favourite what you like, and Use Template to copy it into an independent Screen Design."
                    className="lg:col-span-2"
                >
                    <ul className="text-muted-foreground mt-2 flex flex-col gap-1.5 font-mono text-[11px] tracking-wide uppercase">
                        <li>Landscape &amp; portrait</li>
                        <li>Live schema previews</li>
                        <li>Yours to edit after copying</li>
                    </ul>
                </FeatureCard>

                <FeatureCard
                    icon={ImageIcon}
                    title="Media Library"
                    body="Images, video, logos, text, and links per business. Replace a file and every design keeps its reference; delete is blocked while a design still uses it."
                    className="lg:col-span-2"
                >
                    <div className="mt-2 grid grid-cols-3 gap-1.5">
                        {(['cafe', 'retail', 'corporate'] as const).map(
                            (key) => (
                                <div
                                    key={key}
                                    className="relative aspect-square overflow-hidden rounded-md bg-slate-950"
                                >
                                    <img
                                        {...marketingImageAttrs({
                                            imageKey: key,
                                        })}
                                        className="size-full object-cover"
                                    />
                                </div>
                            ),
                        )}
                    </div>
                </FeatureCard>

                <FeatureCard
                    icon={ListOrderedIcon}
                    title="Playlists"
                    body="Ordered, single-orientation lists of published designs. Set duration, transition, and transition speed per item, then publish the Playlist as a version."
                    className="lg:col-span-2"
                />

                <FeatureCard
                    icon={CalendarClockIcon}
                    title="Control what plays"
                    body="Schedules pin published Playlists to wall-clock windows with timezones, weekdays, overnight ranges, and priority. Publishing centre shows Live, Scheduled, and History — republish in a click; deployments stay active so offline TVs reconcile when they reconnect."
                    className="lg:col-span-5"
                >
                    <div className="border-border/70 bg-muted/40 mt-2 flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-stretch sm:gap-3">
                        {[
                            {
                                name: 'Lobby Welcome',
                                tone: 'live' as const,
                                label: 'Live',
                            },
                            {
                                name: 'Lunch Menu',
                                tone: 'scheduled' as const,
                                label: 'Scheduled',
                            },
                            {
                                name: 'Store Promo',
                                tone: 'warning' as const,
                                label: 'Waiting',
                            },
                        ].map(({ name, tone, label }) => (
                            <div
                                key={name}
                                className="border-border/60 bg-card flex flex-1 items-center justify-between gap-3 rounded-md border px-3 py-2"
                            >
                                <span className="truncate text-xs font-medium">
                                    {name}
                                </span>
                                <StatusPill tone={tone}>{label}</StatusPill>
                            </div>
                        ))}
                    </div>
                </FeatureCard>

                <FeatureCard
                    icon={SquareActivityIcon}
                    title="TV health"
                    body="Four independent axes, derived from real Player heartbeats — never guessed, never synthetic."
                    className="lg:col-span-3"
                >
                    <dl className="mt-2 grid gap-2 sm:grid-cols-2">
                        {[
                            ['Operational', 'Active / Inactive'],
                            ['Pairing', 'Connected / Disconnected'],
                            ['Network', 'Online / Offline'],
                            ['Content sync', 'Up to date / Out of sync'],
                        ].map(([axis, values]) => (
                            <div
                                key={axis}
                                className="border-border/60 bg-muted/40 rounded-md border px-3 py-2"
                            >
                                <dt className="text-muted-foreground font-mono text-[10px] tracking-[0.14em] uppercase">
                                    {axis}
                                </dt>
                                <dd className="mt-1 text-xs font-medium">
                                    {values}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </FeatureCard>

                <FeatureCard
                    icon={CloudOffIcon}
                    title="Offline & widgets"
                    body="Players cache their package locally so a dropped connection does not blank the screen. Clock, Weather, News, Countdown, and Embed widgets render through the same engine online and offline."
                    className="lg:col-span-2"
                />

                <FeatureCard
                    icon={ZapIcon}
                    title="Live data on canvas"
                    body="Place widgets like any other element — SSRF-safe remote fetches for Weather, News, and Embed, with graceful fallbacks when data is unavailable."
                    className="lg:col-span-2"
                />

                <FeatureCard
                    icon={UsersIcon}
                    title="Team"
                    body="Invite your team with real roles: Owner, Admin, Designer, and Content Manager. Permissions are enforced server-side."
                    className="lg:col-span-2"
                />
            </motion.div>
        </MarketingSection>
    );
}
