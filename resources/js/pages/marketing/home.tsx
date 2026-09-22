import { Head, usePage } from '@inertiajs/react';
import FaqSection from '@/components/marketing/faq-section';
import FeatureShowcase from '@/components/marketing/feature-showcase';
import FinalCta from '@/components/marketing/final-cta';
import HeroSection from '@/components/marketing/hero-section';
import IndustrySection from '@/components/marketing/industry-section';
import AiSection from '@/components/marketing/ai-section';
import PricingSection, {
    minorUnitDigits,
} from '@/components/marketing/pricing-section';
import TemplateShowcase from '@/components/marketing/template-showcase';
import WorkflowSection from '@/components/marketing/workflow-section';
import MarketingLayout from '@/layouts/marketing-layout';
import type { MarketingPlanCatalog, MarketingPricing } from '@/types/billing';

export type MarketingHomeProps = {
    canRegister: boolean;
    planCatalog?: MarketingPlanCatalog | null;
    pricing: MarketingPricing;
    seo: {
        title: string;
        description: string;
    };
};

export default function MarketingHome({
    canRegister,
    planCatalog,
    pricing,
    seo,
}: MarketingHomeProps) {
    const { name } = usePage().props;
    const url =
        typeof window === 'undefined' ? '' : `${window.location.origin}/`;

    const starter = planCatalog?.plans.find((plan) => plan.key === 'starter');
    const headlineAmount = starter?.monthly;
    const offer =
        headlineAmount?.amount != null && headlineAmount.currency
            ? {
                  offers: {
                      '@type': 'Offer',
                      price: (
                          headlineAmount.amount /
                          10 ** minorUnitDigits(headlineAmount.currency)
                      ).toString(),
                      priceCurrency: headlineAmount.currency,
                      description: 'Starter plan, billed monthly',
                  },
              }
            : {};

    // No ratings or review counts: nothing here is invented.
    const structuredData = {
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name,
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web browser',
        description: seo.description,
        ...(url ? { url } : {}),
        ...offer,
    };

    return (
        <MarketingLayout canRegister={canRegister}>
            <Head title={seo.title}>
                <meta name="description" content={seo.description} />
                <meta property="og:type" content="website" />
                <meta property="og:site_name" content={name} />
                <meta property="og:title" content={`${name} — ${seo.title}`} />
                <meta property="og:description" content={seo.description} />
                {url ? <meta property="og:url" content={url} /> : null}
                <meta name="twitter:card" content="summary_large_image" />
                <meta name="twitter:title" content={`${name} — ${seo.title}`} />
                <meta name="twitter:description" content={seo.description} />
                <script
                    type="application/ld+json"
                    dangerouslySetInnerHTML={{
                        __html: JSON.stringify(structuredData),
                    }}
                />
            </Head>

            <HeroSection canRegister={canRegister} />
            <WorkflowSection />
            <FeatureShowcase />
            <TemplateShowcase />
            <IndustrySection />
            <AiSection />
            <PricingSection
                planCatalog={planCatalog}
                pricing={pricing}
                canRegister={canRegister}
            />
            <FaqSection />
            <FinalCta canRegister={canRegister} />
        </MarketingLayout>
    );
}
