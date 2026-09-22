import { expect, test, type Page } from '@playwright/test';

async function login(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
}

test.describe('phase 13 billing', () => {
    test.describe.configure({ mode: 'serial' });

    test('Flow A — Billing tab shows catalog pricing even without Stripe', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/settings/billing');
        await expect(page.getByTestId('app-billing')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('billing-tabs')).toBeVisible();
        await page.getByTestId('billing-tab-plans').click();
        await expect(page.getByTestId('billing-plan-starter')).toBeVisible();
        await expect(page.getByTestId('billing-plan-business')).toBeVisible();
        await expect(page.getByTestId('billing-plan-enterprise')).toBeVisible();
        // Plans tab defaults to annual (£15 / £39 equivalents); monthly shows £19 / £49.
        await page.getByTestId('billing-interval-monthly').click();
        await expect(page.getByText('£19.00').first()).toBeVisible();
        await expect(page.getByText('£49.00').first()).toBeVisible();
        // When Stripe is absent, checkout stays disabled with a clear notice.
        const notice = page.getByTestId('billing-config-notice');
        if (await notice.isVisible()) {
            await expect(
                page.getByTestId('billing-checkout-disabled-hint'),
            ).toBeVisible();
        }
    });

    test('Flow H — Admin subscriptions and invoices', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/subscriptions');
        await expect(
            page
                .getByTestId('admin-subscriptions')
                .or(page.getByTestId('admin-billing-unavailable'))
                .first(),
        ).toBeVisible({ timeout: 15_000 });
        await page.goto('/admin/invoices');
        await expect(
            page
                .getByTestId('admin-invoices')
                .or(page.getByTestId('admin-billing-unavailable'))
                .first(),
        ).toBeVisible();
    });

    test('Flow I — Viewer denied billing management / Owner can view', async ({
        page,
    }) => {
        await login(page, 'viewer@dz.local');
        const response = await page.goto('/app/settings/billing');
        // Viewer cannot view billing → 403
        expect(response?.status()).toBe(403);

        await login(page, 'owner@dz.local');
        await page.goto('/app/settings/billing');
        await expect(page.getByTestId('app-billing')).toBeVisible();
    });

    test('Flow mobile — Billing usable on mobile viewport', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/settings/billing');
        await expect(page.getByTestId('app-billing')).toBeVisible();
    });
});
