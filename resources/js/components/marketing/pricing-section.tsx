import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { motion } from 'motion/react';
import { ArrowRightIcon, CheckIcon, XIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { MarketingSection, SectionHeading } from '@/layouts/marketing-layout';
import {
    fadeUp,
    reveal,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { dashboard } from '@/routes/app';
import type {
    MarketingPlan,
    MarketingPlanCatalog,
    MarketingPricing,
} from '@/types/billing';

export type { MarketingPrice, MarketingPricing } from '@/types/billing';

/** Decimal places Stripe uses for a currency (0 for JPY, 2 for GBP, …). */
export function minorUnitDigits(currency: string): number {
    return (
        new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).resolvedOptions().maximumFractionDigits ?? 2
    );
}

const SALES_MAILTO = 'mailto:sales@dzsignage.com';

function PlanCta({
    plan,
    canRegister,
}: {
    plan: MarketingPlan;
    canRegister: boolean;
}) {
    const { auth } = usePage().props;

    if (plan.enterprise) {
        return (
            <Button
                data-test={`pricing-plan-${plan.key}-cta`}
                size="lg"
                variant="outline"
                className="w-full"
                asChild
            >
                <a href={SALES_MAILTO}>Contact Sales</a>
            </Button>
        );
    }

    if (auth.user) {
        return (
            <Button
                data-test={`pricing-plan-${plan.key}-cta`}
                size="lg"
                className="w-full"
                asChild
            >
                <Link href={dashboard()}>
                    Open Dashboard
                    <ArrowRightIcon />
                </Link>
            </Button>
        );
    }

    if (!canRegister) {
        return (
            <Button
                data-test={`pricing-plan-${plan.key}-cta`}
                size="lg"
                className="w-full"
                asChild
            >
                <Link href={login()}>Sign In</Link>
            </Button>
        );
    }

    return (
        <Button
            data-test={`pricing-plan-${plan.key}-cta`}
            size="lg"
            className="w-full"
            asChild
        >
            <Link href={register()}>
                Get Started
                <ArrowRightIcon />
            </Link>
        </Button>
    );
}

function ComparisonCell({ value }: { value: boolean | string }) {
    if (typeof value === 'boolean') {
        return value ? (
            <CheckIcon
                className="text-primary mx-auto size-4"
                aria-label="Included"
            />
        ) : (
            <XIcon
                className="text-muted-foreground/50 mx-auto size-4"
                aria-label="Not included"
            />
        );
    }

    return <span className="text-sm">{value}</span>;
}

export default function PricingSection({
    planCatalog,
    pricing,
    canRegister,
}: {
    planCatalog?: MarketingPlanCatalog | null;
    pricing?: MarketingPricing;
    canRegister: boolean;
}) {
    const reduced = usePrefersReducedMotion();
    const [annual, setAnnual] = useState(true);

    const plans = planCatalog?.plans ?? [];
    const comparison = planCatalog?.comparison ?? [];

    return (
        <MarketingSection
            id="pricing"
            data-test="marketing-pricing"
            className="border-border/70 bg-muted/30 border-y"
        >
            <SectionHeading
                eyebrow="Pricing"
                title="Plans that scale with your TVs"
                description="Starter and Business include fixed TV licences, storage, and team seats. Enterprise is custom — talk to sales."
            />

            <div className="mt-10 flex justify-center">
                <div
                    className="border-border/70 bg-muted/60 flex w-fit gap-1 rounded-full border p-1"
                    role="group"
                    aria-label="Billing interval"
                >
                    <button
                        type="button"
                        data-test="marketing-pricing-interval-monthly"
                        aria-pressed={!annual}
                        onClick={() => setAnnual(false)}
                        className={cn(
                            'focus-visible:ring-ring rounded-full px-4 py-1.5 font-mono text-[10px] font-bold tracking-widest uppercase transition-colors focus-visible:ring-2 focus-visible:outline-none',
                            !annual
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Monthly
                    </button>
                    <button
                        type="button"
                        data-test="marketing-pricing-interval-yearly"
                        aria-pressed={annual}
                        onClick={() => setAnnual(true)}
                        className={cn(
                            'focus-visible:ring-ring rounded-full px-4 py-1.5 font-mono text-[10px] font-bold tracking-widest uppercase transition-colors focus-visible:ring-2 focus-visible:outline-none',
                            annual
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        Annual
                    </button>
                </div>
            </div>

            {plans.length > 0 ? (
                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced, 24)}
                    className="mx-auto mt-10 grid max-w-6xl gap-5 lg:grid-cols-3"
                >
                    {plans.map((plan) => {
                        const monthly = plan.monthly;
                        const yearlyEq = plan.yearly_monthly_equivalent;
                        const showAnnual = annual && !plan.enterprise;
                        const priceLabel = plan.enterprise
                            ? 'Custom'
                            : showAnnual
                              ? (yearlyEq?.formatted ??
                                monthly?.formatted ??
                                '—')
                              : (monthly?.formatted ?? '—');
                        const priceHint = plan.enterprise
                            ? 'Tailored to your estate'
                            : showAnnual
                              ? 'per month, billed annually'
                              : 'per month';

                        return (
                            <div
                                key={plan.key}
                                data-test={`pricing-plan-${plan.key}`}
                                className={cn(
                                    'border-border/70 bg-card relative flex flex-col rounded-2xl border p-6 shadow-sm',
                                    plan.popular &&
                                        'border-primary ring-primary/20 ring-2',
                                )}
                            >
                                {plan.popular ? (
                                    <Badge
                                        className="absolute -top-2.5 left-1/2 -translate-x-1/2"
                                        data-test="pricing-plan-popular"
                                    >
                                        Most Popular
                                    </Badge>
                                ) : null}

                                <div className="mb-5">
                                    <h3 className="font-display text-xl font-semibold tracking-tight">
                                        {plan.name}
                                    </h3>
                                    <p className="text-muted-foreground mt-1.5 text-sm leading-relaxed">
                                        {plan.tagline}
                                    </p>
                                </div>

                                <div className="mb-6">
                                    <p
                                        className="font-display text-4xl font-semibold tracking-tight"
                                        data-test={`pricing-plan-${plan.key}-price`}
                                    >
                                        {priceLabel}
                                    </p>
                                    <p className="text-muted-foreground mt-1 font-mono text-[11px] tracking-[0.14em] uppercase">
                                        {priceHint}
                                    </p>
                                    {showAnnual && monthly?.formatted ? (
                                        <p className="text-muted-foreground mt-2 text-xs">
                                            {monthly.formatted}/mo billed
                                            monthly
                                        </p>
                                    ) : null}
                                </div>

                                <ul className="mb-8 flex flex-1 flex-col gap-2.5">
                                    {plan.feature_labels.map((label) => (
                                        <li
                                            key={label}
                                            className="flex gap-2.5"
                                        >
                                            <CheckIcon className="text-primary mt-0.5 size-4 shrink-0" />
                                            <span className="text-sm leading-relaxed">
                                                {label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>

                                <PlanCta
                                    plan={plan}
                                    canRegister={canRegister}
                                />
                            </div>
                        );
                    })}
                </motion.div>
            ) : pricing ? (
                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced, 24)}
                    className="border-border/70 bg-card mx-auto mt-14 flex max-w-2xl flex-col items-center gap-6 rounded-2xl border p-8 text-center shadow-sm"
                >
                    <p className="text-base leading-relaxed">
                        Plans are loading from configuration. Get Started to
                        create your Workspace.
                    </p>
                    <Button size="lg" asChild>
                        <Link href={canRegister ? register() : login()}>
                            {canRegister ? 'Get Started' : 'Sign In'}
                            <ArrowRightIcon />
                        </Link>
                    </Button>
                </motion.div>
            ) : null}

            {comparison.length > 0 ? (
                <motion.div
                    {...reveal(reduced)}
                    variants={fadeUp(reduced, 24)}
                    className="border-border/70 bg-card mx-auto mt-14 max-w-6xl overflow-hidden rounded-2xl border shadow-sm"
                    data-test="marketing-pricing-comparison"
                >
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left">
                            <thead>
                                <tr className="border-border/70 border-b">
                                    <th className="text-muted-foreground px-4 py-3 font-mono text-[10px] font-bold tracking-widest uppercase">
                                        Feature
                                    </th>
                                    <th className="font-display px-4 py-3 text-center text-sm font-semibold">
                                        Starter
                                    </th>
                                    <th className="font-display px-4 py-3 text-center text-sm font-semibold">
                                        Business
                                    </th>
                                    <th className="font-display px-4 py-3 text-center text-sm font-semibold">
                                        Enterprise
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {comparison.map((row) => (
                                    <tr
                                        key={row.feature}
                                        className="border-border/50 border-b last:border-0"
                                    >
                                        <td className="px-4 py-3 text-sm font-medium">
                                            {row.feature}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <ComparisonCell
                                                value={row.starter}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <ComparisonCell
                                                value={row.business}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <ComparisonCell
                                                value={row.enterprise}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </motion.div>
            ) : null}
        </MarketingSection>
    );
}
