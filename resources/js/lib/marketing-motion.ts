import { useEffect, useState } from 'react';
import type { Variants } from 'motion/react';

/**
 * Motion helpers shared by the public marketing page.
 *
 * Every animation on the landing page flows through here so that
 * `prefers-reduced-motion: reduce` collapses it to a static render in one
 * place instead of being re-checked in every section.
 */

const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';

export const MARKETING_EASE = [0.22, 1, 0.36, 1] as const;

export const REVEAL_VIEWPORT = { once: true, amount: 0.2 } as const;

export function usePrefersReducedMotion(): boolean {
    const [reduced, setReduced] = useState(false);

    useEffect(() => {
        const media = window.matchMedia(REDUCED_MOTION_QUERY);

        setReduced(media.matches);

        const onChange = (event: MediaQueryListEvent) =>
            setReduced(event.matches);

        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    return reduced;
}

/** Fade + rise reveal used by section headings and cards. */
export function fadeUp(reduced: boolean, distance = 18): Variants {
    return {
        hidden: { opacity: reduced ? 1 : 0, y: reduced ? 0 : distance },
        visible: {
            opacity: 1,
            y: 0,
            transition: {
                duration: reduced ? 0 : 0.55,
                ease: MARKETING_EASE,
            },
        },
    };
}

/** Parent variant that staggers `fadeUp` children. */
export function stagger(reduced: boolean, amount = 0.07): Variants {
    return {
        hidden: {},
        visible: {
            transition: { staggerChildren: reduced ? 0 : amount },
        },
    };
}

/**
 * Props to spread on a `motion` element for a scroll-triggered reveal.
 * Reduced motion renders the visible state immediately.
 */
export function reveal(reduced: boolean) {
    return reduced
        ? ({ initial: 'visible', animate: 'visible' } as const)
        : ({
              initial: 'hidden',
              whileInView: 'visible',
              viewport: REVEAL_VIEWPORT,
          } as const);
}

/**
 * Infinite ambient loop (drifting glows, pulsing status dots). Returns `null`
 * when reduced motion is requested so callers can skip the animation entirely.
 */
export function ambientLoop(
    reduced: boolean,
    duration: number,
    delay = 0,
): {
    duration: number;
    delay: number;
    repeat: number;
    ease: 'easeInOut';
} | null {
    if (reduced) {
        return null;
    }

    return { duration, delay, repeat: Infinity, ease: 'easeInOut' };
}

/** Anchor navigation that honours the reduced-motion preference. */
export function scrollToSection(id: string, reduced = false): void {
    const target = document.getElementById(id);

    if (!target) {
        return;
    }

    target.scrollIntoView({
        behavior: reduced ? 'auto' : 'smooth',
        block: 'start',
    });

    // Keep the URL shareable without triggering the browser's instant jump.
    // Reuse the existing history state so Inertia's scroll restoration and
    // back/forward handling stay intact.
    window.history.replaceState(window.history.state, '', `#${id}`);
}
