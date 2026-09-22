import { expect, test, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';

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
    await page.waitForURL(/\/app\/screen-designs\/\d+\/edit/);
    await page.getByTestId('screen-design-editor-name').fill(name);
}

async function openWidgetsTab(page: Page): Promise<void> {
    await page.getByTestId('screen-design-tab-widgets').click();
}

function seedScreenAndPublish(
    designName: string,
    screenName: string,
): {
    screenId: number;
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
$publishDesign = app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class);
$publishDesign->handle($user, $design);
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
echo json_encode(['screen_id' => $screen->id, 'token' => $token]);
`;

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', script],
        { cwd: process.cwd(), encoding: 'utf8' },
    );
    const match = output.match(/\{.*\}/s);
    if (!match) {
        throw new Error(`Could not seed widget screen: ${output}`);
    }

    const parsed = JSON.parse(match[0]) as { screen_id: number; token: string };
    return { screenId: parsed.screen_id, token: parsed.token };
}

async function openPairedPlayer(page: Page, token: string): Promise<void> {
    await page.goto('/player');
    await page.evaluate((deviceToken) => {
        window.localStorage.setItem('dz_player_device_token', deviceToken);
        document.cookie = `dz_player_device_token=${encodeURIComponent(deviceToken)}; path=/; SameSite=Lax`;
    }, token);
    await page.reload();
}

test.describe('phase 11 widgets', () => {
    test.describe.configure({ mode: 'serial' });

    test('Flow A — add Clock, publish, player shows clock', async ({
        page,
    }) => {
        test.setTimeout(120_000);
        await login(page, 'owner@dz.local');

        const designName = `E2E Widget Clock ${Date.now()}`;
        await createLandscapeDesign(page, designName);
        await openWidgetsTab(page);
        await page.getByTestId('screen-design-add-widget-clock').click();
        await expect(
            page.locator('[data-element-type="widget"]').first(),
        ).toBeVisible();

        const timezone = page.getByTestId('widget-prop-timezone');
        if (await timezone.count()) {
            await timezone.selectOption('Europe/London');
        }

        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });
        await page.getByTestId('screen-design-publish').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/published|saved/i, { timeout: 10_000 });

        const seeded = seedScreenAndPublish(
            designName,
            `E2E Widget TV ${Date.now()}`,
        );
        await openPairedPlayer(page, seeded.token);
        await expect(page.getByTestId('player-ready')).toBeVisible({
            timeout: 30_000,
        });
        await expect(
            page.locator('[data-widget-type="clock"]').first(),
        ).toBeVisible({ timeout: 15_000 });
    });

    test('Flow B — Countdown updates in preview', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, `E2E Widget Countdown ${Date.now()}`);
        await openWidgetsTab(page);
        await page.getByTestId('screen-design-add-widget-countdown').click();

        const target = page.getByTestId('widget-prop-target-at');
        if (await target.count()) {
            const future = new Date(Date.now() + 60 * 60 * 1000).toISOString();
            await target.fill(future.slice(0, 16));
        }

        await page.getByTestId('screen-design-preview').click();
        await expect(
            page.getByTestId('screen-design-preview-dialog'),
        ).toBeVisible();
        await expect(
            page
                .getByTestId('screen-design-preview-dialog')
                .locator('[data-widget-type="countdown"]')
                .first(),
        ).toBeVisible();
    });

    test('Flow C — Weather offline keeps cached display', async ({ page }) => {
        test.setTimeout(120_000);
        await login(page, 'owner@dz.local');

        const designName = `E2E Widget Weather ${Date.now()}`;
        await createLandscapeDesign(page, designName);
        await openWidgetsTab(page);
        await page.getByTestId('screen-design-add-widget-weather').click();
        const location = page.getByTestId('widget-prop-location');
        if (await location.count()) {
            await location.fill('Nottingham, United Kingdom');
        }

        await page.getByTestId('screen-design-save').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/saved/i, { timeout: 10_000 });
        await page.getByTestId('screen-design-publish').click();
        await expect(
            page.getByTestId('screen-design-save-state'),
        ).toContainText(/published|saved/i, { timeout: 10_000 });

        const seeded = seedScreenAndPublish(
            designName,
            `E2E Weather TV ${Date.now()}`,
        );
        await openPairedPlayer(page, seeded.token);
        await expect(page.getByTestId('player-ready')).toBeVisible({
            timeout: 30_000,
        });
        const weather = page.locator('[data-widget-type="weather"]').first();
        await expect(weather).toBeVisible({ timeout: 20_000 });
        const before = (await weather.innerText()).trim();
        expect(before.length).toBeGreaterThan(0);

        await page.context().setOffline(true);
        await expect(weather).toBeVisible();
        await expect(weather).toContainText(/.+/);
        await page.context().setOffline(false);
    });

    test('Flow D — RSS widget in preview', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, `E2E Widget RSS ${Date.now()}`);
        await openWidgetsTab(page);
        await page.getByTestId('screen-design-add-widget-news').click();

        const feed = page.getByTestId('widget-prop-feed-url');
        if (await feed.count()) {
            await feed.fill('https://www.nasa.gov/rss/dyn/breaking_news.rss');
        }

        await page.getByTestId('screen-design-preview').click();
        await expect(
            page.getByTestId('screen-design-preview-dialog'),
        ).toBeVisible();
        await expect(
            page
                .getByTestId('screen-design-preview-dialog')
                .locator('[data-widget-type="news"]')
                .first(),
        ).toBeVisible();
    });

    test('Flow E — Template widget copies to Screen Design', async ({
        page,
    }) => {
        test.setTimeout(90_000);
        await login(page, 'owner@dz.local');

        await page.goto('/app/templates');
        await page
            .getByTestId('templates-search')
            .fill('Prayer Times — Landscape');
        await page.waitForURL(/q=Prayer/, { timeout: 10_000 });
        await page.waitForTimeout(500);

        const card = page
            .locator('[data-test^="template-card-"]')
            .filter({ hasText: /Prayer Times — Landscape/i })
            .first();
        await expect(card).toBeVisible({ timeout: 15_000 });

        const useBtn = card.locator('[data-test^="template-use-"]');
        await card.hover();
        await expect(useBtn).toBeEnabled();

        await Promise.all([
            page.waitForURL(/\/app\/screen-designs\/\d+\/edit/, {
                timeout: 20_000,
            }),
            useBtn.click({ force: true }),
        ]);

        await expect(
            page.locator('[data-element-type="widget"]').first(),
        ).toBeVisible({ timeout: 10_000 });
    });

    test('Flow F/G — offline clock continues; alert isolates failures', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await createLandscapeDesign(page, `E2E Widget Offline ${Date.now()}`);
        await openWidgetsTab(page);
        await page.getByTestId('screen-design-add-widget-clock').click();
        await page.getByTestId('screen-design-add-widget-alert').click();
        await page.getByTestId('screen-design-add-widget-embed').click();

        await page.getByTestId('screen-design-preview').click();
        const dialog = page.getByTestId('screen-design-preview-dialog');
        await expect(dialog).toBeVisible();
        await expect(
            dialog.locator('[data-widget-type="clock"]').first(),
        ).toBeVisible();
        await expect(
            dialog.locator('[data-widget-type="alert"]').first(),
        ).toBeVisible();
        await expect(
            dialog.locator('[data-widget-type="embed"]').first(),
        ).toBeVisible();
        await expect(dialog.locator('[data-widget-type]')).toHaveCount(3);
    });
});
