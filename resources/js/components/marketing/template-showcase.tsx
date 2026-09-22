import { useState } from 'react';
import { AnimatePresence, motion } from 'motion/react';
import { ScreenFrame } from '@/components/marketing/product-frame';
import { ContinuousTabs } from '@/components/watermelon/continuous-tabs';
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

/**
 * Accent palettes belong to the signage design itself, not the app chrome —
 * a mock TV shows its own colours in light and dark mode alike.
 */
const ACCENTS = {
    emerald: {
        text: 'text-emerald-300',
        wash: 'from-emerald-500/25 via-slate-950 to-slate-950',
        panel: 'bg-emerald-500/15 ring-emerald-400/25',
        bar: 'bg-emerald-400/70',
    },
    amber: {
        text: 'text-amber-300',
        wash: 'from-amber-500/25 via-slate-950 to-slate-950',
        panel: 'bg-amber-500/15 ring-amber-400/25',
        bar: 'bg-amber-400/70',
    },
    rose: {
        text: 'text-rose-300',
        wash: 'from-rose-500/25 via-slate-950 to-slate-950',
        panel: 'bg-rose-500/15 ring-rose-400/25',
        bar: 'bg-rose-400/70',
    },
    cyan: {
        text: 'text-cyan-300',
        wash: 'from-cyan-500/25 via-slate-950 to-slate-950',
        panel: 'bg-cyan-500/15 ring-cyan-400/25',
        bar: 'bg-cyan-400/70',
    },
    violet: {
        text: 'text-violet-300',
        wash: 'from-violet-500/25 via-slate-950 to-slate-950',
        panel: 'bg-violet-500/15 ring-violet-400/25',
        bar: 'bg-violet-400/70',
    },
    sky: {
        text: 'text-sky-300',
        wash: 'from-sky-500/25 via-slate-950 to-slate-950',
        panel: 'bg-sky-500/15 ring-sky-400/25',
        bar: 'bg-sky-400/70',
    },
    teal: {
        text: 'text-teal-300',
        wash: 'from-teal-500/25 via-slate-950 to-slate-950',
        panel: 'bg-teal-500/15 ring-teal-400/25',
        bar: 'bg-teal-400/70',
    },
    lime: {
        text: 'text-lime-300',
        wash: 'from-lime-500/25 via-slate-950 to-slate-950',
        panel: 'bg-lime-500/15 ring-lime-400/25',
        bar: 'bg-lime-400/70',
    },
    fuchsia: {
        text: 'text-fuchsia-300',
        wash: 'from-fuchsia-500/25 via-slate-950 to-slate-950',
        panel: 'bg-fuchsia-500/15 ring-fuchsia-400/25',
        bar: 'bg-fuchsia-400/70',
    },
} as const;

type AccentId = keyof typeof ACCENTS;

type TemplateMockData = {
    name: string;
    eyebrow: string;
    title: string;
    subtitle: string;
    rows?: [string, string][];
    kind: 'hero' | 'list' | 'split';
    imageKey?: MarketingImageKey;
};

type Category = {
    id: string;
    label: string;
    blurb: string;
    accent: AccentId;
    templates: TemplateMockData[];
};

const CATEGORY_IMAGES: Record<string, MarketingImageKey> = {
    restaurant: 'restaurant',
    retail: 'retail',
    corporate: 'corporate',
    education: 'education',
    healthcare: 'healthcare',
    hotel: 'hotel',
    gym: 'gym',
    'real-estate': 'property',
    events: 'events',
};

