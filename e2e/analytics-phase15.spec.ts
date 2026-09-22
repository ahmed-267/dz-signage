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

test.describe('Analytics Phase 15 visualisations', () => {
    test('owner dashboard shows real chart surfaces', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/dashboard');
        await expect(page.getByTestId('app-dashboard')).toBeVisible();
        await expect(
            page.locator('.recharts-responsive-container').first(),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('owner analytics charts respond to date range', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/analytics');
        await expect(page.getByTestId('app-analytics')).toBeVisible();
        await expect(page.getByTestId('analytics-tabs')).toBeVisible();
        await expect(page.getByTestId('analytics-tab-overview')).toBeVisible();
        await expect(
            page.locator('.recharts-responsive-container').first(),
        ).toBeVisible({ timeout: 15_000 });

        await page.getByTestId('analytics-range').selectOption('7d');
        await page.waitForURL(/range=7d/);
        await expect(
            page.locator('.recharts-responsive-container').first(),
        ).toBeVisible();

        await page.getByTestId('analytics-range').selectOption('30d');
        await page.waitForURL(/range=30d/);

        await page.getByTestId('analytics-tab-tvs').click();
        await page.waitForURL(/tab=tvs/);
        await expect(page.getByTestId('analytics-panel-tvs')).toBeVisible();
        await expect(page).toHaveURL(/range=30d/);
    });
});
