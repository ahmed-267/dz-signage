import { expect, test, type Page } from '@playwright/test';
import { registerCustomer } from './helpers/auth';
import {
    publishPlaylistToScreen,
    seedPublishedDesigns,
} from './helpers/playlists';

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

async function itemOrder(page: Page): Promise<string[]> {
    return page
        .locator('[data-test="playlist-items"] > li')
        .evaluateAll((items) =>
            items.map((item) => item.getAttribute('data-design-name') ?? ''),
        );
}

test.describe('playlists phase 7', () => {
    test.describe.configure({ mode: 'serial' });

    const stamp = Date.now();
    const marker = `P${stamp}`;
    const playlistName = `E2E Playlist ${stamp}`;

    let designNames: string[] = [];
    let editorUrl = '';

    test.beforeAll(() => {
        designNames = seedPublishedDesigns(marker, 3).map(
            (design) => design.name,
        );
    });

    test('create a playlist with three published designs and save', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');

        await expect(
            page.getByRole('heading', { name: 'Playlists', exact: true }),
        ).toBeVisible();

        await page.getByTestId('playlists-create').click();
        await page.getByTestId('playlists-create-name').fill(playlistName);
        await page.getByTestId('playlists-create-submit').click();

        await page.waitForURL(/\/app\/playlists\/\d+\/edit/, {
            timeout: 20_000,
        });
        editorUrl = page.url();

        for (const designName of designNames) {
            await page.getByTestId('playlist-add-design').click();
            await expect(
                page.getByTestId('playlist-design-picker'),
            ).toBeVisible();

            await page.getByTestId('playlist-picker-search').fill(marker);

            const card = page
                .locator('[data-test^="playlist-picker-design-"]')
                .filter({ hasText: designName });
            await expect(card).toHaveCount(1, { timeout: 15_000 });
            await card.click();

            await expect(
                page.getByTestId('playlist-design-picker'),
            ).toBeHidden();
        }

        await expect(
            page.locator('[data-test="playlist-items"] > li'),
        ).toHaveCount(3);

        // Three ten-second items — a thirty second loop.
        for (let index = 0; index < 3; index += 1) {
            const duration = page.getByTestId(
                `playlist-item-duration-${index}`,
            );
            await duration.fill('10');
            await duration.blur();
        }

        await expect(page.getByTestId('playlist-summary-items')).toHaveText(
            '3',
        );
        await expect(
            page.getByTestId('playlist-summary-duration'),
        ).toContainText('30s');

        await page.getByTestId('playlist-save').click();
        await expect(page.getByTestId('playlist-save-state')).toContainText(
            /saved/i,
            { timeout: 15_000 },
        );

        await page.reload();
        await expect(
            page.locator('[data-test="playlist-items"] > li'),
        ).toHaveCount(3);
        await expect(
            page.getByTestId('playlist-summary-duration'),
        ).toContainText('30s');
    });

    test('reordering items persists after save', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto(editorUrl);

        const before = await itemOrder(page);
        expect(before).toHaveLength(3);

        // Drag handles stay keyboard reachable and labelled.
        await expect(
            page.getByTestId('playlist-item-handle-0'),
        ).toHaveAttribute('aria-label', /reorder/i);

        await page.getByTestId('playlist-item-down-0').click();

        const reordered = await itemOrder(page);
        expect(reordered).toEqual([before[1], before[0], before[2]]);

        await page.getByTestId('playlist-save').click();
        await expect(page.getByTestId('playlist-save-state')).toContainText(
            /saved/i,
            { timeout: 15_000 },
        );

        await page.reload();
        expect(await itemOrder(page)).toEqual(reordered);
    });

    test('the native drag handle reorders items', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto(editorUrl);

        const before = await itemOrder(page);
        expect(before).toHaveLength(3);

        await page
            .getByTestId('playlist-item-handle-2')
            .dragTo(page.getByTestId('playlist-item-0'));

        expect(await itemOrder(page)).toEqual([
            before[2],
            before[0],
            before[1],
        ]);
    });

    test('inactive items are skipped in preview', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto(editorUrl);

        const order = await itemOrder(page);

        await page.getByTestId('playlist-item-active-0').click();
        await expect(
            page.locator('[data-test="playlist-item-0"]'),
        ).toHaveAttribute('data-active', 'false');
        await expect(page.getByTestId('playlist-summary-active')).toHaveText(
            '2',
        );
        await expect(
            page.getByTestId('playlist-summary-duration'),
        ).toContainText('20s');

        await page.getByTestId('playlist-save').click();
        await expect(page.getByTestId('playlist-save-state')).toContainText(
            /saved/i,
            { timeout: 15_000 },
        );

        await page.getByTestId('playlist-preview').click();
        await expect(page.getByTestId('playlist-preview-dialog')).toBeVisible();
        await expect(page.getByTestId('playlist-player-stage')).toBeVisible();

        // Pause so the assertions are not racing the ten second advance timer.
        await page.getByTestId('playlist-player-toggle').click();
        await expect(page.getByTestId('playlist-player-position')).toHaveText(
            '1/2',
        );
        await expect(page.getByTestId('playlist-player-name')).toHaveText(
            order[1],
        );
    });

    test('publish the playlist and see it in the library', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto(editorUrl);

        await page.getByTestId('playlist-publish').click();
        await expect(page.getByTestId('playlist-editor-status')).toHaveText(
            /published/i,
            { timeout: 15_000 },
        );

        await page.goto('/app/playlists');
        await page.getByTestId('playlists-search').fill(playlistName);

        const card = page
            .locator('[data-test^="playlist-card-"]')
            .filter({ hasText: playlistName });
        await expect(card).toHaveCount(1, { timeout: 15_000 });
        await expect(card).toHaveAttribute('data-status', 'published');
        await expect(card.getByText('3 Screens')).toBeVisible();
        // Active items only — the skipped design is not part of the loop.
        await expect(card.getByText(/20s runtime/)).toBeVisible();
        await expect(
            card.locator('[data-test^="playlist-sequence-"]'),
        ).toBeVisible();
        await expect(
            card.locator('[data-test="playlist-seq-thumb"]').first(),
        ).toBeVisible();
    });

    test('library preview plays the published playlist', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');
        await page.getByTestId('playlists-search').fill(playlistName);

        const card = page
            .locator('[data-test^="playlist-card-"]')
            .filter({ hasText: playlistName });
        await expect(card).toHaveCount(1, { timeout: 15_000 });

        await card.locator('[data-test^="playlist-preview-"]').click();

        await expect(page.getByTestId('playlist-preview-dialog')).toBeVisible();
        await expect(page.getByTestId('playlist-player-toggle')).toBeEnabled({
            timeout: 15_000,
        });
        await expect(page.getByTestId('playlist-player-stage')).toBeVisible();
        await page.getByTestId('playlist-player-toggle').click();
        await expect(page.getByTestId('playlist-player-position')).toHaveText(
            '1/2',
        );

        await page.getByTestId('playlist-player-next').click();
        await expect(page.getByTestId('playlist-player-position')).toHaveText(
            '2/2',
        );
    });

    test('player plays a deployed playlist', async ({ browser, page }) => {
        test.setTimeout(90_000);

        const playerContext = await browser.newContext();
        const player = await playerContext.newPage();
        await player.goto('/player');
        await expect(player.getByTestId('player-pairing')).toBeVisible({
            timeout: 15_000,
        });
        const code = (
            await player.getByTestId('player-pairing-code').innerText()
        ).trim();

        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');
        await page.getByTestId('screens-add').click();
        await page.getByTestId('screens-pair-code').fill(code);
        await page.getByTestId('screens-pair-name').fill(`E2E TV ${stamp}`);
        await page.getByTestId('screens-pair-confirm').click();

        await page.waitForURL(/\/app\/screens\/\d+/, { timeout: 20_000 });
        const screenId = Number(/\/app\/screens\/(\d+)/.exec(page.url())?.[1]);
        expect(Number.isFinite(screenId)).toBe(true);

        await expect(player.getByTestId('player-no-content')).toBeVisible({
            timeout: 30_000,
        });

        publishPlaylistToScreen(playlistName, screenId);

        const ready = player.getByTestId('player-ready');
        await expect(ready).toBeVisible({ timeout: 30_000 });
        await expect(ready).toHaveAttribute('data-content-type', 'playlist');
        await expect(
            player.locator('[data-test="playlist-player-stage"]'),
        ).toHaveAttribute('data-current-index', '1');

        await playerContext.close();
    });

    test('viewer cannot mutate playlists', async ({ page }) => {
        await login(page, 'viewer@dz.local');
        await page.goto('/app/playlists');

        await expect(
            page.getByRole('heading', { name: 'Playlists', exact: true }),
        ).toBeVisible();
        await expect(page.getByTestId('playlists-create')).toHaveCount(0);

        await page.goto(editorUrl);
        await expect(page.getByTestId('playlist-editor')).toBeVisible();
        await expect(page.getByTestId('playlist-save')).toBeDisabled();
        await expect(page.getByTestId('playlist-add-design')).toHaveCount(0);
    });

    test('playlists are isolated to their workspace', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/playlists');

        await expect(
            page.getByRole('heading', { name: 'Playlists', exact: true }),
        ).toBeVisible();
        await expect(page.getByText(playlistName)).toHaveCount(0);
        await expect(page.getByText('No playlists yet')).toBeVisible();
    });

    test('playlist library works on a mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/playlists');

        await expect(
            page.getByRole('heading', { name: 'Playlists', exact: true }),
        ).toBeVisible();
        await expect(page.getByTestId('playlists-create')).toBeVisible();
    });
});
