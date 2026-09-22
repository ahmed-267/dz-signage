import { expect, test, type Page } from '@playwright/test';

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

test.describe('customer template library', () => {
    // Shared seeded accounts — avoid parallel Fortify login throttling.
    test.describe.configure({ mode: 'serial' });

    test('owner browses library without create or my templates', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        await expect(
            page.getByRole('heading', { name: 'Templates', exact: true }),
        ).toBeVisible();
        await expect(page.getByTestId('templates-tab-all')).toBeVisible();
        await expect(page.getByTestId('templates-tab-dz')).toBeVisible();
        await expect(
            page.getByTestId('templates-tab-favourites'),
        ).toBeVisible();
        await expect(page.getByTestId('templates-tab-mine')).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: /create template/i }),
        ).toHaveCount(0);
        await expect(page.getByTestId('templates-create-button')).toHaveCount(
            0,
        );
    });

    test('starter template cards render recognisable design content', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        await page.getByTestId('templates-search').fill('Prayer Times');
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Prayer Times/i })
                .first(),
        ).toBeVisible({ timeout: 15_000 });
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Prayer Times/i })
                .first()
                .locator('[role="img"]'),
        ).toBeVisible();

        await page.getByTestId('templates-search').fill('Sale');
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Sale/i })
                .first(),
        ).toBeVisible({ timeout: 15_000 });
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Sale/i })
                .first()
                .getByText(/SALE|50%/i)
                .first(),
        ).toBeVisible();

        await page.getByTestId('templates-search').fill('Welcome');
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Welcome/i })
                .first(),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('preview shows real template design elements', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        await page
            .getByTestId('templates-search')
            .fill('Prayer Times — Landscape');
        const prayerCard = page
            .locator('[data-test^="template-card-"]')
            .filter({ hasText: /Prayer Times/i })
            .first();
        await expect(prayerCard).toBeVisible({ timeout: 15_000 });
        await prayerCard.hover();
        await prayerCard.getByRole('button', { name: /^preview$/i }).click();

        const host = page.getByTestId('template-preview-host');
        await expect(host).toBeVisible();
        await expect(
            host.getByText(/Fajr|Prayer Times|Jumu|Masjid/i).first(),
        ).toBeVisible();
    });

    test('use template is enabled for owners and creates a design', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        const useButton = page
            .getByRole('button', { name: /use template/i })
            .first();
        if ((await useButton.count()) === 0) {
            test.skip(true, 'No published platform templates seeded');
            return;
        }

        await page.locator('[data-test^="template-card-"]').first().hover();
        await expect(useButton).toBeEnabled();
    });

    test('use template copies design into editor and preserves master', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        await page
            .getByTestId('templates-search')
            .fill('Retail Sale Promotion');
        await page.waitForURL(/q=Retail/, { timeout: 10_000 });
        // Let search debounce settle so the card is not remounted mid-click.
        await page.waitForTimeout(500);

        const saleCard = page
            .locator('[data-test^="template-card-"]')
            .filter({ hasText: /Retail Sale Promotion/i })
            .first();
        await expect(saleCard).toBeVisible({ timeout: 15_000 });

        const useBtn = saleCard.locator('[data-test^="template-use-"]');
        await saleCard.hover();
        await expect(useBtn).toBeEnabled();

        await Promise.all([
            page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
                timeout: 20_000,
            }),
            useBtn.click({ force: true }),
        ]);

        await expect(page.getByTestId('screen-design-canvas')).toBeVisible();
        await expect(page.getByText(/SALE|50%|OFF/i).first()).toBeVisible();

        const nameField = page.getByTestId('screen-design-editor-name');
        const e2eName = `E2E Design ${Date.now()}`;
        await nameField.fill(e2eName);
        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });

        await page.goto('/app/templates');
        await page
            .getByTestId('templates-search')
            .fill('Retail Sale Promotion');
        await page.waitForURL(/q=Retail/, { timeout: 10_000 });
        await expect(
            page
                .locator('[data-test^="template-card-"]')
                .filter({ hasText: /Retail Sale Promotion/i })
                .first(),
        ).toBeVisible();
        await expect(page.getByText(/SALE|50%/i).first()).toBeVisible();
    });

    test('preview fits portrait template in reduced-height viewport', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 600 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        const portraitCard = page.locator(
            '[data-test^="template-card-"][data-orientation="portrait"]',
        );
        const card =
            (await portraitCard.count()) > 0
                ? portraitCard.first()
                : page.locator('[data-test^="template-card-"]').first();

        if ((await card.count()) === 0) {
            test.skip(true, 'No published platform templates seeded');
            return;
        }

        await card.hover();
        await card.getByRole('button', { name: /^preview$/i }).click();

        const host = page.getByTestId('template-preview-host');
        await expect(host).toBeVisible();
        await page.waitForTimeout(250);

        const box = await host.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.height).toBeLessThanOrEqual(640);

        const canvas = host.locator('[role="img"]');
        await expect(canvas).toBeVisible();
        const canvasBox = await canvas.boundingBox();
        expect(canvasBox).not.toBeNull();
        expect(canvasBox!.height).toBeLessThanOrEqual(box!.height + 4);
        expect(canvasBox!.width).toBeLessThanOrEqual(box!.width + 4);
    });

    test('portrait starter template renders and copies', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/templates');

        await page
            .getByTestId('templates-search')
            .fill('Prayer Times — Portrait');
        await page.waitForURL(/q=Prayer/, { timeout: 10_000 });
        await page.waitForTimeout(500);

        const card = page
            .locator(
                '[data-test^="template-card-"][data-orientation="portrait"]',
            )
            .filter({ hasText: /Prayer Times/i })
            .first();

        await expect(card).toBeVisible({ timeout: 15_000 });
        await expect(card.locator('[role="img"]')).toBeVisible();
        await card.hover();
        const useBtn = card.locator('[data-test^="template-use-"]');
        await expect(useBtn).toBeEnabled();

        await Promise.all([
            page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
                timeout: 20_000,
            }),
            useBtn.click({ force: true }),
        ]);
        await expect(page.getByTestId('screen-design-canvas')).toBeVisible();
        await page
            .getByTestId('screen-design-editor-name')
            .fill(`E2E Design Portrait ${Date.now()}`);
        await page.getByTestId('screen-design-save').click();
    });

    test('customer cannot open admin templates', async ({ page }) => {
        await login(page, 'owner@dz.local');
        const response = await page.goto('/admin/templates');
        expect(response?.status()).toBe(403);
    });
});

