import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * Priority live-media correction — Editor + Preview + Player matrix.
 * Uses real providers/streams; fails loudly if controls or classification break.
 */

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

async function createLandscapeDesign(page: Page, name: string): Promise<void> {
    await page.goto('/app/screen-designs');
    await page.getByTestId('screen-designs-create').click();
    await page.getByTestId('screen-designs-create-landscape').click();
    await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
        timeout: 45_000,
    });
    await page.getByTestId('screen-design-editor-name').fill(name);
}

async function addEmbedAndSelect(page: Page): Promise<void> {
    await page.getByTestId('screen-design-tab-widgets').click();
    await page.getByTestId('screen-design-add-widget-embed').click();
    await page.locator('[data-element-type="widget"]').last().click();
    await expect(page.getByTestId('embed-url-input')).toBeVisible({
        timeout: 10_000,
    });
}

async function setEmbedUrl(page: Page, url: string): Promise<void> {
    const input = page.getByTestId('embed-url-input');
    await input.fill(url);
    await input.blur();
    // Give resolver + player mount a beat.
    await page.waitForTimeout(800);
}

function seedPlayerForDesign(
    designName: string,
    screenName: string,
): {
    token: string;
} {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$design = \\App\\Models\\ScreenDesign::query()
    ->where('workspace_id', $workspace->id)
    ->where('name', ${JSON.stringify(designName)})
    ->latest('id')
    ->firstOrFail();
app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class)->handle($user, $design);
$screen = \\App\\Models\\Screen::factory()->create([
    'workspace_id' => $workspace->id,
    'name' => ${JSON.stringify(screenName)},
    'orientation' => 'landscape',
    'operational_status' => \\App\\Enums\\ScreenOperationalStatus::Active,
]);
$token = \\Illuminate\\Support\\Str::random(64);
\\App\\Models\\ScreenDevice::factory()->forScreen($screen)->withToken($token)->create();
app(\\App\\Actions\\Deployments\\PublishContentToScreens::class)
    ->publishDesign($user, $workspace, $design->fresh(), [$screen->id]);
