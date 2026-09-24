import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type OnboardingChecklist = {
    has_business: boolean;
    has_screen: boolean;
    has_tv: boolean;
    has_publish: boolean;
    complete_count: number;
    total: number;
    all_done: boolean;
};

export type ProductOnboardingState = {
    eligible: boolean;
    can_replay?: boolean;
    active: boolean;
    auto_start: boolean;
    completed: boolean;
    skipped: boolean;
    step: number;
    total_steps: number;
    can_pair: boolean;
    can_publish: boolean;
    checklist: OnboardingChecklist | null;
    show_checklist: boolean;
    reason?: string | null;
};

type StepDef = {
    id: number;
    title: string;
    body: string;
    /** Route that must be active before the coach mark is shown. */
    route: string;
    target?: string;
    cta?: string;
};

/**
 * Each step declares the page it explains. The tour navigates there first,
 * waits for the route (and optional target), then shows the bubble.
 */
const STEPS: StepDef[] = [
    {
        id: 1,
        title: 'Welcome to RMSignage',
        body: 'This Dashboard gives you a quick view of your TVs, content and activity. Let’s get your first Screen live on a TV.',
        route: '/app/dashboard',
        cta: "Let's start",
    },
    {
        id: 2,
        title: 'Screens',
        body: 'Screens are the content you create and display on your TVs. Let’s create your first one.',
        route: '/app/screen-designs',
        target: '[data-tour="nav-screens"], [data-tour="create-screen"]',
        cta: 'Create my first Screen',
    },
    {
        id: 3,
        title: 'Start a Screen',
        body: 'You can start from a Template, create from scratch, or use AI. Pick whatever fits.',
        route: '/app/screen-designs',
        target: '[data-tour="create-screen"]',
    },
    {
        id: 4,
        title: 'Screen Editor',
        body: 'This is your Screen Editor. Add text, Media and Widgets here. Everything you create remains editable.',
        route: '/app/screen-designs',
        target: '[data-tour="screen-editor"], [data-tour="create-screen"]',
    },
    {
        id: 5,
        title: 'Save your Screen',
        body: 'Save when you are ready. Your first Screen stays editable after publishing.',
        route: '/app/screen-designs',
        target: '[data-tour="screen-save"], [data-tour="create-screen"]',
    },
    {
        id: 6,
        title: 'Paired TVs',
        body: 'Now we need a TV to display your Screen. This is where you manage every paired display.',
        route: '/app/screens',
        target: '[data-tour="nav-tvs"], [data-tour="pair-tv"]',
        cta: 'Pair a TV',
    },
    {
        id: 7,
        title: 'Pair a TV',
        body: 'Open RMSignage Player on your TV, then enter the PIN or scan the QR code shown there.',
        route: '/app/screens',
        target: '[data-tour="pair-tv"]',
    },
    {
        id: 8,
        title: 'Publish',
        body: 'Great — your TV is connected. Now send your Screen to it from the Screens library.',
        route: '/app/screen-designs',
        target: '[data-tour="publish-screen"], [data-tour="create-screen"]',
        cta: 'Publish Screen',
    },
    {
        id: 9,
        title: 'Your first Screen is live',
        body: 'You can now create Playlists, schedule content, and manage multiple TVs.',
        route: '/app/dashboard',
        cta: 'Finish',
    },
];

function postAction(action: string, step?: number) {
    router.post(
        '/app/product-onboarding',
        { action, step },
        { preserveScroll: true, preserveState: true },
    );
}

function pathMatches(currentUrl: string, route: string): boolean {
    try {
        const path = currentUrl.startsWith('http')
            ? new URL(currentUrl).pathname
            : (currentUrl.split('?')[0] ?? currentUrl);
        if (route === '/app/dashboard') {
            return (
                path === '/app/dashboard' || path === '/app' || path === '/app/'
            );
        }
        if (route === '/app/screen-designs') {
            return (
                path === '/app/screen-designs' ||
                path.startsWith('/app/screen-designs/')
            );
        }
        if (route === '/app/screens') {
            return path === '/app/screens' || path.startsWith('/app/screens/');
        }
        return path === route || path.startsWith(`${route}/`);
    } catch {
        return currentUrl.includes(route);
    }
}