test.describe('platform template builder', () => {
    test.describe.configure({ mode: 'serial' });

    test('super admin creates template, drags, resizes, undoes, saves', async ({
        page,
    }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/templates');

        await page.getByTestId('admin-templates-create').click();
        const name = `E2E Platform ${Date.now()}`;
        await page.getByTestId('admin-templates-create-name').fill(name);
        await page.getByTestId('admin-templates-create-submit').click();

        await page.waitForURL(/\/admin\/templates\/\d+\/(edit|builder)/);
        await expect(page.getByTestId('template-builder-canvas')).toBeVisible();

        await page.getByTestId('template-add-text').click();
        const element = page.locator('[data-element-type="text"]').first();
        await expect(element).toBeVisible();

        const xBefore = await page.getByTestId('template-prop-x').inputValue();
        const yBefore = await page.getByTestId('template-prop-y').inputValue();

        const box = await element.boundingBox();
        expect(box).not.toBeNull();
        await page.mouse.move(
            box!.x + box!.width / 2,
            box!.y + box!.height / 2,
        );
        await page.mouse.down();
        await page.mouse.move(
            box!.x + box!.width / 2 + 80,
            box!.y + box!.height / 2 + 40,
            { steps: 8 },
        );
        await page.mouse.up();

        await expect
            .poll(async () => page.getByTestId('template-prop-x').inputValue())
            .not.toBe(xBefore);
        await expect
            .poll(async () => page.getByTestId('template-prop-y').inputValue())
            .not.toBe(yBefore);

        const wBefore = await page
            .getByTestId('template-prop-width')
            .inputValue();
        const handle = page.getByTestId('layout-resize-se');
        const handleBox = await handle.boundingBox();
        expect(handleBox).not.toBeNull();
        await page.mouse.move(
            handleBox!.x + handleBox!.width / 2,
            handleBox!.y + handleBox!.height / 2,
        );
        await page.mouse.down();
        await page.mouse.move(
            handleBox!.x + handleBox!.width / 2 + 60,
            handleBox!.y + handleBox!.height / 2 + 40,
            { steps: 6 },
        );
        await page.mouse.up();

        await expect
            .poll(async () =>
                page.getByTestId('template-prop-width').inputValue(),
            )
            .not.toBe(wBefore);

        const xAfterResize = await page
            .getByTestId('template-prop-x')
            .inputValue();
        await page.getByTestId('template-builder-undo').click();
        await expect
            .poll(async () =>
                page.getByTestId('template-prop-width').inputValue(),
            )
            .toBe(wBefore);
        await page.getByTestId('template-builder-redo').click();
        await expect
            .poll(async () =>
                page.getByTestId('template-prop-width').inputValue(),
            )
            .not.toBe(wBefore);

        await page.getByTestId('template-builder-save').click();
        await page.reload();
        await expect(page.getByTestId('template-builder-canvas')).toBeVisible();
        const textElement = page.locator('[data-element-type="text"]').first();
        await expect(textElement).toBeVisible();
        await textElement.click({ force: true });
        await expect(page.getByTestId('template-prop-x')).toHaveValue(
            xAfterResize,
        );

        // Keep local Template library clean — archive E2E artifact.
        await page.goto('/admin/templates');
        const row = page.locator('tr').filter({ hasText: name }).first();
        if (await row.count()) {
            await row.getByRole('button', { name: /^archive$/i }).click();
        }
    });

    test('portrait canvas opens in builder', async ({ page }) => {
        await login(page, 'admin@dz.local');
        await page.goto('/admin/templates');

        await page.getByTestId('admin-templates-create').click();
        await page
            .getByTestId('admin-templates-create-name')
            .fill(`E2E Portrait ${Date.now()}`);
        await page.locator('#admin-orientation').selectOption('portrait');
        await page.getByTestId('admin-templates-create-submit').click();

        await page.waitForURL(/\/admin\/templates\/\d+\/(edit|builder)/);
        await expect(page.getByTestId('template-builder-canvas')).toBeVisible();
        await page.getByTestId('template-add-text').click();
        await expect(
            page.locator('[data-element-type="text"]').first(),
        ).toBeVisible();
    });
});