echo json_encode(['token' => $token]);
`;

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', script],
        { cwd: process.cwd(), encoding: 'utf8' },
    );
    const match = output.match(/\{.*\}/s);
    if (!match) {
        throw new Error(`Could not seed live-media player: ${output}`);
    }

    return JSON.parse(match[0]) as { token: string };
}

test.describe.configure({ mode: 'serial' });

test.describe('live media player', () => {
    test.setTimeout(90_000);
    test('YouTube: detect, play/pause, mute/unmute in Editor', async ({
        page,
    }) => {
        const name = `E2E Live YT ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);

        await setEmbedUrl(page, 'https://www.youtube.com/watch?v=M7lc1UVf-VE');

        await expect(page.getByTestId('embed-resolved-kind')).toContainText(
            /YouTube/i,
        );
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /Supported/i,
        );

        const controls = page.getByTestId('live-media-controls');
        await expect(controls).toBeVisible({ timeout: 20_000 });

        const playPause = page.getByTestId('live-media-play-pause');
        await expect(playPause).toBeVisible();

        // Toggle pause/play via aria-label (icon buttons).
        const initial = await playPause.getAttribute('aria-label');
        await playPause.click();
        await expect(playPause).toHaveAttribute(
            'aria-label',
            initial === 'Pause' ? 'Play' : 'Pause',
        );
        await playPause.click();
        await expect(playPause).toHaveAttribute(
            'aria-label',
            initial === 'Pause' ? 'Pause' : 'Play',
        );

        const mute = page.getByTestId('live-media-mute');
        const muteLabel = await mute.getAttribute('aria-label');
        await mute.click();
        await expect(mute).toHaveAttribute(
            'aria-label',
            muteLabel === 'Unmute' ? 'Mute' : 'Unmute',
        );
        await expect(page.getByTestId('live-media-volume')).toBeVisible();

        await page.getByTestId('screen-design-preview').click();
        const dialog = page.getByTestId('screen-design-preview-dialog');
        await expect(dialog).toBeVisible();
        await expect(
            dialog.locator('[data-widget-type="embed"]').first(),
        ).toBeVisible();
        await expect(
            dialog.getByTestId('live-media-controls').first(),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('HLS .m3u8: detect, controls, Preview', async ({ page }) => {
        const name = `E2E Live HLS ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);

        await setEmbedUrl(
            page,
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        );

        await expect(page.getByTestId('embed-resolved-kind')).toContainText(
            /HLS/i,
        );
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /Supported/i,
        );

        await expect(page.getByTestId('live-media-controls')).toBeVisible({
            timeout: 25_000,
        });
        await expect(page.locator('video').first()).toBeVisible({
            timeout: 25_000,
        });

        const playPause = page.getByTestId('live-media-play-pause');
        const before = await playPause.getAttribute('aria-label');
        await playPause.click();
        await expect(playPause).toHaveAttribute(
            'aria-label',
            before === 'Pause' ? 'Play' : 'Pause',
        );

        await page.getByTestId('screen-design-preview').click();
        const dialog = page.getByTestId('screen-design-preview-dialog');
        await expect(dialog.locator('video').first()).toBeVisible({
            timeout: 20_000,
        });
    });

    test('MP4 direct video: detect and play surface', async ({ page }) => {
        const name = `E2E Live MP4 ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);

        await setEmbedUrl(
            page,
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        );

        await expect(page.getByTestId('embed-resolved-kind')).toContainText(
            /Direct Video|Video/i,
        );
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /Supported/i,
        );
        await expect(page.locator('video').first()).toBeVisible({
            timeout: 20_000,
        });
        await expect(page.getByTestId('live-media-controls')).toBeVisible();
    });

    test('Vimeo: detect official player', async ({ page }) => {
        const name = `E2E Live Vimeo ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);

        await setEmbedUrl(page, 'https://vimeo.com/76979871');

        await expect(page.getByTestId('embed-resolved-kind')).toContainText(
            /Vimeo/i,
        );
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /Supported/i,
        );
        await expect(page.getByTestId('live-media-controls')).toBeVisible({
            timeout: 20_000,
        });
    });

    test('Restricted DRM + BBC: clear messages, no blank iframe', async ({
        page,
    }) => {
        const name = `E2E Live Blocked ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);

        await setEmbedUrl(page, 'https://www.primevideo.com/detail/foo');
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /restricted|unavailable/i,
        );
        await expect(page.locator('body')).toContainText(
            /third-party signage|does not support playback/i,
        );
        await expect(
            page.locator('[data-widget-type="embed"] iframe'),
        ).toHaveCount(0);

        await setEmbedUrl(page, 'https://www.bbc.co.uk/news');
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /restricted|unavailable/i,
        );
        await expect(page.locator('body')).toContainText(
            /BBC does not permit|News Widget|does not allow external embedding/i,
        );
        await expect(
            page.locator('[data-widget-type="embed"] iframe'),
        ).toHaveCount(0);
    });

    test('Player surface renders shared live media for HLS', async ({
        page,
    }) => {
        const name = `E2E Live Player HLS ${Date.now()}`;
        const screenName = `E2E Live Screen ${Date.now()}`;
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, name);
        await addEmbedAndSelect(page);
        await setEmbedUrl(
            page,
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        );
        await expect(page.getByTestId('embed-source-status')).toContainText(
            /Supported/i,
        );

        // Persist draft before publish seed.
        await page.getByTestId('screen-design-save').click();
        await expect(page.getByText(/saved/i).first()).toBeVisible({
            timeout: 10_000,
        });

        const { token } = seedPlayerForDesign(name, screenName);

        await page.goto('/player');
        await page.evaluate((deviceToken) => {
            window.localStorage.setItem('dz_player_device_token', deviceToken);
            document.cookie = `dz_player_device_token=${encodeURIComponent(deviceToken)}; path=/; SameSite=Lax`;
        }, token);
        await page.reload();

        await expect(
            page.locator('[data-widget-type="embed"]').first(),
        ).toBeVisible({
            timeout: 30_000,
        });
        await expect(page.locator('video').first()).toBeVisible({
            timeout: 30_000,
        });
    });
});

test.describe('landing marketing — Masjid removed', () => {
    test('public landing has no Masjid/Mosque copy; industries stay balanced', async ({
        page,
    }) => {
        await page.goto('/');
        const body = await page.locator('body').innerText();
        expect(body).not.toMatch(/Masjid|Mosque|Jumu'?ah|Prayer timetable/i);

        for (const industry of [
            'Restaurants',
            'Retail',
            'Corporate',
            'Education',
            'Healthcare',
            'Hotels',
            'Gyms',
            'Real Estate',
            'Events',
        ]) {
            await expect(
                page
                    .getByRole('heading', { name: industry })
                    .or(page.getByText(industry, { exact: true }).first()),
            ).toBeVisible();
        }
    });
});
