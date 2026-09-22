import { Link, usePage } from '@inertiajs/react';
import { motion } from 'motion/react';
import { ArrowRightIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { MarketingSection } from '@/layouts/marketing-layout';
import {
    fadeUp,
    reveal,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { login, register } from '@/routes';
import { dashboard } from '@/routes/app';

const BLOB_CLIP =
    'polygon(74.8% 41.9%, 97.2% 73.2%, 100% 34.9%, 92.5% 0.4%, 87.5% 0%, 75% 28.6%, 58.5% 54.6%, 50.1% 56.8%, 46.9% 44%, 48.3% 17.4%, 24.7% 53.9%, 0% 27.9%, 11.9% 74.2%, 24.9% 54.1%, 68.6% 100%, 74.8% 41.9%)';

export default function FinalCta({ canRegister }: { canRegister: boolean }) {
    const { auth } = usePage().props;
    const reduced = usePrefersReducedMotion();

    return (
        <MarketingSection data-test="marketing-final-cta">
            <motion.div
                {...reveal(reduced)}
                variants={fadeUp(reduced, 24)}
                className="border-border/70 bg-primary/5 relative isolate overflow-hidden rounded-2xl border px-6 py-14 text-center sm:px-12 sm:py-20"
            >
                <div
                    aria-hidden="true"
                    className="absolute top-1/2 left-[max(-7rem,calc(50%-52rem))] -z-10 -translate-y-1/2 transform-gpu blur-2xl"
                >
                    <div
                        style={{ clipPath: BLOB_CLIP }}
                        className="from-primary to-primary/50 aspect-[577/310] w-[36rem] bg-gradient-to-r opacity-25"
                    />
                </div>
                <div
                    aria-hidden="true"
                    className="absolute top-1/2 left-[max(45rem,calc(50%+8rem))] -z-10 -translate-y-1/2 transform-gpu blur-2xl"
                >
                    <div
                        style={{ clipPath: BLOB_CLIP }}
                        className="from-accent to-primary/50 aspect-[577/310] w-[36rem] bg-gradient-to-r opacity-20"
                    />
                </div>

                <h2 className="font-display mx-auto max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    Put your screens to work
                </h2>
                <p className="text-muted-foreground mx-auto mt-4 max-w-xl text-base leading-relaxed text-pretty">
                    Create a business, design your first Screen Design, and pair
                    a display. You can start with a single TV and grow from
                    there.
                </p>

                <div className="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                    {auth.user ? (
                        <Button
                            data-test="marketing-final-open-dashboard"
                            size="lg"
                            asChild
                        >
                            <Link href={dashboard()}>
                                Open Dashboard
                                <ArrowRightIcon />
                            </Link>
                        </Button>
                    ) : (
                        <>
                            {canRegister && (
                                <Button
                                    data-test="marketing-final-get-started"
                                    size="lg"
                                    asChild
                                >
                                    <Link href={register()}>
                                        Get Started
                                        <ArrowRightIcon />
                                    </Link>
                                </Button>
                            )}
                            <Button
                                data-test="marketing-final-sign-in"
                                size="lg"
                                variant="outline"
                                asChild
                            >
                                <Link href={login()}>Sign In</Link>
                            </Button>
                        </>
                    )}
                </div>
            </motion.div>
        </MarketingSection>
    );
}
