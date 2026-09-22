import { useEffect, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { MenuIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ThemeToggle } from '@/components/theme-toggle';
import { Button } from '@/components/ui/button';

import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    scrollToSection,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { cn } from '@/lib/utils';
import { login, register } from '@/routes';
import { dashboard } from '@/routes/app';

/** Anchor targets shared by the navbar, footer, and in-page CTAs. */
export const MARKETING_SECTIONS = [
    { id: 'product', label: 'Product' },
    { id: 'features', label: 'Features' },
    { id: 'templates', label: 'Templates' },
    { id: 'industries', label: 'Industries' },
    { id: 'ai', label: 'AI' },
    { id: 'pricing', label: 'Pricing' },
    { id: 'faq', label: 'FAQ' },
] as const;

export type MarketingSectionId = (typeof MARKETING_SECTIONS)[number]['id'];

/** Highlights the section currently filling the viewport. */
function useActiveSection(): string | null {
    const [active, setActive] = useState<string | null>(null);

    useEffect(() => {
        const targets = MARKETING_SECTIONS.map(({ id }) =>
            document.getElementById(id),
        ).filter((element): element is HTMLElement => element !== null);

        if (targets.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (a, b) => b.intersectionRatio - a.intersectionRatio,
                    )[0];

                if (visible) {
                    setActive(visible.target.id);
                }
            },
            { rootMargin: '-25% 0px -60% 0px', threshold: [0.05, 0.3, 0.6] },
        );

        targets.forEach((target) => observer.observe(target));

        return () => observer.disconnect();
    }, []);

    return active;
}

export default function MarketingNavbar({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth, name } = usePage().props;
    const reduced = usePrefersReducedMotion();
    const active = useActiveSection();
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const goTo = (id: string) => {
        setMobileOpen(false);
        scrollToSection(id, reduced);
    };

    return (
        <header
            data-test="marketing-nav"
            className={cn(
                'sticky top-0 z-50 w-full transition-colors duration-300',
                scrolled
                    ? 'border-border/70 bg-background/80 border-b backdrop-blur-xl'
                    : 'border-b border-transparent',
            )}
        >
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-6">
                <Link
                    href="/"
                    className="focus-visible:ring-ring flex items-center gap-2.5 rounded-md focus-visible:ring-2 focus-visible:outline-none"
                >
                    <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                        <AppLogoIcon className="size-[18px]" />
                    </span>
                    <span className="font-display text-base font-semibold tracking-tight">
                        {name}
                    </span>
                </Link>

                <nav
                    aria-label="Sections"
                    className="hidden items-center gap-1 lg:flex"
                >
                    {MARKETING_SECTIONS.map(({ id, label }) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            data-test={`marketing-nav-link-${id}`}
                            aria-current={active === id ? 'true' : undefined}
                            onClick={(event) => {
                                event.preventDefault();
                                goTo(id);
                            }}
                            className={cn(
                                'hover:text-foreground focus-visible:ring-ring rounded-md px-3 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                active === id
                                    ? 'text-foreground'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {label}
                        </a>
                    ))}
                </nav>

                <div className="flex items-center gap-2">
                    <ThemeToggle className="hidden sm:inline-flex" />

                    {auth.user ? (
                        <Button
                            data-test="marketing-open-dashboard"
                            size="sm"
                            asChild
                        >
                            <Link href={dashboard()}>Open Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button
                                data-test="marketing-sign-in"
                                variant="ghost"
                                size="sm"
                                className="hidden sm:inline-flex"
                                asChild
                            >
                                <Link href={login()}>Sign In</Link>
                            </Button>
                            {canRegister && (
                                <Button
                                    data-test="marketing-get-started"
                                    size="sm"
                                    className="hidden sm:inline-flex"
                                    asChild
                                >
                                    <Link href={register()}>Get Started</Link>
                                </Button>
                            )}
                        </>
                    )}

                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                data-test="marketing-nav-toggle"
                                variant="outline"
                                size="icon"
                                className="lg:hidden"
                                aria-label="Open menu"
                            >
                                <MenuIcon />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            data-test="marketing-mobile-nav"
                            className="w-[86vw] gap-0 sm:max-w-sm"
                        >
                            <SheetHeader className="border-border/70 border-b">
                                <SheetTitle className="flex items-center gap-2.5">
                                    <span className="bg-primary text-primary-foreground flex size-7 items-center justify-center rounded-md">
                                        <AppLogoIcon className="size-4" />
                                    </span>
                                    <span className="font-display">{name}</span>
                                </SheetTitle>
                            </SheetHeader>

                            <nav
                                aria-label="Sections"
                                className="flex flex-col gap-1 p-4"
                            >
                                {MARKETING_SECTIONS.map(({ id, label }) => (
                                    <a
                                        key={id}
                                        href={`#${id}`}
                                        data-test={`marketing-mobile-nav-link-${id}`}
                                        onClick={(event) => {
                                            event.preventDefault();
                                            goTo(id);
                                        }}
                                        className="hover:bg-secondary focus-visible:ring-ring rounded-md px-3 py-2.5 text-base font-medium transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                    >
                                        {label}
                                    </a>
                                ))}
                            </nav>

                            <div className="border-border/70 mt-auto flex flex-col gap-3 border-t p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-muted-foreground text-sm font-medium">
                                        Theme
                                    </span>
                                    <ThemeToggle />
                                </div>

                                {auth.user ? (
                                    <Button
                                        data-test="marketing-mobile-open-dashboard"
                                        asChild
                                    >
                                        <Link href={dashboard()}>
                                            Open Dashboard
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        {canRegister && (
                                            <Button
                                                data-test="marketing-mobile-get-started"
                                                asChild
                                            >
                                                <Link href={register()}>
                                                    Get Started
                                                </Link>
                                            </Button>
                                        )}
                                        <Button
                                            data-test="marketing-mobile-sign-in"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href={login()}>Sign In</Link>
                                        </Button>
                                    </>
                                )}
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>
    );
}
