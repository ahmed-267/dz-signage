import { expect, test } from '@playwright/test';
import { registerCustomer } from './helpers/auth';

test.describe('theme preference', () => {
    test('light and dark theme toggle works and persists after reload', async ({
        page,
    }) => {
        await registerCustomer(page);

        const themeToggle = page.getByTestId('theme-toggle');
        await expect(themeToggle).toBeVisible({ timeout: 15_000 });
        await expect(
            page.getByRole('radiogroup', { name: 'Theme' }),
        ).toBeVisible();

        await themeToggle.getByRole('radio', { name: 'Dark' }).click();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(
            page.evaluate(() => localStorage.getItem('appearance')),
        ).resolves.toBe('dark');

        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await expect(page.getByTestId('theme-toggle')).toBeVisible();

        await page
            .getByTestId('theme-toggle')
            .getByRole('radio', { name: 'Light' })
            .click();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
        await expect(
            page.evaluate(() => localStorage.getItem('appearance')),
        ).resolves.toBe('light');

        await page.reload();
        await expect(page.locator('html')).not.toHaveClass(/dark/);
    });
});
