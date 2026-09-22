import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-background relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div
                aria-hidden
                className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_color-mix(in_oklch,var(--primary)_14%,transparent),_transparent_50%)]"
            />
            <div className="relative z-10 w-full max-w-sm">
                <div className="mb-4">
                    <Link
                        href={home()}
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm transition-colors"
                        data-test="auth-back-home"
                    >
                        <ArrowLeft className="size-4" />
                        Back to home
                    </Link>
                </div>
                <div className="border-border bg-card text-card-foreground rounded-xl border p-6 shadow-sm sm:p-8">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="focus-visible:ring-ring/50 flex flex-col items-center gap-2 rounded-md font-medium outline-none focus-visible:ring-[3px]"
                            >
                                <div className="bg-primary text-primary-foreground mb-1 flex size-9 items-center justify-center rounded-lg">
                                    <AppLogoIcon className="size-5" />
                                </div>
                                <span className="sr-only">RMSignage home</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {title}
                                </h1>
                                <p className="text-muted-foreground text-center text-sm text-pretty">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
