import { expect, test } from '@playwright/test';

test.describe('application surfaces', () => {
    test('landing page loads', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByText('RMSignage').first()).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Get Started' }).first(),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Sign In' }).first(),
        ).toBeVisible();
    });

    test('login page loads', async ({ page }) => {
        await page.goto('/login');

        await expect(
            page.getByRole('textbox', { name: /email/i }),
        ).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
    });

    test('customer app redirects unauthenticated users to login', async ({
        page,
    }) => {
        await page.goto('/app/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('admin area redirects unauthenticated users to login', async ({
        page,
    }) => {
        await page.goto('/admin/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('player renders standalone pairing surface', async ({ page }) => {
        await page.goto('/player');

        await expect(page.getByTestId('player-root')).toBeVisible();
        await expect(page.getByTestId('player-pairing')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByText('Dashboard')).toHaveCount(0);
        await expect(page.getByText('Super Admin')).toHaveCount(0);
    });
});
