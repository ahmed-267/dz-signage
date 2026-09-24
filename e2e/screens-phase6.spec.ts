import { execFileSync } from 'node:child_process';
import { expect, test, type Browser, type Page } from '@playwright/test';

const DEVICE_TOKEN_KEY = 'dz_player_device_token';

let player: Page | null = null;
let screenId: number | null = null;
let screenName = '';

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

async function closePlayer(): Promise<void> {
    if (player !== null) {
        await player.context().close();
        player = null;
    }
}

function tinker(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: process.cwd(),
        stdio: 'pipe',
    });
}

/**
 * Heartbeats normally run on a timer inside the Player. Trigger one on demand so
 * presence assertions do not depend on the interval.
 */
async function sendHeartbeat(
    page: Page,
    payload: Record<string, unknown> = {},
): Promise<{ status: number; body: Record<string, unknown> | null }> {
    return page.evaluate(
        async ([tokenKey, body]) => {
            const token = window.localStorage.getItem(tokenKey as string);
            const response = await fetch('/player/api/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Device-Token': token ?? '',
                },
                body: JSON.stringify(body),
                credentials: 'same-origin',
            });

            let parsed: Record<string, unknown> | null = null;
            try {
                parsed = (await response.json()) as Record<string, unknown>;
            } catch {
                parsed = null;
            }

            return { status: response.status, body: parsed };
        },
        [
            DEVICE_TOKEN_KEY,
            {
                player_version: '1.0.0',
                viewport_width: 1920,
                viewport_height: 1080,
                orientation: 'landscape',
                playback_state: 'ready',
                ...payload,
            },
        ] as const,
    );
}

function ageLastSeen(id: number, minutes = 10): void {
    tinker(
        `\\App\\Models\\ScreenDevice::where('screen_id', ${id})->update(['last_seen_at' => now()->subMinutes(${minutes})]);`,
    );
}

