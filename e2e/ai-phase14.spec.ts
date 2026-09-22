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

test.describe('phase 14 AI content generation', () => {
    test('Flow C — Media Generate with AI saves an asset', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/media');
        await page.getByTestId('media-add-button').click();
        await page.getByTestId('media-generate-ai').click();
        await expect(page.getByTestId('ai-image-dialog')).toBeVisible();

        if (await page.getByTestId('ai-unavailable').isVisible()) {
            test.skip(true, 'AI is not available in this environment');
        }

        await page
            .getByTestId('ai-image-prompt')
            .fill('Premium iced coffee on a dark elegant café background');
        await page.getByTestId('ai-image-generate').click();
        await expect(page.getByTestId('ai-image-variants')).toBeVisible({
            timeout: 30_000,
        });
        await page.getByTestId('ai-image-variant-0').click();
        await page.getByTestId('ai-image-name').fill('E2E AI Coffee');
        await page.getByTestId('ai-image-save').click();
        await expect(page.getByText('E2E AI Coffee').first()).toBeVisible({
            timeout: 15_000,
        });
    });

    test('Flow E — Create Design with AI opens the editor', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');
        await page
            .getByRole('button', { name: 'Create Screen' })
            .first()
            .click();
        await page.getByTestId('screen-designs-create-ai').click();
        await expect(page.getByTestId('ai-design-dialog')).toBeVisible();

        if (await page.getByTestId('ai-unavailable').isVisible()) {
            test.skip(true, 'AI is not available in this environment');
        }

        await page
            .getByTestId('ai-design-prompt')
            .fill(
                'Landscape summer sale for a clothing store with large 40% OFF heading',
            );
        await page.getByTestId('ai-design-generate').click();
        await expect(page.getByTestId('ai-design-concepts')).toBeVisible({
            timeout: 30_000,
        });
        await page.getByTestId('ai-design-concept-0').click();
        await expect(page).toHaveURL(/\/app\/screen-designs\/\d+\/edit/, {
            timeout: 30_000,
        });
        await expect(page.getByTestId('screen-design-tab-ai')).toBeVisible();
    });

    test('Flow A — Write with AI inserts text in the editor', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');
        await page
            .getByRole('button', { name: 'Create Screen' })
            .first()
            .click();
        await page.getByTestId('screen-designs-create-landscape').click();
        await expect(page).toHaveURL(/\/app\/screen-designs\/\d+\/edit/);

        await page.getByTestId('screen-design-tab-ai').click();
        await page.getByTestId('screen-design-ai-write').click();

        if (await page.getByTestId('ai-unavailable').isVisible()) {
            test.skip(true, 'AI is not available in this environment');
        }

        await page
            .getByTestId('ai-text-prompt')
            .fill('Short headline for iced coffee summer promo');
        await page.getByTestId('ai-text-generate').click();
        await expect(page.getByTestId('ai-text-options')).toBeVisible({
            timeout: 20_000,
        });
        await page.getByTestId('ai-text-option-0').click();
        await page.getByTestId('ai-text-insert').click();
        await expect(page.getByTestId('screen-design-prop-text')).toBeVisible();
    });

    test('Flow H — Viewer cannot open AI generation', async ({ page }) => {
        await login(page, 'viewer@dz.local');
        await page.goto('/app/media');
        await expect(page.getByTestId('media-add-button')).toHaveCount(0);
    });

    test('Flow I — Media AI dialog works on a phone viewport', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/media');
        await page.getByTestId('media-add-button').click();
        await page.getByTestId('media-generate-ai').click();
        await expect(page.getByTestId('ai-image-dialog')).toBeVisible();
    });
});
