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

test.describe('Super Admin plan management', () => {
    test('super admin can open plans and edit Starter screen limit', async ({
        page,
    }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/subscriptions/plans');
        await expect(page.getByTestId('admin-billing-plans')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('admin-plan-row-starter')).toBeVisible();

        await page.getByTestId('admin-plan-edit-starter').click();
        await expect(page.getByTestId('admin-billing-plan-edit')).toBeVisible();

        const screens = page.getByTestId('admin-plan-screen-limit');
        await expect(screens).toBeVisible();
        const previous = await screens.inputValue();
        const next = previous === '8' ? '5' : '8';
        await screens.fill(next);

        await page.getByTestId('admin-plan-save').click();
        await expect(page.getByTestId('admin-plan-confirm-save')).toBeVisible();
        await page.getByTestId('admin-plan-confirm-save').click();
        await expect(page.getByTestId('admin-plan-confirm-save')).toBeHidden({
            timeout: 15_000,
        });

        await expect(page.getByTestId('admin-billing-plan-edit')).toBeVisible({
            timeout: 15_000,
        });
        await expect(screens).toHaveValue(next);

        // Restore prior value (always a real change — avoid no-op when next was already 5)
        await screens.fill(previous);
        await page.getByTestId('admin-plan-save').click();
        await expect(page.getByTestId('admin-plan-confirm-save')).toBeEnabled();
        await page.getByTestId('admin-plan-confirm-save').click();
        await expect(page.getByTestId('admin-plan-confirm-save')).toBeHidden({
            timeout: 15_000,
        });
        await expect(screens).toHaveValue(previous, { timeout: 15_000 });
    });
});
