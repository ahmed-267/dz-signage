import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

async function login(page: Page, email = 'owner@dz.local'): Promise<void> {
    await page.goto('/login');
    await page.getByLabel(/email/i).fill(email);
    await page.locator('#password').fill('password');
    await page.getByTestId('login-button').click();
    await page.waitForURL(/\/(app|admin)\//, { timeout: 20_000 });
}

type Seed = {
    screen_id: number;
    design_name: string;
    playlist_name: string;
    pair_code?: string;
};

function seedOffline(marker: string): Seed & { token: string } {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$createDesign = app(\\App\\Actions\\ScreenDesigns\\CreateBlankScreenDesign::class);
$publishDesign = app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class);
$createPlaylist = app(\\App\\Actions\\Playlists\\CreatePlaylist::class);
$savePlaylist = app(\\App\\Actions\\Playlists\\SavePlaylistDraft::class);
$publishPlaylist = app(\\App\\Actions\\Playlists\\PublishPlaylist::class);
$publish = app(\\App\\Actions\\Deployments\\PublishContentToScreens::class);

$design = $createDesign->handle($user, $workspace, 'E2E Off Design ${marker}', \\App\\Enums\\TemplateOrientation::Landscape);
$publishDesign->handle($user, $design);

$playlist = $createPlaylist->handle($user, $workspace, 'E2E Off Playlist ${marker}');
$savePlaylist->handle($user, $playlist, [
    'items' => [
        ['screen_design_id' => $design->id, 'duration_seconds' => 4],
    ],
]);
$publishPlaylist->handle($user, $playlist);

$screen = \\App\\Models\\Screen::factory()->create([
    'workspace_id' => $workspace->id,
    'name' => 'E2E Off Screen ${marker}',
    'orientation' => 'landscape',
    'operational_status' => \\App\\Enums\\ScreenOperationalStatus::Active,
]);

$token = \\Illuminate\\Support\\Str::random(64);
\\App\\Models\\ScreenDevice::factory()->forScreen($screen)->withToken($token)->create();

$publish->publishDesign($user, $workspace, $design->fresh(), [$screen->id]);

echo json_encode([
    'screen_id' => $screen->id,
    'design_name' => $design->name,
    'playlist_name' => $playlist->name,
    'token' => $token,
]);
`;

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', script],
        { cwd: process.cwd(), encoding: 'utf8' },
    );
    const match = output.match(/\{.*\}/s);
    if (!match) {
        throw new Error(`Could not seed offline fixtures: ${output}`);
    }

    return JSON.parse(match[0]) as Seed & { token: string };
}

async function openPairedPlayer(page: Page, token: string): Promise<void> {
    await page.goto('/player');
    await page.evaluate((deviceToken) => {
        window.localStorage.setItem('dz_player_device_token', deviceToken);
        document.cookie = `dz_player_device_token=${encodeURIComponent(deviceToken)}; path=/; SameSite=Lax`;
    }, token);
    await page.reload();
    await expect(page.getByTestId('player-ready')).toBeVisible({
        timeout: 30_000,
    });

    // Wait until the service worker is controlling this client (needed for offline boot).
    await page.evaluate(async () => {
        if (!('serviceWorker' in navigator)) {
            return;
        }
        await navigator.serviceWorker.register('/player-sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;
        if (!navigator.serviceWorker.controller) {
            await new Promise<void>((resolve) => {
                navigator.serviceWorker.addEventListener(
                    'controllerchange',
                    () => resolve(),
                    { once: true },
                );
                window.setTimeout(() => resolve(), 3000);
            });
        }
    });
    await page.reload();
    await expect(page.getByTestId('player-ready')).toBeVisible({
        timeout: 30_000,
    });

    await page.waitForFunction(
        async () => {
            return await new Promise<boolean>((resolve) => {
                const req = indexedDB.open('dz-player-offline');
                req.onerror = () => resolve(false);
                req.onsuccess = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains('packages')) {
                        resolve(false);
                        return;
                    }
                    const tx = db.transaction('packages', 'readonly');
                    const get = tx.objectStore('packages').get('active');
                    get.onsuccess = () => resolve(Boolean(get.result));
                    get.onerror = () => resolve(false);
                };
            });
        },
        undefined,
        { timeout: 30_000 },
    );
}

test.describe('player offline phase 10', () => {
    test.describe.configure({ mode: 'serial' });

    const marker = `P10${Date.now()}`;
    let seed: Seed & { token: string };

    test.beforeAll(() => {
        seed = seedOffline(marker);
    });

    test('current content survives going offline', async ({
        page,
        context,
    }) => {
        await openPairedPlayer(page, seed.token);
        await expect(page.getByTestId('player-ready')).toBeVisible();

        // Allow offline package sync to complete.
        await page.waitForTimeout(2000);

        await context.setOffline(true);
        await expect(page.getByTestId('player-ready')).toBeVisible();
        await context.setOffline(false);
    });

    test('offline reload keeps cached content', async ({ page, context }) => {
        test.setTimeout(60_000);
        await openPairedPlayer(page, seed.token);

        // Ensure package is active in IndexedDB before cutting the network.
        await page.waitForFunction(async () => {
            return await new Promise<boolean>((resolve) => {
                const req = indexedDB.open('dz-player-offline');
                req.onerror = () => resolve(false);
                req.onsuccess = () => {
                    const db = req.result;
                    if (!db.objectStoreNames.contains('packages')) {
                        resolve(false);
                        return;
                    }
                    const tx = db.transaction('packages', 'readonly');
                    const get = tx.objectStore('packages').get('active');
                    get.onsuccess = () => resolve(Boolean(get.result));
                    get.onerror = () => resolve(false);
                };
            });
        });

        await expect(page.getByTestId('player-ready')).toBeVisible();
        // Confirm the SW is controlling this client before an offline boot.
        await page.waitForFunction(
            () =>
                'serviceWorker' in navigator &&
                Boolean(navigator.serviceWorker.controller),
            undefined,
            { timeout: 15_000 },
        );

        await context.setOffline(true);
        await page.goto('/player', { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('player-ready')).toBeVisible({
            timeout: 45_000,
        });
        await context.setOffline(false);
    });

    test('reconnect syncs newer content after publish', async ({
        page,
        context,
    }) => {
        await login(page);
        await openPairedPlayer(page, seed.token);
        await page.waitForTimeout(2000);

        await context.setOffline(true);
        await expect(page.getByTestId('player-ready')).toBeVisible();

        // Publish playlist while player is offline (from a separate request via tinker).
        execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute',
                `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$playlist = \\App\\Models\\Playlist::where('name', 'E2E Off Playlist ${marker}')->firstOrFail();
$screen = \\App\\Models\\Screen::where('name', 'E2E Off Screen ${marker}')->firstOrFail();
app(\\App\\Actions\\Deployments\\PublishContentToScreens::class)
    ->publishPlaylist($user, $workspace, $playlist, [$screen->id]);
`,
            ],
            { cwd: process.cwd(), encoding: 'utf8' },
        );

        await context.setOffline(false);
        await expect(page.getByTestId('player-ready')).toHaveAttribute(
            'data-content-type',
            'playlist',
            { timeout: 45_000 },
        );
    });
});
