import { expect, test, type Page } from '@playwright/test';

async function login(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
    // Ensure product tour cannot intercept playlist/TV interactions.
    await page.evaluate(async () => {
        const token = (
            document.querySelector(
                'meta[name="csrf-token"]',
            ) as HTMLMetaElement | null
        )?.content;
        await fetch('/app/product-onboarding', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'skip' }),
        });
    });
}

type TransitionSample = {
    lifecycle: string | null;
    transition: string | null;
    bothMounted: boolean;
    outOpacity: number | null;
    inOpacity: number | null;
    outPct: number | null;
    inPct: number | null;
};

async function sampleNextTransition(
    page: Page,
    samples = 18,
    intervalMs = 40,
): Promise<{
    transition: string | null;
    hadGradualFade: boolean;
    hadSimultaneousSlide: boolean;
    bothMountedDuringAnim: boolean;
    committedIdle: boolean;
    samples: TransitionSample[];
}> {
    return page.evaluate(
        async ([count, interval]) => {
            const toggle = document.querySelector(
                '[data-test="playlist-player-toggle"]',
            ) as HTMLButtonElement | null;
            if (toggle?.getAttribute('data-playing') === 'true') {
                toggle.click();
            }

            await new Promise((r) => setTimeout(r, 50));

            const next = document.querySelector(
                '[data-test="playlist-player-next"]',
            ) as HTMLButtonElement | null;
            if (!next) {
                return {
                    transition: null,
                    hadGradualFade: false,
                    hadSimultaneousSlide: false,
                    bothMountedDuringAnim: false,
                    committedIdle: false,
                    samples: [] as TransitionSample[],
                };
            }

            const read = (): TransitionSample => {
                const player = document.querySelector(
                    '[data-test="playlist-player"]',
                );
                const out = document.querySelector(
                    '[data-test="playlist-player-outgoing"]',
                ) as HTMLElement | null;
                const inn = document.querySelector(
                    '[data-test="playlist-player-incoming"]',
                ) as HTMLElement | null;
                const stage = document.querySelector(
                    '[data-test="playlist-player-stage"]',
                );
                const stageW = stage?.getBoundingClientRect().width || 1;
                const layer = (el: HTMLElement | null) => {
                    if (!el) {
                        return {
                            opacity: null as number | null,
                            pct: null as number | null,
                        };
                    }
                    const cs = getComputedStyle(el);
                    let tx = 0;
                    if (cs.transform && cs.transform !== 'none') {
                        tx = new DOMMatrixReadOnly(cs.transform).m41;
                    }
                    return {
                        opacity:
                            Math.round(parseFloat(cs.opacity) * 1000) / 1000,
                        pct: Math.round((tx / stageW) * 100),
                    };
                };
                const o = layer(out);
                const i = layer(inn);
                return {
                    lifecycle:
                        player?.getAttribute('data-transition-lifecycle') ??
                        null,
                    transition: player?.getAttribute('data-transition') ?? null,
                    bothMounted: !!(out && inn),
                    outOpacity: o.opacity,
                    inOpacity: i.opacity,
                    outPct: o.pct,
                    inPct: i.pct,
                };
            };

            const collected: TransitionSample[] = [];
            collected.push(read());
            next.click();

            for (let n = 0; n < (count as number); n += 1) {
                await new Promise((r) => setTimeout(r, interval as number));
                collected.push(read());
            }

            // Allow commit to settle after the last animating frame.
            for (let n = 0; n < 10; n += 1) {
                await new Promise((r) => setTimeout(r, 40));
                collected.push(read());
                const last = collected[collected.length - 1];
                if (last.lifecycle === 'idle' && !last.bothMounted) {
                    break;
                }
            }

            const animating = collected.filter(
                (s) => s.lifecycle === 'animating',
            );
            const transition =
                animating.find((s) => s.transition)?.transition ??
                collected.find((s) => s.transition)?.transition ??
                null;

            return {
                transition,
                hadGradualFade: collected.some(
                    (s) =>
                        s.bothMounted &&
                        s.outOpacity !== null &&
                        s.inOpacity !== null &&
                        s.outOpacity > 0.05 &&
                        s.outOpacity < 0.95 &&
                        s.inOpacity > 0.05 &&
                        s.inOpacity < 0.95,
                ),
                hadSimultaneousSlide: collected.some(
                    (s) =>
                        s.bothMounted &&
                        s.outPct !== null &&
                        s.inPct !== null &&
                        Math.abs(s.outPct) > 10 &&
                        Math.abs(s.inPct) > 10,
                ),
                bothMountedDuringAnim: collected.some(
                    (s) => s.bothMounted && s.lifecycle === 'animating',
                ),
                committedIdle: collected.some(
                    (s, idx) =>
                        idx > 2 && s.lifecycle === 'idle' && !s.bothMounted,
                ),
                samples: collected,
            };
        },
        [samples, intervalMs],
    );
}

