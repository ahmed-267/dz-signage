import { expect, test } from '@playwright/test';
import { registerCustomer } from './helpers/auth';

test.describe('theme preference', () => {
    test('light and dark theme toggle works and persists after reload', async ({
        page,
    }) => {
        await registerCustomer(page);

        // Open the sidebar user menu (theme selector lives here).
        await page.getByTestId('sidebar-menu-button').click();
        await expect(
            page.getByRole('radiogroup', { name: 'Theme' }),
        ).toBeVisible();

        await page.getByRole('radio', { name: 'Dark' }).click();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(
            page.evaluate(() => localStorage.getItem('appearance')),
        ).resolves.toBe('dark');

        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);

        await page.getByTestId('sidebar-menu-button').click();
        await page.getByRole('radio', { name: 'Light' }).click();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        await expect(
            page.evaluate(() => localStorage.getItem('appearance')),
        ).resolves.toBe('light');

        await page.reload();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });
});
