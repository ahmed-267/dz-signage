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

test.describe('post-phase 15 brand kit + locations', () => {
    test.describe.configure({ mode: 'serial' });

    test('Flow B — Brand Kit persists colours and fonts', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/brand-kit');
        await expect(page.getByTestId('brand-kit-name')).toBeVisible({
            timeout: 15_000,
        });

        await page.getByTestId('brand-kit-name').fill('North & Bean Café');
        await page
            .getByTestId('brand-kit-tagline')
            .fill('Coffee worth the queue');
        await page.getByTestId('brand-kit-primary_color').fill('#C47A3F');
        await page
            .getByTestId('brand-kit-heading-font')
            .selectOption({ index: 1 });
        await page
            .getByTestId('brand-kit-body-font')
            .selectOption({ index: 1 });
        await page.getByTestId('brand-kit-save').click();

        await page.reload();
        await expect(page.getByTestId('brand-kit-name')).toHaveValue(
            'North & Bean Café',
        );
        await expect(page.getByTestId('brand-kit-tagline')).toHaveValue(
            'Coffee worth the queue',
        );
        await expect(page.getByTestId('brand-kit-preview')).toBeVisible();
    });

    test('Flow E — Locations list create and detail', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/locations');
        await expect(page.getByTestId('locations-index')).toBeVisible({
            timeout: 15_000,
        });

        await page.getByTestId('locations-create').click();
        await expect(page.getByTestId('location-create')).toBeVisible();
        const unique = `E2E Location ${Date.now()}`;
        await page.getByTestId('location-name').fill(unique);
        await page.getByTestId('location-city').fill('Leeds');
        await page.locator('#location-country').fill('United Kingdom');
        await page.getByTestId('location-submit').click();

        await expect(page.getByTestId('location-show')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByText(unique).first()).toBeVisible();
        await expect(page.getByText(/Leeds/i).first()).toBeVisible();

        await page.goto('/app/locations');
        await expect(page.getByTestId('locations-index')).toBeVisible();
        await expect(page.getByText(unique).first()).toBeVisible();
    });

    test('Flow C — Playlist cards render layout previews', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');
        await expect(page.getByTestId(/^playlist-card-/).first()).toBeVisible({
            timeout: 15_000,
        });
        // At least one card should expose a schema-backed preview mount.
        await expect(
            page.locator('[data-test^="playlist-preview-"]').first(),
        ).toBeVisible();
    });
});
