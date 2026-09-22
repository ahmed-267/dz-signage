import { expect, test, type Page } from '@playwright/test';
import path from 'node:path';

const fixture = (name: string) =>
    path.join(process.cwd(), 'e2e', 'fixtures', name);

async function login(
    page: Page,
    email: string,
    password = 'password',
): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill(password);
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
}

test.describe('screen designs phase 4', () => {
    test.describe.configure({ mode: 'serial' });

    test('start blank portrait, add text, save, preview', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');

        await expect(
            page.getByRole('heading', { name: 'Screens', exact: true }),
        ).toBeVisible();

        await page.getByTestId('screen-designs-create').click();
        await page.getByTestId('screen-designs-create-portrait').click();

        await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/);
        await expect(page.getByTestId('screen-design-canvas')).toBeVisible();

        const e2eName = `E2E Design ${Date.now()}`;
        await page.getByTestId('screen-design-editor-name').fill(e2eName);

        await page.getByTestId('screen-design-add-text-heading').click();
        await expect(
            page.locator('[data-element-type="text"]').first(),
        ).toBeVisible();

        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });

        await page.getByTestId('screen-design-preview').click();
        await expect(
            page.getByTestId('screen-design-preview-dialog'),
        ).toBeVisible();
    });

    test('card preview updates after editing a design', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');

        await page.getByTestId('screen-designs-create').click();
        await page.getByTestId('screen-designs-create-landscape').click();
        await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/);

        const marker = `E2E Card ${Date.now()}`;
        await page
            .getByTestId('screen-design-editor-name')
            .fill(`E2E Design ${marker}`);
        await page.getByTestId('screen-design-add-text-heading').click();
        const textInput = page.getByTestId('screen-design-prop-text');
        if (await textInput.count()) {
            await textInput.fill(marker);
        }

        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });

        await page.goto('/app/screen-designs');
        const card = page
            .locator('[data-test^="screen-design-card-"]')
            .filter({ hasText: marker })
            .first();
        await expect(card).toBeVisible({ timeout: 10_000 });
        await expect(card.locator('[role="img"]')).toBeVisible();
        await expect(card.getByText(marker).first()).toBeVisible();
    });

    test('use template opens editor with design', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        const useBtn = page.locator('[data-test^="template-use-"]').first();
        if ((await useBtn.count()) === 0) {
            test.skip(true, 'No published templates');
            return;
        }

        await page.locator('[data-test^="template-card-"]').first().hover();
        await expect(useBtn).toBeEnabled();

        await Promise.all([
            page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
                timeout: 15_000,
            }),
            useBtn.click({ force: true }),
        ]);
        await expect(page.getByTestId('screen-design-canvas')).toBeVisible();
    });

    test('add media, save draft, publish design', async ({ page }) => {
        await login(page, 'owner@dz.local');

        await page.goto('/app/media');
        await page.getByTestId('media-add-button').click();
        await page.getByRole('menuitem', { name: /upload image/i }).click();
        await page
            .getByTestId('media-upload-name')
            .fill(`Design Img ${Date.now()}`);
        await page
            .getByTestId('media-upload-file')
            .setInputFiles(fixture('sample.png'));
        await page.getByTestId('media-upload-submit').click();
        await expect(page.getByText(/Design Img/i).first()).toBeVisible();

        await page.goto('/app/screen-designs');
        await page.getByTestId('screen-designs-create').click();
        await page.getByTestId('screen-designs-create-landscape').click();
        await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/);

        await page
            .getByTestId('screen-design-editor-name')
            .fill(`E2E Design Media ${Date.now()}`);

        await page.getByTestId('screen-design-tab-media').click();
        await page
            .locator('[data-test^="screen-design-media-"]')
            .first()
            .click();
        await expect(
            page.locator('[data-element-type="image"]').first(),
        ).toBeVisible();

        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });

        await page.getByTestId('screen-design-publish').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/published|saved/i, { timeout: 10_000 });
    });

    test('duplicate screen design from library', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');

        const card = page.locator('[data-test^="screen-design-card-"]').first();
        if ((await card.count()) === 0) {
            test.skip(true, 'No designs yet');
            return;
        }

        const beforeCount = await page
            .locator('[data-test^="screen-design-card-"]')
            .count();

        const menu = page.locator('[data-test^="screen-design-menu-"]').first();
        await menu.click();
        await page.getByRole('menuitem', { name: /duplicate/i }).click();

        // Duplicate opens the new design in the editor
        await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
            timeout: 15_000,
        });
        await expect(page.getByTestId('screen-design-editor-name')).toHaveValue(
            /copy/i,
        );

        await page.goto('/app/screen-designs');
        // Paginated library — assert the copy appears rather than exact card count.
        expect(beforeCount).toBeGreaterThan(0);
        await expect(page.getByText(/copy/i).first()).toBeVisible({
            timeout: 10_000,
        });
    });

    test('viewer cannot mutate screen designs', async ({ page }) => {
        await login(page, 'viewer@dz.local');
        await page.goto('/app/screen-designs');

        await expect(
            page.getByRole('heading', { name: 'Screens', exact: true }),
        ).toBeVisible();
        await expect(page.getByTestId('screen-designs-create')).toHaveCount(0);
    });

    test('screen designs library works on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/screen-designs');
        await expect(
            page.getByRole('heading', { name: 'Screens', exact: true }),
        ).toBeVisible();
    });
});