const CATEGORIES: Category[] = [
    {
        id: 'restaurant',
        label: 'Restaurant',
        accent: 'amber',
        blurb: 'Menu boards, daily specials, and opening hours that switch by time of day.',
        templates: [
            {
                name: 'Lunch Menu',
                kind: 'list',
                eyebrow: 'Served 11:30 – 14:30',
                title: 'Lunch Menu',
                subtitle: 'Kitchen favourites',
                rows: [
                    ['Soup of the day', '£5.50'],
                    ['Grilled chicken', '£9.00'],
                    ['Seasonal salad', '£7.50'],
                    ['House dessert', '£4.00'],
                ],
            },
            {
                name: 'Daily Special',
                kind: 'hero',
                eyebrow: 'Tonight',
                title: 'Chef’s Special',
                subtitle: 'Slow-cooked lamb · available until 21:00',
            },
            {
                name: 'Opening Hours',
                kind: 'split',
                eyebrow: 'Visit',
                title: 'We’re Open',
                subtitle: 'Mon–Sat 11:30–22:00 · Sun 12:00–21:00',
            },
        ],
    },
    {
        id: 'retail',
        label: 'Retail',
        accent: 'rose',
        blurb: 'Promotions, new arrivals, and store directories across a whole estate.',
        templates: [
            {
                name: 'Promo Banner',
                kind: 'hero',
                eyebrow: 'This week',
                title: 'Mid-Season Sale',
                subtitle: 'Selected lines in store now',
            },
            {
                name: 'New Arrivals',
                kind: 'split',
                eyebrow: 'Just landed',
                title: 'New Arrivals',
                subtitle: 'Ask a colleague for sizes',
            },
            {
                name: 'Store Directory',
                kind: 'list',
                eyebrow: 'Directory',
                title: 'Find Your Floor',
                subtitle: 'Level guide',
                rows: [
                    ['Ground', 'Beauty · Gifts'],
                    ['Level 1', 'Womenswear'],
                    ['Level 2', 'Menswear'],
                    ['Level 3', 'Home'],
                ],
            },
        ],
    },
    {
        id: 'corporate',
        label: 'Corporate',
        accent: 'cyan',
        blurb: 'Reception welcomes, meeting room boards, and internal comms.',
        templates: [
            {
                name: 'Reception Welcome',
                kind: 'hero',
                eyebrow: 'Reception · Level 1',
                title: 'Welcome to Northgate',
                subtitle: 'Please sign in at the desk',
            },
            {
                name: 'Meeting Rooms',
                kind: 'list',
                eyebrow: 'Today',
                title: 'Meeting Rooms',
                subtitle: 'Room status',
                rows: [
                    ['Boardroom', '09:00 – 11:00'],
                    ['Ada Lovelace', 'Available'],
                    ['Turing', '13:30 – 15:00'],
                    ['Focus 2', 'Available'],
                ],
            },
            {
                name: 'Team Notice',
                kind: 'split',
                eyebrow: 'Notice',
                title: 'All-Hands Thursday',
                subtitle: '16:00 · Main atrium',
            },
        ],
    },
    {
        id: 'education',
        label: 'Education',
        accent: 'violet',
        blurb: 'Timetables, campus wayfinding, and exam notices for every corridor.',
        templates: [
            {
                name: 'Class Timetable',
                kind: 'list',
                eyebrow: 'Block B',
                title: 'Class Timetable',
                subtitle: 'Wednesday',
                rows: [
                    ['Period 1', 'Mathematics'],
                    ['Period 2', 'Physics'],
                    ['Period 3', 'History'],
                    ['Period 4', 'Art'],
                ],
            },
            {
                name: 'Campus Welcome',
                kind: 'hero',
                eyebrow: 'Main entrance',
                title: 'Welcome to Campus',
                subtitle: 'Visitor passes at reception',
            },
            {
                name: 'Exam Notice',
                kind: 'split',
                eyebrow: 'Examinations',
                title: 'Silence Please',
                subtitle: 'Exams in progress until 16:00',
            },
        ],
    },
    {
        id: 'healthcare',
        label: 'Healthcare',
        accent: 'sky',
        blurb: 'Waiting room information, clinic directories, and public health notices.',
        templates: [
            {
                name: 'Waiting Room',
                kind: 'split',
                eyebrow: 'Waiting area',
                title: 'Thank You for Waiting',
                subtitle: 'You will be called shortly',
            },
            {
                name: 'Clinic Directory',
                kind: 'list',
                eyebrow: 'Clinics',
                title: 'Today’s Clinics',
                subtitle: 'Department guide',
                rows: [
                    ['Audiology', 'Room 4'],
                    ['Dermatology', 'Room 7'],
                    ['Physiotherapy', 'Room 9'],
                    ['Phlebotomy', 'Room 2'],
                ],
            },
            {
                name: 'Health Notice',
                kind: 'hero',
                eyebrow: 'Reminder',
                title: 'Clean Hands Save Lives',
                subtitle: 'Gel points at every entrance',
            },
        ],
    },
    {
        id: 'hotel',
        label: 'Hotel',
        accent: 'teal',
        blurb: 'Guest welcomes, event boards, and facility information in the lobby.',
        templates: [
            {
                name: 'Guest Welcome',
                kind: 'hero',
                eyebrow: 'Lobby',
                title: 'Welcome, Guests',
                subtitle: 'Check-in from 15:00',
            },
            {
                name: 'Event Board',
                kind: 'list',
                eyebrow: 'Today',
                title: "What's On",
                subtitle: 'Function rooms',
                rows: [
                    ['Willow Suite', 'Conference'],
                    ['Oak Room', 'Private dining'],
                    ['Terrace', 'Reception 19:00'],
                    ['Cellar', 'Tasting 20:30'],
                ],
            },
            {
                name: 'Facilities',
                kind: 'split',
                eyebrow: 'Facilities',
                title: 'Spa & Pool',
                subtitle: 'Open 07:00 – 21:00 · Level -1',
            },
        ],
    },
    {
        id: 'gym',
        label: 'Gym',
        accent: 'lime',
        blurb: 'Class schedules, studio timetables, and membership promotions.',
        templates: [
            {
                name: 'Class Schedule',
                kind: 'list',
                eyebrow: 'Studio 1',
                title: 'Class Schedule',
                subtitle: 'Today',
                rows: [
                    ['07:00', 'Spin'],
                    ['09:30', 'Yoga'],
                    ['12:00', 'HIIT'],
                    ['18:00', 'Strength'],
                ],
            },
            {
                name: 'Membership Promo',
                kind: 'hero',
                eyebrow: 'Membership',
                title: 'Bring a Friend',
                subtitle: 'Ask at the front desk',
            },
            {
                name: 'Studio Timetable',
                kind: 'split',
                eyebrow: 'Studio 2',
                title: 'Next: HIIT 12:00',
                subtitle: '45 minutes · All levels',
            },
        ],
    },
    {
        id: 'real-estate',
        label: 'Real Estate',
        accent: 'cyan',
        blurb: 'Window displays, featured listings, and office welcome screens.',
        templates: [
            {
                name: 'Featured Listing',
                kind: 'split',
                eyebrow: 'Featured',
                title: '4 Bed Semi-Detached',
                subtitle: 'Northgate · Viewings this week',
            },
            {
                name: 'Listings Board',
                kind: 'list',
                eyebrow: 'Available now',
                title: 'Latest Listings',
                subtitle: 'Sales & lettings',
                rows: [
                    ['2 bed flat', 'Riverside'],
                    ['3 bed house', 'Elm Park'],
                    ['Studio', 'City centre'],
                    ['Office unit', 'Trade Quarter'],
                ],
            },
            {
                name: 'Office Welcome',
                kind: 'hero',
                eyebrow: 'Welcome',
                title: 'Talk to Our Team',
                subtitle: 'Valuations and viewings',
            },
        ],
    },
    {
        id: 'events',
        label: 'Events',
        accent: 'fuchsia',
        blurb: 'Countdowns, programme boards, and speaker spotlights for live events.',
        templates: [
            {
                name: 'Event Countdown',
                kind: 'hero',
                eyebrow: 'Doors open in',
                title: '00 : 14 : 32',
                subtitle: 'Main stage · Hall 2',
            },
            {
                name: 'Programme Board',
                kind: 'list',
                eyebrow: 'Programme',
                title: 'Main Stage',
                subtitle: 'Session times',
                rows: [
                    ['10:00', 'Opening keynote'],
                    ['11:30', 'Panel discussion'],
                    ['14:00', 'Workshops'],
                    ['16:30', 'Closing remarks'],
                ],
            },
            {
                name: 'Speaker Spotlight',
                kind: 'split',
                eyebrow: 'Up next',
                title: 'Keynote Session',
                subtitle: 'Hall 2 · 30 minutes',
            },
        ],
    },
];

