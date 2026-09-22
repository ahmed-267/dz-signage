import {
    CalendarClockIcon,
    CreditCardIcon,
    LayoutTemplateIcon,
    MonitorIcon,
    PenToolIcon,
    RotateCcwIcon,
    ShieldCheckIcon,
    TvIcon,
    WifiOffIcon,
} from 'lucide-react';
import {
    AccordionApp,
    type AccordionItemData,
} from '@/components/watermelon/card-split-accordian';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import { usePrefersReducedMotion } from '@/lib/marketing-motion';

const ITEMS: AccordionItemData[] = [
    {
        id: 1,
        title: 'What hardware do I need?',
        icon: <TvIcon className="size-4" />,
        content:
            'Any display that can run a modern browser — a smart TV browser, an Android TV box, a Chromebox, or a small PC behind the screen. You open the Player page on the device once, pair it, and leave it running. There is no app to install and no per-device configuration file.',
    },
    {
        id: 2,
        title: 'How is a TV licence counted?',
        icon: <CreditCardIcon className="size-4" />,
        content:
            'A licence is used by a TV that has a paired device. Whether that TV is Online or Offline, Active or Inactive, makes no difference. Unpair the device and the licence is released for another TV.',
    },
    {
        id: 3,
        title: 'What is the difference between a Template and a Screen Design?',
        icon: <LayoutTemplateIcon className="size-4" />,
        content:
            'Templates are the reusable layouts maintained by the RMSignage team. Use Template copies a published Template into your business as a Screen Design that is entirely yours to edit — and later changes to the Template library never overwrite it.',
    },
    {
        id: 4,
        title: 'If I edit a design, does the TV change immediately?',
        icon: <PenToolIcon className="size-4" />,
        content:
            'No. Publish Design finalises a version, and Publish to TV deploys that exact version. Your later edits stay off-air until you deliberately republish, so you can work on the next version during opening hours.',
    },
    {
        id: 5,
        title: 'What happens if the internet drops?',
        icon: <WifiOffIcon className="size-4" />,
        content:
            'The Player keeps playing its cached package, including the media its designs need, and keeps honouring scheduled changeovers from the windows it already synced. When the connection returns it checks in, picks up anything it missed, and swaps packages atomically.',
    },
    {
        id: 6,
        title: 'Can two schedules overlap?',
        icon: <CalendarClockIcon className="size-4" />,
        content:
            'Yes. Overlaps are warnings, never blockers: when two active schedules match the same moment, the higher priority content plays. If no schedule matches, the always-on Deployment keeps the TV filled.',
    },
    {
        id: 7,
        title: 'Can I use portrait screens?',
        icon: <MonitorIcon className="size-4" />,
        content:
            'Yes. Blank canvases are available in Landscape 1920×1080 and Portrait 1080×1920, Templates are published for both, and a Playlist is single-orientation so a portrait loop never lands on a landscape display.',
    },
    {
        id: 8,
        title: 'Who on my team can publish to a TV?',
        icon: <ShieldCheckIcon className="size-4" />,
        content:
            'Owner, Admin, and Content Manager can publish content to TVs. Designers can build and preview designs, but cannot publish to TVs. Every one of these rules is enforced on the server, not just hidden in the interface.',
    },
    {
        id: 9,
        title: 'How does payment work?',
        icon: <RotateCcwIcon className="size-4" />,
        content:
            'Through Stripe Checkout and the Stripe Customer Portal, with TV licences as the subscription quantity. Your business is the Stripe customer, the Owner manages the subscription, and Admins can view it. Card details never touch RMSignage.',
    },
];

export default function FaqSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection id="faq" data-test="marketing-faq">
            <SectionHeading
                eyebrow="FAQ"
                title="Questions worth answering before you sign up"
            />

            <div className="mt-12">
                <AccordionApp
                    items={ITEMS}
                    reducedMotion={reduced}
                    listClassName="max-w-3xl"
                />
            </div>
        </MarketingSection>
    );
}
