import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes/app';
import { login, register } from '@/routes';

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Digital signage that stays on brand" />
            <div className="bg-background text-foreground relative min-h-dvh overflow-hidden">
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_color-mix(in_oklch,var(--primary)_22%,transparent),_transparent_55%),radial-gradient(ellipse_at_bottom_right,_color-mix(in_oklch,var(--info)_12%,transparent),_transparent_45%)]"
                />
                <div
                    aria-hidden
                    className="border-border/40 pointer-events-none absolute inset-0 [background-image:linear-gradient(var(--border)_1px,transparent_1px),linear-gradient(90deg,var(--border)_1px,transparent_1px)] [background-size:48px_48px] opacity-[0.35]"
                />

                <header className="relative z-10 mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-6">
                    <div className="flex items-center gap-3">
                        <div className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-5 fill-current" />
                        </div>
                        <span className="text-lg font-semibold tracking-tight">
                            {name}
                        </span>
                    </div>
                    <nav className="flex items-center gap-2 sm:gap-3">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Open app</Link>
                            </Button>
                        ) : (
                            <>
                                <Button variant="ghost" asChild>
                                    <Link href={login()}>Sign In</Link>
                                </Button>
                                <Button asChild>
                                    <Link href={register()}>Get Started</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-col justify-center px-6 pt-16 pb-24 md:pt-28">
                    <p className="text-primary text-sm font-medium tracking-[0.2em] uppercase">
                        DZ Signage
                    </p>
                    <h1 className="mt-4 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl md:text-6xl">
                        Screens that stay on message.
                    </h1>
                    <p className="text-muted-foreground mt-5 max-w-xl text-base text-pretty sm:text-lg">
                        Design, schedule, and publish digital signage across
                        every location — from one calm workspace.
                    </p>
                    {!auth.user && (
                        <div className="mt-8 flex flex-wrap gap-3">
                            <Button size="lg" asChild>
                                <Link href={register()}>Get Started</Link>
                            </Button>
                            <Button size="lg" variant="outline" asChild>
                                <Link href={login()}>Sign In</Link>
                            </Button>
                        </div>
                    )}
                </main>
            </div>
        </>
    );
}