function queryTarget(selector?: string): HTMLElement | null {
    if (!selector) {
        return null;
    }
    for (const part of selector.split(',').map((s) => s.trim())) {
        const el = document.querySelector(part);
        if (el instanceof HTMLElement) {
            return el;
        }
    }
    return null;
}

export function ProductOnboardingTour() {
    const page = usePage<{
        productOnboarding?: ProductOnboardingState | null;
    }>();
    const state = page.props.productOnboarding;
    const [open, setOpen] = useState(false);
    /** True only after we are on the step route (and target found or timed out). */
    const [routeReady, setRouteReady] = useState(false);
    const [targetRect, setTargetRect] = useState<DOMRect | null>(null);
    const navigatingRef = useRef(false);

    const stepDef = useMemo(() => {
        if (!state) {
            return STEPS[0];
        }
        return STEPS.find((s) => s.id === state.step) ?? STEPS[0];
    }, [state]);

    useEffect(() => {
        if (!state?.auto_start) {
            return;
        }
        postAction('start', 1);
        setOpen(true);
    }, [state?.auto_start]);

    useEffect(() => {
        if (state?.active) {
            setOpen(true);
        }
    }, [state?.active]);

    // Route-aware: ensure the explained page is loaded before showing the bubble.
    useEffect(() => {
        if (!open || !state?.active || state.completed || state.skipped) {
            setRouteReady(false);
            return;
        }

        setRouteReady(false);
        setTargetRect(null);

        const ensureRoute = () => {
            if (pathMatches(page.url, stepDef.route)) {
                navigatingRef.current = false;
                return true;
            }
            if (!navigatingRef.current) {
                navigatingRef.current = true;
                router.visit(stepDef.route, {
                    preserveScroll: true,
                    onFinish: () => {
                        navigatingRef.current = false;
                    },
                });
            }
            return false;
        };

        if (!ensureRoute()) {
            return;
        }

        let attempts = 0;
        const maxAttempts = 40; // ~2s at 50ms
        const tick = () => {
            if (!pathMatches(page.url, stepDef.route)) {
                return;
            }
            const el = queryTarget(stepDef.target);
            if (el) {
                setTargetRect(el.getBoundingClientRect());
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                setRouteReady(true);
                return;
            }
            attempts += 1;
            if (attempts >= maxAttempts) {
                // Graceful fallback — show the bubble centered on the correct page.
                setTargetRect(null);
                setRouteReady(true);
                return;
            }
            window.setTimeout(tick, 50);
        };

        tick();
    }, [
        open,
        state?.active,
        state?.completed,
        state?.skipped,
        state?.step,
        stepDef,
        page.url,
    ]);

    if (
        !state ||
        (!open && !state.active) ||
        state.completed ||
        state.skipped
    ) {
        return null;
    }

    if (!open || !routeReady) {
        return null;
    }

    function next() {
        if (!state) {
            return;
        }
        if (state.step >= state.total_steps) {
            postAction('complete');
            setOpen(false);
            return;
        }

        const nextStep = STEPS.find((s) => s.id === state.step + 1);
        setRouteReady(false);
        postAction('advance', state.step + 1);
        if (nextStep && !pathMatches(page.url, nextStep.route)) {
            navigatingRef.current = true;
            router.visit(nextStep.route, {
                preserveScroll: true,
                onFinish: () => {
                    navigatingRef.current = false;
                },
            });
        }
    }

    function skip() {
        postAction('skip');
        setOpen(false);
    }

    return (
        <div
            className="pointer-events-none fixed inset-0 z-[80]"
            data-test="product-onboarding-tour"
            data-onboarding-route={stepDef.route}
            data-onboarding-step={stepDef.id}
        >
            {targetRect ? (
                <div
                    className="border-primary pointer-events-none absolute rounded-lg border-2 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                    style={{
                        top: targetRect.top - 4,
                        left: targetRect.left - 4,
                        width: targetRect.width + 8,
                        height: targetRect.height + 8,
                    }}
                />
            ) : (
                <div className="pointer-events-none absolute inset-0 bg-black/40" />
            )}
            <div
                className={cn(
                    'border-border bg-card pointer-events-auto absolute max-w-sm rounded-xl border p-4 shadow-xl',
                    targetRect
                        ? 'right-4 left-4 sm:right-6 sm:left-auto sm:w-96'
                        : 'top-1/2 left-1/2 w-[min(24rem,calc(100%-2rem))] -translate-x-1/2 -translate-y-1/2',
                )}
                style={
                    targetRect
                        ? {
                              top: Math.min(
                                  targetRect.bottom + 12,
                                  window.innerHeight - 220,
                              ),
                          }
                        : undefined
                }
                role="dialog"
                aria-label={stepDef.title}
            >
                <p className="text-muted-foreground font-mono text-[10px] tracking-wider uppercase">
                    Step {state?.step ?? 1} of {state?.total_steps ?? 9}
                </p>
                <h2 className="font-display mt-1 text-lg font-semibold">
                    {stepDef.title}
                </h2>
                <p className="text-muted-foreground mt-2 text-sm">
                    {stepDef.body}
                </p>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={skip}
                        data-test="onboarding-skip"
                    >
                        Skip tour
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={next}
                        data-test="onboarding-next"
                    >
                        {stepDef.cta ?? 'Next'}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export function OnboardingChecklistCard() {
    const page = usePage<{
        productOnboarding?: ProductOnboardingState | null;
    }>();
    const state = page.props.productOnboarding;
    const checklist = state?.checklist;

    if (!state?.show_checklist || !checklist) {
        return null;
    }

    const items = [
        { done: checklist.has_business, label: 'Create Business' },
        { done: checklist.has_screen, label: 'Create Screen' },
        { done: checklist.has_tv, label: 'Pair a TV' },
        { done: checklist.has_publish, label: 'Publish your first Screen' },
    ];

    return (
        <div
            className="border-border bg-card rounded-xl border p-4 shadow-none"
            data-test="onboarding-checklist"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-display text-base font-semibold">
                        Get started with RMSignage
                    </h2>
                    <p className="text-muted-foreground mt-0.5 text-xs">
                        {checklist.complete_count} of {checklist.total} complete
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => {
                        postAction('start', state.step || 1);
                        router.visit('/app/dashboard');
                    }}
                    data-test="onboarding-resume"
                >
                    {state.active ? 'Resume tour' : 'Start tour'}
                </Button>
            </div>
            <ul className="mt-3 space-y-1.5 text-sm">
                {items.map((item) => (
                    <li key={item.label} className="flex items-center gap-2">
                        <span
                            className={cn(
                                'flex size-5 items-center justify-center rounded-full text-[10px] font-bold',
                                item.done
                                    ? 'bg-success/15 text-success'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            {item.done ? '✓' : '○'}
                        </span>
                        <span
                            className={
                                item.done
                                    ? 'text-muted-foreground line-through'
                                    : ''
                            }
                        >
                            {item.label}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function RestartProductTourButton({
    className,
}: {
    className?: string;
} = {}) {
    const page = usePage<{
        productOnboarding?: ProductOnboardingState | null;
    }>();
    const state = page.props.productOnboarding;

    if (
        !state?.can_replay &&
        !state?.eligible &&
        !state?.completed &&
        !state?.skipped
    ) {
        return null;
    }

    return (
        <Button
            type="button"
            variant="outline"
            className={className}
            onClick={() => {
                postAction('restart');
                router.visit('/app/dashboard');
            }}
            data-test="onboarding-restart"
        >
            Replay Product Tour
        </Button>
    );
}
