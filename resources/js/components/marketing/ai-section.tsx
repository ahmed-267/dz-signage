import { SparklesIcon } from 'lucide-react';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import { marketingImageAttrs } from '@/lib/marketing-images';
import {
    fadeUp,
    reveal,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { motion } from 'motion/react';

const STEPS = [
    {
        title: 'Brief your agent',
        body: 'Describe the signage you need — a promo board, reception welcome, or menu layout — in plain language.',
    },
    {
        title: 'Get an editable draft',
        body: 'AI proposes copy, imagery, and a Screen Design draft you can open in the editor like any other design.',
    },
    {
        title: 'Finish and publish yourself',
        body: 'Refine, save to Media or Screen Designs, then publish on your terms. AI never auto-publishes to a TV.',
    },
];

export default function AiSection() {
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection
            id="ai"
            data-test="marketing-ai"
            className="bg-muted/20"
        >
            <motion.div {...reveal(reduced)} variants={fadeUp(reduced)}>
                <SectionHeading
                    eyebrow="AI signage agent"
                    title="Build signage with an AI agent — not just images"
                    description="Treat AI as a signage-building teammate: it drafts Screen Designs, headlines, and Media you can edit. You stay in control of every publish."
                />
            </motion.div>

            <div className="mt-12 grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced)}
                    className="border-border/70 bg-card rounded-2xl border p-6 sm:p-8"
                >
                    <div className="text-primary mb-4 flex size-10 items-center justify-center rounded-xl bg-[color-mix(in_oklch,var(--primary)_16%,transparent)]">
                        <SparklesIcon className="size-5" />
                    </div>
                    <h3 className="font-display text-xl font-semibold tracking-tight">
                        From brief to Screen Design draft
                    </h3>
                    <p className="text-muted-foreground mt-3 text-sm leading-relaxed">
                        Generate signage copy, save images into the Media
                        Library, and accept AI Screen Design drafts into the
                        editor. Video generation is not part of this release.
                    </p>
                    <ul className="mt-6 space-y-4">
                        {STEPS.map((step, index) => (
                            <li key={step.title} className="flex gap-3">
                                <span className="bg-primary/15 text-primary flex size-7 shrink-0 items-center justify-center rounded-full font-mono text-xs font-semibold">
                                    {index + 1}
                                </span>
                                <div>
                                    <p className="text-sm font-medium">
                                        {step.title}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-sm leading-relaxed">
                                        {step.body}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </motion.div>

                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced)}
                    className="border-border/70 from-card to-muted/40 relative overflow-hidden rounded-2xl border bg-gradient-to-br p-6"
                >
                    <p className="text-muted-foreground font-mono text-[11px] tracking-[0.16em] uppercase">
                        Example agent flow
                    </p>
                    <div className="mt-5 space-y-3">
                        <div className="bg-background/80 rounded-xl border px-4 py-3 text-sm shadow-sm">
                            “Summer iced coffee promo for our café TV”
                        </div>
                        <div className="relative aspect-video overflow-hidden rounded-xl border">
                            <img
                                {...marketingImageAttrs({ imageKey: 'cafe' })}
                                className="size-full object-cover"
                            />
                            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent px-3 py-2">
                                <p className="text-xs font-medium text-white">
                                    Draft Screen Design ready to edit
                                </p>
                            </div>
                        </div>
                        <div className="text-muted-foreground px-1 text-xs">
                            → Open in editor → tweak type &amp; widgets → save
                        </div>
                        <div className="bg-primary/10 text-primary rounded-xl border border-[color-mix(in_oklch,var(--primary)_35%,transparent)] px-4 py-3 text-sm font-medium">
                            Publish still requires your confirmation
                        </div>
                    </div>
                </motion.div>
            </div>
        </MarketingSection>
    );
}