test.describe('phase 6 screen presence and health', () => {
    test.describe.configure({ mode: 'serial' });

    test.afterAll(async () => {
        await closePlayer();
    });

    test('paired screen reporting a heartbeat is active, connected and online', async ({
        browser,
        page,
    }) => {
        test.setTimeout(90_000);

        player = await openPlayer(browser);
        const code = (
            await player.getByTestId('player-pairing-code').innerText()
        ).trim();

        screenName = `E2E Phase6 TV ${Date.now()}`;

        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');
        await page.getByTestId('screens-add').click();
        await page.getByTestId('screens-pair-code').fill(code);
        await page.getByTestId('screens-pair-name').fill(screenName);
        await page.getByTestId('screens-pair-confirm').click();

        await page.waitForURL(/\/app\/screens\/\d+/, { timeout: 20_000 });
        await expect(page.getByTestId('screen-detail')).toBeVisible();

        const matched = /\/app\/screens\/(\d+)/.exec(page.url());
        expect(matched).not.toBeNull();
        screenId = Number(matched?.[1]);

        await expect(player.getByTestId('player-no-content')).toBeVisible({
            timeout: 20_000,
        });

        const heartbeat = await sendHeartbeat(player, {
            playback_state: 'no_content',
        });
        expect(heartbeat.status).toBe(200);
        expect(heartbeat.body?.screen_active).toBe(true);

        await page.reload();
        await expect(page.getByTestId('screen-state-operational')).toHaveText(
            'Active',
        );
        await expect(page.getByTestId('screen-state-pairing')).toHaveText(
            'Connected',
        );
        await expect(page.getByTestId('screen-state-network')).toHaveText(
            'Online',
        );
        await expect(page.getByTestId('screen-last-seen')).toContainText(
            /just now|minute/i,
        );

        // Same three axes on the library, filtered by search.
        await page.goto('/app/screens');
        await page.getByTestId('screens-search').fill(screenName);
        await expect(
            page.getByTestId(`screen-status-${screenId}`).first(),
        ).toHaveText('Active', { timeout: 15_000 });
        await expect(
            page.getByTestId(`screen-pairing-${screenId}`).first(),
        ).toHaveText('Connected');
        await expect(
            page.getByTestId(`screen-network-${screenId}`).first(),
        ).toHaveText('Online');
    });

    test('deactivating the screen shows the inactive player message and still accepts heartbeats', async ({
        page,
    }) => {
        test.setTimeout(90_000);
        expect(screenId).not.toBeNull();
        expect(player).not.toBeNull();

        await login(page, 'owner@dz.local');
        await page.goto(`/app/screens/${screenId}`);
        await page.getByTestId('screen-deactivate').click();
        await expect(page.getByTestId('screen-state-operational')).toHaveText(
            'Inactive',
            { timeout: 15_000 },
        );

        await expect(player!.getByTestId('player-inactive')).toBeVisible({
            timeout: 30_000,
        });
        await expect(player!.getByTestId('player-inactive')).toContainText(
            'This screen is currently inactive.',
        );

        const heartbeat = await sendHeartbeat(player!, {
            playback_state: 'inactive',
        });
        expect(heartbeat.status).toBe(200);
        expect(heartbeat.body?.screen_active).toBe(false);

        await page.reload();
        await expect(page.getByTestId('screen-state-pairing')).toHaveText(
            'Connected',
        );
        await expect(page.getByTestId('screen-state-network')).toHaveText(
            'Online',
        );

        await page.getByTestId('screen-activate').click();
        await expect(page.getByTestId('screen-state-operational')).toHaveText(
            'Active',
            { timeout: 15_000 },
        );
    });

    test('a screen that stops reporting is offline but still connected', async ({
        page,
    }) => {
        expect(screenId).not.toBeNull();

        // No more heartbeats from this point — presence must decay server-side.
        await closePlayer();
        ageLastSeen(screenId!, 10);

        await login(page, 'owner@dz.local');
        await page.goto(`/app/screens/${screenId}`);

        await expect(page.getByTestId('screen-state-pairing')).toHaveText(
            'Connected',
        );
        await expect(page.getByTestId('screen-state-network')).toHaveText(
            'Offline',
        );
        await expect(page.getByTestId('screen-state-health')).toHaveText(
            'Offline',
        );
    });

    test('unpairing the screen reports disconnected', async ({ page }) => {
        expect(screenId).not.toBeNull();

        await login(page, 'owner@dz.local');
        await page.goto(`/app/screens/${screenId}`);
        await page.getByTestId('screen-unpair').click();
        await page.getByTestId('screen-unpair-confirm').click();
        await page.waitForURL(/\/app\/screens(\?.*)?$/, { timeout: 15_000 });

        await page.getByTestId('screens-search').fill(screenName);
        await expect(
            page.getByTestId(`screen-pairing-${screenId}`).first(),
        ).toHaveText('Disconnected', { timeout: 15_000 });
        await expect(
            page.getByTestId(`screen-network-${screenId}`).first(),
        ).toHaveText('Offline');
    });

    test('platform admin sees the screen in the directory and in screen health', async ({
        page,
    }) => {
        test.setTimeout(90_000);
        expect(screenId).not.toBeNull();

        await login(page, 'admin@dz.local');

        await page.goto('/admin/screens');
        await expect(
            page.getByRole('heading', { name: 'Customers', exact: true }),
        ).toBeVisible();
        await expect(page.getByTestId('admin-tab-tvs')).toBeVisible();
        await page.getByTestId('admin-screens-search').fill(screenName);
        await expect(
            page.getByTestId(`admin-screen-row-${screenId}`),
        ).toBeVisible({ timeout: 15_000 });

        await page.goto(`/admin/screens/${screenId}`);
        await expect(page.getByTestId('admin-screen-detail')).toBeVisible();
        await expect(page.getByTestId('admin-screen-heartbeats')).toBeVisible();

        await page.goto('/admin/screen-health');
        await expect(page.getByTestId('admin-screen-health')).toBeVisible();
        await expect(
            page.getByTestId('admin-health-count-total'),
        ).not.toBeEmpty();
        await page.getByTestId('admin-health-filter-offline').click();
        await expect(page.getByTestId('admin-screen-health')).toBeVisible();
    });

    test('screens library filters work on a mobile viewport', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/screens');

        await expect(
            page.getByRole('heading', { name: 'Paired TVs', exact: true }),
        ).toBeVisible();
        await page.getByTestId('screens-filter-disconnected').click();
        await expect(page.getByTestId('screens-search')).toBeVisible();

        if (screenId !== null) {
            await expect(
                page.getByTestId(`screen-card-${screenId}`),
            ).toBeVisible({ timeout: 15_000 });
        }
    });
});