test.describe('transitions / onboarding / current content', () => {
    test('playlist preview fade crossfades both layers before commit', async ({
        page,
    }) => {
        test.setTimeout(60_000);
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');

        const card = page
            .locator('[data-test^="playlist-card-"]')
            .filter({ hasText: 'Cafe Daily Rotation' });
        await expect(card).toBeVisible({ timeout: 15_000 });
        await card.locator('[data-test^="playlist-preview-"]').click();

        await expect(page.getByTestId('playlist-preview-dialog')).toBeVisible();
        await expect(page.getByTestId('playlist-player-toggle')).toBeEnabled({
            timeout: 20_000,
        });
        await expect(page.getByTestId('playlist-player')).toHaveAttribute(
            'data-transition',
            'fade',
            { timeout: 10_000 },
        );
        await expect(page.getByTestId('playlist-player')).toHaveAttribute(
            'data-transition-ms',
            '700',
        );

        const result = await sampleNextTransition(page);
        expect(result.transition).toBe('fade');
        expect(result.bothMountedDuringAnim).toBe(true);
        expect(result.hadGradualFade).toBe(true);
        expect(result.committedIdle).toBe(true);
    });

    test('playlist preview slide left/right push both layers simultaneously', async ({
        page,
    }) => {
        test.setTimeout(60_000);
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');

        const card = page
            .locator('[data-test^="playlist-card-"]')
            .filter({ hasText: 'test2' });
        await expect(card).toBeVisible({ timeout: 15_000 });
        await card.locator('[data-test^="playlist-preview-"]').click();

        await expect(page.getByTestId('playlist-preview-dialog')).toBeVisible();
        await expect(page.getByTestId('playlist-player-toggle')).toBeEnabled({
            timeout: 20_000,
        });
        await page.getByTestId('playlist-player-restart').click();
        await page.waitForTimeout(300);

        const first = await sampleNextTransition(page);
        expect(['slide_left', 'slide_right']).toContain(first.transition);
        expect(first.bothMountedDuringAnim).toBe(true);
        expect(first.hadSimultaneousSlide).toBe(true);
        expect(first.committedIdle).toBe(true);

        const second = await sampleNextTransition(page);
        expect(['slide_left', 'slide_right']).toContain(second.transition);
        expect(second.bothMountedDuringAnim).toBe(true);
        expect(second.hadSimultaneousSlide).toBe(true);
    });

    test('paired tvs now showing name opens content preview', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');

        const nameButton = page
            .getByRole('button', { name: /Preview current content:/i })
            .first();
        await expect(nameButton).toBeVisible({ timeout: 15_000 });
        await nameButton.click();

        await expect(
            page.getByText(
                /Read-only preview of what this TV should be showing/i,
            ),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('product onboarding navigates to paired tvs before that coach mark', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');

        await page.evaluate(async () => {
            const token = (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement | null
            )?.content;
            await fetch('/app/product-onboarding', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'restart' }),
            });
            await fetch('/app/product-onboarding', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'advance', step: 6 }),
            });
        });

        await page.goto('/app/dashboard');
        await expect(page.getByTestId('product-onboarding-tour')).toBeVisible({
            timeout: 20_000,
        });
        await expect(
            page.getByTestId('product-onboarding-tour'),
        ).toHaveAttribute('data-onboarding-step', '6');
        await expect(
            page.getByTestId('product-onboarding-tour'),
        ).toHaveAttribute('data-onboarding-route', '/app/screens');
        await expect(page).toHaveURL(/\/app\/screens/);
        await expect(
            page.locator('h1', { hasText: 'Paired TVs' }),
        ).toBeVisible();
    });
});
