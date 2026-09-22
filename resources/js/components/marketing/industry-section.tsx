import { motion } from 'motion/react';
import {
    BedDoubleIcon,
    Building2Icon,
    DumbbellIcon,
    GraduationCapIcon,
    HeartPulseIcon,
    KeyRoundIcon,
    ShoppingBagIcon,
    TicketIcon,
    UtensilsCrossedIcon,
} from 'lucide-react';
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

const INDUSTRIES: {
    icon: typeof UtensilsCrossedIcon;
    name: string;
    body: string;
    imageKey?: MarketingImageKey;
}[] = [
    {
        icon: UtensilsCrossedIcon,
        name: 'Restaurants',
        body: 'Menu boards that change with service',
        imageKey: 'restaurant',
    },
    {
        icon: ShoppingBagIcon,
        name: 'Retail',
        body: 'Promotions across every store',
        imageKey: 'retail',
    },
    {
        icon: Building2Icon,
        name: 'Corporate',
        body: 'Reception and internal comms',
        imageKey: 'corporate',
    },
    {
        icon: GraduationCapIcon,
        name: 'Education',
        body: 'Timetables and campus notices',
        imageKey: 'education',
    },
    {
        icon: HeartPulseIcon,
        name: 'Healthcare',
        body: 'Waiting rooms and clinic updates',
        imageKey: 'healthcare',
    },
    {
        icon: BedDoubleIcon,
        name: 'Hotels',
        body: 'Guest welcomes and event boards',
        imageKey: 'hotel',
    },
    {
        icon: DumbbellIcon,
        name: 'Gyms',
        body: 'Class schedules by studio',
        imageKey: 'gym',
    },
    {
        icon: KeyRoundIcon,
        name: 'Real Estate',
        body: 'Window displays and listings',
        imageKey: 'property',
    },
    {
        icon: TicketIcon,
        name: 'Events',
        body: 'Countdowns and programme boards',
        imageKey: 'events',
    },
];

export default function IndustrySection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection
            id="industries"
            className="border-border/70 bg-muted/30 border-y"
        >
            <SectionHeading
                eyebrow="Who it's for"
                title="One platform, whatever your TVs show"
                description="RMSignage is deliberately universal. The same workflow runs restaurant menus, retail promotions, corporate reception boards, and more."
            />

            <motion.ul
                {...reveal(reduced)}
                variants={stagger(reduced, 0.04)}
                className="mt-14 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                {INDUSTRIES.map(({ icon: Icon, name, body, imageKey }) => (
                    <motion.li
                        key={name}
                        variants={fadeUp(reduced, 12)}
                        className="border-border/70 bg-card hover:border-primary/40 flex flex-col overflow-hidden rounded-xl border transition-colors"
                    >
                        {imageKey ? (
                            <div className="relative aspect-[16/9] w-full overflow-hidden bg-slate-950">
                                <img
                                    {...marketingImageAttrs({ imageKey })}
                                    className="h-full w-full object-cover"
                                />
                            </div>
                        ) : null}
                        <div className="flex flex-1 items-start gap-3 p-4">
                            <span className="bg-primary/10 text-primary mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-lg">
                                <Icon className="size-4" aria-hidden />
                            </span>
                            <div>
                                <p className="text-sm font-semibold tracking-tight">
                                    {name}
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs leading-relaxed">
                                    {body}
                                </p>
                            </div>
                        </div>
                    </motion.li>
                ))}
            </motion.ul>
        </MarketingSection>
    );
}