function TemplateMock({
    template,
    accent,
    fallbackImageKey,
}: {
    template: TemplateMockData;
    accent: AccentId;
    fallbackImageKey: MarketingImageKey;
}) {
    const tone = ACCENTS[accent];
    const imageKey = template.imageKey ?? fallbackImageKey;

    if (template.kind === 'list') {
        return (
            <div className="relative flex h-full flex-col overflow-hidden bg-slate-950">
                {imageKey ? (
                    <img
                        {...marketingImageAttrs({ imageKey })}
                        className="absolute inset-0 size-full object-cover opacity-40"
                    />
                ) : null}
                <div
                    className={`relative flex h-full flex-col bg-gradient-to-br p-3 ${tone.wash}`}
                >
                    <p
                        className={`font-mono text-[7px] tracking-[0.18em] uppercase ${tone.text}`}
                    >
                        {template.eyebrow}
                    </p>
                    <p className="font-display mt-1 text-[13px] leading-none font-semibold text-white">
                        {template.title}
                    </p>
                    <div className="mt-2.5 flex flex-1 flex-col justify-center gap-1">
                        {template.rows?.map(([label, value]) => (
                            <div
                                key={label}
                                className="flex items-center justify-between gap-2 rounded bg-black/40 px-2 py-1 backdrop-blur-sm"
                            >
                                <span className="text-[8px] text-white/70">
                                    {label}
                                </span>
                                <span
                                    className={`font-mono text-[8px] font-semibold ${tone.text}`}
                                >
                                    {value}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    if (template.kind === 'split') {
        return (
            <div className="flex h-full bg-slate-950">
                <div className="flex flex-1 flex-col justify-center gap-1.5 p-3">
                    <p
                        className={`font-mono text-[7px] tracking-[0.18em] uppercase ${tone.text}`}
                    >
                        {template.eyebrow}
                    </p>
                    <p className="font-display text-[13px] leading-tight font-semibold text-white">
                        {template.title}
                    </p>
                    <p className="text-[8px] leading-snug text-white/55">
                        {template.subtitle}
                    </p>
                </div>
                <div className="relative w-2/5 overflow-hidden">
                    {imageKey ? (
                        <img
                            {...marketingImageAttrs({ imageKey })}
                            className="absolute inset-0 size-full object-cover"
                        />
                    ) : (
                        <div
                            className={`flex h-full flex-col justify-end gap-1 p-2.5 ring-1 ring-inset ${tone.panel}`}
                        >
                            <span
                                className={`h-1 w-6 rounded-full ${tone.bar}`}
                            />
                            <span className="h-1 w-full rounded-full bg-white/20" />
                            <span className="h-1 w-2/3 rounded-full bg-white/15" />
                        </div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                </div>
            </div>
        );
    }

    return (
        <div className="relative flex h-full flex-col justify-between overflow-hidden bg-slate-950">
            {imageKey ? (
                <img
                    {...marketingImageAttrs({ imageKey })}
                    className="absolute inset-0 size-full object-cover"
                />
            ) : null}
            <div
                className={`relative flex h-full flex-col justify-between bg-gradient-to-br p-3 ${
                    imageKey
                        ? 'from-black/70 via-black/40 to-transparent'
                        : tone.wash
                }`}
            >
                <p
                    className={`font-mono text-[7px] tracking-[0.18em] uppercase ${tone.text}`}
                >
                    {template.eyebrow}
                </p>
                <div>
                    <p className="font-display text-[15px] leading-tight font-semibold text-balance text-white">
                        {template.title}
                    </p>
                    <p className="mt-1 text-[8px] text-white/70">
                        {template.subtitle}
                    </p>
                </div>
                <div className="flex gap-1">
                    <span className={`h-1 w-8 rounded-full ${tone.bar}`} />
                    <span className="h-1 w-4 rounded-full bg-white/20" />
                    <span className="h-1 w-2 rounded-full bg-white/10" />
                </div>
            </div>
        </div>
    );
}

export default function TemplateShowcase() {
    const reduced = usePrefersReducedMotion();
    const [activeId, setActiveId] = useState(CATEGORIES[0].id);
    const active = CATEGORIES.find((c) => c.id === activeId) ?? CATEGORIES[0];

    return (
        <MarketingSection id="templates">
            <SectionHeading
                eyebrow="Templates"
                title="Start from a Template built for your setting"
                description="The RMSignage team maintains the library. Use Template copies a published layout into your business as an independent Screen Design — later library updates never overwrite your work."
            />

            {/* `w-max` + `mx-auto` centres the rail when it fits and keeps the
                first (active) category in view when it has to scroll. */}
            <div className="mt-10 [scrollbar-width:none] overflow-x-auto pb-2 [&::-webkit-scrollbar]:hidden">
                <div className="mx-auto w-max px-1">
                    <ContinuousTabs
                        label="Template categories"
                        panelId="template-showcase-panel"
                        tabs={CATEGORIES.map(({ id, label }) => ({
                            id,
                            label,
                        }))}
                        defaultActiveId={CATEGORIES[0].id}
                        onChange={setActiveId}
                    />
                </div>
            </div>

            <p className="text-muted-foreground mx-auto mt-6 max-w-xl text-center text-sm">
                {active.blurb}
            </p>

            <div
                id="template-showcase-panel"
                role="tabpanel"
                aria-label={`${active.label} templates`}
                className="mt-8 min-h-[280px]"
            >
                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={active.id}
                        initial={reduced ? undefined : { opacity: 0, y: 12 }}
                        animate={reduced ? undefined : { opacity: 1, y: 0 }}
                        exit={reduced ? undefined : { opacity: 0, y: -8 }}
                        transition={{ duration: reduced ? 0 : 0.28 }}
                        className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        {active.templates.map((template) => (
                            <figure
                                key={template.name}
                                className="border-border/70 bg-card hover:border-primary/40 group flex flex-col gap-3 rounded-xl border p-3 transition-colors"
                            >
                                <ScreenFrame>
                                    <TemplateMock
                                        template={template}
                                        accent={active.accent}
                                        fallbackImageKey={
                                            CATEGORY_IMAGES[active.id] ??
                                            'corporate'
                                        }
                                    />
                                </ScreenFrame>
                                <figcaption className="flex items-center justify-between gap-2 px-1 pb-1">
                                    <span className="text-sm font-medium">
                                        {template.name}
                                    </span>
                                    <span className="text-muted-foreground font-mono text-[10px] tracking-wide uppercase">
                                        {active.label}
                                    </span>
                                </figcaption>
                            </figure>
                        ))}
                    </motion.div>
                </AnimatePresence>
            </div>

            <motion.p
                {...reveal(reduced)}
                variants={fadeUp(reduced)}
                className="text-muted-foreground mt-8 text-center text-sm"
            >
                Prefer a blank canvas? Create one in Landscape 1920×1080 or
                Portrait 1080×1920 and build from scratch.
            </motion.p>
        </MarketingSection>
    );
}
