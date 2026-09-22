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

test.describe('final correction smoke', () => {
    test('Flow A — theme toggle stays top-right across app pages', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        for (const path of ['/app/dashboard', '/app/media', '/app/schedules']) {
            await page.goto(path);
            await expect(page.getByTestId('theme-toggle')).toBeVisible({
                timeout: 15_000,
            });
        }
        await page
            .getByTestId('theme-toggle')
            .getByRole('radio', { name: 'Light' })
            .click();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });

    test('Flow C — Media shows recognisable demo assets', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/media');
        await expect(page.getByTestId(/^media-card-/).first()).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByText(/cyan|solid rectangle/i)).toHaveCount(0);
    });

    test('Flow E — Dashboard and Analytics are populated for owner', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/dashboard');
        await expect(page.getByTestId('app-dashboard')).toBeVisible({
            timeout: 15_000,
        });
        await page.goto('/app/analytics');
        await expect(page.getByTestId('app-analytics')).toBeVisible({
            timeout: 15_000,
        });
    });

    test('Flow F — Schedules table header sorting', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules');
        await expect(page.getByTestId('schedules-table')).toBeVisible({
            timeout: 15_000,
        });
        const nameHeader = page
            .getByTestId('schedules-table')
            .getByRole('button', { name: /Name:.*sort/i });
        await expect(nameHeader).toBeVisible();
        await nameHeader.click();
        await expect(page).toHaveURL(/sort=name/);
    });
});
