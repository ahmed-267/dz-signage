import { expect, test } from '@playwright/test';
import path from 'node:path';
import { registerCustomer } from './helpers/auth';

const fixture = (name: string) =>
    path.join(process.cwd(), 'e2e', 'fixtures', name);

test.describe('media library phase 2', () => {
    test('upload image appears in library', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /upload image/i }).click();
        await page.getByTestId('media-upload-name').fill('Sample Promo');
        await page
            .getByTestId('media-upload-file')
            .setInputFiles(fixture('sample.png'));
        await page.getByTestId('media-upload-submit').click();

        await expect(page.getByText('Sample Promo').first()).toBeVisible();
    });

    test('add text appears in text tab', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add text/i }).click();
        await page.getByTestId('media-text-name').fill('Friday Prayer Notice');
        await page
            .getByTestId('media-text-content')
            .fill("Jumu'ah begins at 1:30 PM.");
        await page.getByTestId('media-upload-submit').click();

        await page.getByTestId('media-tab-text').click();
        await expect(
            page.getByText('Friday Prayer Notice').first(),
        ).toBeVisible();
    });

    test('add link and open preview', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add link/i }).click();
        await page.getByTestId('media-link-name').fill('Organisation Website');
        await page.getByTestId('media-link-url').fill('https://example.com');
        await page.getByTestId('media-upload-submit').click();

        await expect(
            page.getByText('Organisation Website').first(),
        ).toBeVisible();

        await page
            .getByRole('button', { name: /preview organisation website/i })
            .click();
        await expect(page.getByTestId('media-preview')).toBeVisible();
        await expect(
            page.getByText('https://example.com').first(),
        ).toBeVisible();
    });

    test('rename media', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add text/i }).click();
        await page.getByTestId('media-text-name').fill('Old Name');
        await page.getByTestId('media-text-content').fill('Body');
        await page.getByTestId('media-upload-submit').click();
        await expect(page.getByText('Old Name').first()).toBeVisible();

        await page
            .getByTestId(/^media-card-/)
            .first()
            .hover();
        await page
            .getByTestId(/^media-card-/)
            .first()
            .getByRole('button', { name: /actions/i })
            .click();
        await page.getByRole('menuitem', { name: /rename/i }).click();
        await page.getByTestId('media-rename-input').fill('New Name');
        await page.getByRole('button', { name: /^save$/i }).click();

        await expect(page.getByText('New Name').first()).toBeVisible();
    });

    test('replace image updates preview', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /upload image/i }).click();
        await page.getByTestId('media-upload-name').fill('Replace Target');
        await page
            .getByTestId('media-upload-file')
            .setInputFiles(fixture('sample.png'));
        await page.getByTestId('media-upload-submit').click();
        await expect(page.getByText('Replace Target').first()).toBeVisible();

        await page
            .getByTestId(/^media-card-/)
            .first()
            .hover();
        await page
            .getByTestId(/^media-card-/)
            .first()
            .getByRole('button', { name: /actions/i })
            .click();
        await page.getByRole('menuitem', { name: /replace/i }).click();
        await page
            .getByTestId('media-upload-file')
            .setInputFiles(fixture('replace.png'));
        await page.getByTestId('media-upload-submit').click();

        await expect(page.getByText('Replace Target').first()).toBeVisible();
    });

    test('delete media removes it', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add text/i }).click();
        await page.getByTestId('media-text-name').fill('Delete Me');
        await page.getByTestId('media-text-content').fill('Gone soon');
        await page.getByTestId('media-upload-submit').click();
        await expect(page.getByText('Delete Me').first()).toBeVisible();

        await page
            .getByTestId(/^media-card-/)
            .first()
            .hover();
        await page
            .getByTestId(/^media-card-/)
            .first()
            .getByRole('button', { name: /actions/i })
            .click();
        await page.getByRole('menuitem', { name: /delete/i }).click();
        await page.getByTestId('media-delete-confirm').click();

        await expect(page.getByText('Delete Me')).toHaveCount(0);
    });

    test('workspace switch shows different media collections', async ({
        page,
    }) => {
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add text/i }).click();
        await page.getByTestId('media-text-name').fill('Workspace A Only');
        await page.getByTestId('media-text-content').fill('Isolation');
        await page.getByTestId('media-upload-submit').click();
        await expect(page.getByText('Workspace A Only').first()).toBeVisible();

        await page.goto('/app/workspaces/create');
        await page.locator('#name').fill('Media Workspace B');
        await page.locator('#industry').selectOption({ index: 2 });
        await page.locator('#country').selectOption('United Kingdom');
        await page.locator('#timezone').selectOption('Europe/London');
        await page.getByRole('button', { name: /create business/i }).click();
        await page.waitForURL(/\/app\/dashboard/);

        await page.goto('/app/media');
        await expect(page.getByText('Workspace A Only')).toHaveCount(0);
    });
});

test.describe('media mobile smoke', () => {
    test('media library add and preview on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await registerCustomer(page);
        await page.goto('/app/media');

        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /add text/i }).click();
        await page.getByTestId('media-text-name').fill('Mobile Notice');
        await page.getByTestId('media-text-content').fill('Works on phones');
        await page.getByTestId('media-upload-submit').click();

        await expect(page.getByText('Mobile Notice').first()).toBeVisible();
        await page
            .getByRole('button', { name: /preview mobile notice/i })
            .click();
        await expect(page.getByTestId('media-preview')).toBeVisible();
    });
});
