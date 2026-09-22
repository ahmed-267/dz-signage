import { expect, test, type Browser, type Page } from '@playwright/test';

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

async function openPlayer(browser: Browser): Promise<Page> {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto('/player');
    await expect(page.getByTestId('player-pairing')).toBeVisible({
        timeout: 15_000,
    });
    return page;
}

test.describe('phase 5 player pairing and publishing', () => {
    test.describe.configure({ mode: 'serial' });

    test('fresh player shows pairing code', async ({ page }) => {
        await page.goto('/player');
        await expect(page.getByTestId('player-pairing')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('player-pairing-code')).toBeVisible();
        await expect(page.getByTestId('player-qr')).toBeVisible();
        const code = await page.getByTestId('player-pairing-code').innerText();
        expect(code.trim()).toMatch(/^DZ-[A-Z2-9]{4}$/);
    });

    test('pair screen via code then publish design', async ({
        browser,
        page,
    }) => {
        test.setTimeout(90_000);

        const player = await openPlayer(browser);
        const code = (
            await player.getByTestId('player-pairing-code').innerText()
        ).trim();

        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');
        await expect(
            page.getByRole('heading', { name: 'Paired TVs', exact: true }),
        ).toBeVisible();

        await page.getByTestId('screens-add').click();
        await page.getByTestId('screens-pair-code').fill(code);
        await page
            .getByTestId('screens-pair-name')
            .fill(`E2E TV ${Date.now()}`);
        await page.getByTestId('screens-pair-confirm').click();

        await page.waitForURL(/\/app\/screens\/\d+/, { timeout: 15_000 });
        await expect(page.getByTestId('screen-detail')).toBeVisible();

        const screenId = Number(/\/app\/screens\/(\d+)/.exec(page.url())?.[1]);
        expect(Number.isFinite(screenId)).toBe(true);

        await expect(player.getByTestId('player-no-content')).toBeVisible({
            timeout: 20_000,
        });

        await page.goto('/app/screen-designs');
        await page.getByTestId('screen-designs-create').click();
        await page.getByTestId('screen-designs-create-landscape').click();
        await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/);
        await page.getByTestId('screen-design-add-text-heading').click();
        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });
        await page.getByTestId('screen-design-publish').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/published|saved/i, { timeout: 10_000 });

        // Publish from Screens library — target this test's own screen, since
        // other specs may add screens to the same workspace concurrently.
        await page.goto('/app/screens');
        await page.getByTestId(`screen-menu-${screenId}`).click();
        await page.getByRole('menuitem', { name: /publish content/i }).click();
        await page.getByTestId('screen-publish-design').selectOption({
            index: 1,
        });
        await page.getByTestId('screen-publish-confirm').click();

        await expect(player.getByTestId('player-ready')).toBeVisible({
            timeout: 30_000,
        });

        await player.context().close();
    });

    test('screens library works on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');
        await expect(
            page.getByRole('heading', { name: 'Paired TVs', exact: true }),
        ).toBeVisible();
    });
});
