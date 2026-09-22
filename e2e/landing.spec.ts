import { expect, test } from '@playwright/test';
import { registerCustomer } from './helpers/auth';

/**
 * Phase 13.5 — public marketing landing page.
 *
 * Flow A landing renders · B nav anchors · C Get Started · D Sign In
 * E authenticated CTA · F mobile drawer · G reduced motion.
 */

test.describe('marketing landing page', () => {
    // Flow A — the page renders its shell, hero, and SEO metadata.
    test('renders the hero, navigation, footer, and SEO metadata', async ({
        page,
    }) => {
        await page.goto('/');

        await expect(page).toHaveTitle(/Digital Signage Made Simple/i);
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: /digital signage, made simple/i,
            }),
        ).toBeVisible();

        await expect(page.getByTestId('marketing-hero-supporting')).toHaveText(
            'Create screen designs and playlists, schedule, and publish to any Screen remotely from your RMSignage business.',
        );

        await expect(page.getByTestId('marketing-nav')).toBeVisible();
        await expect(page.getByTestId('theme-toggle').first()).toBeVisible();
        await expect(
            page.getByRole('radiogroup', { name: 'Theme' }).first(),
        ).toBeVisible();
        await expect(page.getByTestId('marketing-hero')).toBeVisible();
        await expect(page.getByTestId('marketing-pricing')).toBeAttached();
        await expect(page.getByTestId('marketing-faq')).toBeAttached();
        await expect(page.getByTestId('marketing-footer')).toBeAttached();

        await expect(page.locator('meta[name="description"]')).toHaveAttribute(
            'content',
            /RMSignage Workspace/i,
        );
        await expect(
            page.locator('script[type="application/ld+json"]'),
        ).toHaveCount(1);

        // No placeholder anchors anywhere on the page.
        await expect(page.locator('a[href="#"]')).toHaveCount(0);
    });

    // Flow B — every navbar anchor scrolls its section into view.
    test('navigation anchors scroll to their sections', async ({ page }) => {
        await page.goto('/');

        for (const id of [
            'product',
            'features',
            'templates',
            'industries',
            'pricing',
            'faq',
        ]) {
            await page.getByTestId(`marketing-nav-link-${id}`).click();
            await expect(page.locator(`#${id}`)).toBeInViewport({
                ratio: 0.1,
            });
        }
    });

    // Flow C — the primary CTA reaches registration.
    test('Get Started goes to registration', async ({ page }) => {
        await page.goto('/');

        await page.getByTestId('marketing-hero-get-started').click();
        await page.waitForURL(/\/register/);

        await expect(
            page.getByRole('button', { name: /create account/i }),
        ).toBeVisible();
    });

    // Flow D — the secondary CTA reaches login.
    test('Sign In goes to the login page', async ({ page }) => {
        await page.goto('/');

        await page.getByTestId('marketing-sign-in').click();
        await page.waitForURL(/\/login/);

        await expect(page.getByLabel('Email address')).toBeVisible();
    });

    // Flow E — signed-in visitors are offered the dashboard, not registration.
    test('authenticated visitors get an Open Dashboard call to action', async ({
        page,
    }) => {
        await registerCustomer(page);

        await page.goto('/');

        await expect(
            page.getByTestId('marketing-open-dashboard'),
        ).toBeVisible();
        await expect(page.getByTestId('marketing-get-started')).toHaveCount(0);
        await expect(page.getByTestId('marketing-sign-in')).toHaveCount(0);

        await page.getByTestId('marketing-open-dashboard').click();
        await page.waitForURL(/\/app\/dashboard/);
    });

    // Flow G — reduced motion renders a static page, not a broken one.
    test('respects prefers-reduced-motion', async ({ browser }) => {
        const context = await browser.newContext({ reducedMotion: 'reduce' });
        const page = await context.newPage();

        await page.goto('/');

        await expect(page.getByTestId('marketing-hero')).toHaveAttribute(
            'data-reduced-motion',
            'true',
        );
        await expect(
            page.getByRole('heading', {
                level: 1,
                name: /digital signage, made simple/i,
            }),
        ).toBeVisible();

        await page.getByTestId('marketing-nav-link-faq').click();
        await expect(page.locator('#faq')).toBeInViewport({ ratio: 0.1 });

        await context.close();
    });
});

// Flow F — the mobile shell uses a drawer, and its links work.
test.describe('marketing landing page on a phone viewport', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('opens the mobile drawer and navigates from it', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByTestId('marketing-hero')).toBeVisible();
        await expect(
            page.getByTestId('marketing-nav-link-pricing'),
        ).toBeHidden();

        await page.getByTestId('marketing-nav-toggle').click();
        const drawer = page.getByTestId('marketing-mobile-nav');
        await expect(drawer).toBeVisible();
        await expect(drawer.getByTestId('theme-toggle')).toBeVisible();

        await page.getByTestId('marketing-mobile-nav-link-pricing').click();
        await expect(drawer).toBeHidden();
        await expect(page.locator('#pricing')).toBeInViewport({ ratio: 0.1 });

        await page.getByTestId('marketing-nav-toggle').click();
        await page.getByTestId('marketing-mobile-get-started').click();
        await page.waitForURL(/\/register/);
    });
});
