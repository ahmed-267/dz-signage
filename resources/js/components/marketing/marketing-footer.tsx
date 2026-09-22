import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { MARKETING_SECTIONS } from '@/components/marketing/marketing-navbar';
import {
    scrollToSection,
    usePrefersReducedMotion,
} from '@/lib/marketing-motion';
import { login, register } from '@/routes';
import { dashboard } from '@/routes/app';

export default function MarketingFooter({
    canRegister,
}: {
    canRegister: boolean;
}) {
    const { auth, name } = usePage().props;
    const reduced = usePrefersReducedMotion();
    const year = new Date().getFullYear();

    return (
        <footer
            data-test="marketing-footer"
            className="border-border/70 border-t px-6 py-14"
        >
            <div className="mx-auto grid w-full max-w-6xl gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
                <div className="flex flex-col gap-4">
                    <div className="flex items-center gap-2.5">
                        <span className="bg-primary text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                            <AppLogoIcon className="size-[18px]" />
                        </span>
                        <span className="font-display text-base font-semibold tracking-tight">
                            {name}
                        </span>
                    </div>
                    <p className="text-muted-foreground max-w-xs text-sm leading-relaxed">
                        Create Screen Designs, build Playlists, schedule
                        content, and publish to every TV from one Workspace.
                    </p>
                </div>

                <nav aria-labelledby="footer-product">
                    <h2
                        id="footer-product"
                        className="font-mono text-xs font-semibold tracking-[0.18em] uppercase"
                    >
                        Product
                    </h2>
                    <ul className="mt-4 flex flex-col gap-2.5">
                        {MARKETING_SECTIONS.map(({ id, label }) => (
                            <li key={id}>
                                <a
                                    href={`#${id}`}
                                    onClick={(event) => {
                                        event.preventDefault();
                                        scrollToSection(id, reduced);
                                    }}
                                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {label}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <nav aria-labelledby="footer-account">
                    <h2
                        id="footer-account"
                        className="font-mono text-xs font-semibold tracking-[0.18em] uppercase"
                    >
                        Account
                    </h2>
                    <ul className="mt-4 flex flex-col gap-2.5">
                        {auth.user ? (
                            <li>
                                <Link
                                    href={dashboard()}
                                    data-test="marketing-footer-dashboard"
                                    className="text-muted-foreground hover:text-foreground rounded text-sm transition-colors"
                                >
                                    Dashboard
                                </Link>
                            </li>
                        ) : (
                            <>
                                <li>
                                    <Link
                                        href={login()}
                                        data-test="marketing-footer-sign-in"
                                        className="text-muted-foreground hover:text-foreground rounded text-sm transition-colors"
                                    >
                                        Sign In
                                    </Link>
                                </li>
                                {canRegister && (
                                    <li>
                                        <Link
                                            href={register()}
                                            data-test="marketing-footer-get-started"
                                            className="text-muted-foreground hover:text-foreground rounded text-sm transition-colors"
                                        >
                                            Get Started
                                        </Link>
                                    </li>
                                )}
                            </>
                        )}
                    </ul>
                </nav>
            </div>

            <div className="border-border/70 mx-auto mt-12 w-full max-w-6xl border-t pt-6">
                <p className="text-muted-foreground font-mono text-xs">
                    © {year} {name}
                </p>
            </div>
        </footer>
    );
}
