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
    design_id: number;
    design_name: string;
    playlist_id: number;
    screen_ids: number[];
    screen_names: string[];
};

function seedPublishing(marker: string): Seed {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$createDesign = app(\\App\\Actions\\ScreenDesigns\\CreateBlankScreenDesign::class);
$publishDesign = app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class);
$createPlaylist = app(\\App\\Actions\\Playlists\\CreatePlaylist::class);
$savePlaylist = app(\\App\\Actions\\Playlists\\SavePlaylistDraft::class);
$publishPlaylist = app(\\App\\Actions\\Playlists\\PublishPlaylist::class);

$design = $createDesign->handle($user, $workspace, 'E2E Pub Design ${marker}', \\App\\Enums\\TemplateOrientation::Landscape);
$publishDesign->handle($user, $design);

$playlist = $createPlaylist->handle($user, $workspace, 'E2E Pub Playlist ${marker}');
$savePlaylist->handle($user, $playlist, [
    'items' => [['screen_design_id' => $design->id, 'duration_seconds' => 6]],
]);
$publishPlaylist->handle($user, $playlist);

$screens = [];
foreach ([1, 2] as $i) {
    $screens[] = \\App\\Models\\Screen::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => 'E2E Pub Screen ${marker} ' . $i,
        'orientation' => 'landscape',
        'operational_status' => \\App\\Enums\\ScreenOperationalStatus::Active,
    ]);
}

echo json_encode([
    'design_id' => $design->id,
    'design_name' => $design->name,
    'playlist_id' => $playlist->id,
    'screen_ids' => array_map(fn ($s) => $s->id, $screens),
    'screen_names' => array_map(fn ($s) => $s->name, $screens),
]);
`;

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', script],
        {
            cwd: process.cwd(),
            encoding: 'utf8',
        },
    );
    const match = output.match(/\{.*\}/s);
    if (!match) {
        throw new Error(`Could not seed publishing fixtures: ${output}`);
    }

    return JSON.parse(match[0]) as Seed;
}

test.describe('publishing phase 9', () => {
    test.describe.configure({ mode: 'serial' });

    const marker = `P9${Date.now()}`;
    let seed: Seed;

    test.beforeAll(() => {
        seed = seedPublishing(marker);
    });

    test('publish design to a screen and show it live on publishing page', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/app/publishing');
        await expect(page.getByTestId('publishing-page')).toBeVisible({
            timeout: 15_000,
        });

        await page.getByTestId('publishing-open').click();
        await page.getByTestId('publish-type-screen_design').click();
        await page
            .getByTestId('publish-content')
            .selectOption(String(seed.design_id));
        await page.getByTestId(`publish-screen-${seed.screen_ids[0]}`).click();
        await page.getByTestId('publish-confirm').click();

        await expect(page.getByTestId('publishing-flash')).toBeVisible({
            timeout: 15_000,
        });
        await expect(
            page.getByTestId(`publishing-live-${seed.screen_ids[0]}`),
        ).toContainText(seed.design_name);
    });

    test('publishing another design supersedes the previous deployment', async ({
        page,
    }) => {
        await login(page);

        // Seed a second published design.
        const second = execFileSync(
            'php',
            [
                'artisan',
                'tinker',
                '--execute',
                `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$design = app(\\App\\Actions\\ScreenDesigns\\CreateBlankScreenDesign::class)
    ->handle($user, $workspace, 'E2E Pub Design ${marker} B', \\App\\Enums\\TemplateOrientation::Landscape);
app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class)->handle($user, $design);
echo json_encode(['id' => $design->id, 'name' => $design->name]);
`,
            ],
            { cwd: process.cwd(), encoding: 'utf8' },
        );
        const designB = JSON.parse(second.match(/\{.*\}/s)![0]) as {
            id: number;
            name: string;
        };

        await page.goto('/app/publishing');
        await page.getByTestId('publishing-open').click();
        await page
            .getByTestId('publish-content')
            .selectOption(String(designB.id));
        await page.getByTestId(`publish-screen-${seed.screen_ids[0]}`).click();
        await page.getByTestId('publish-confirm').click();

        await expect(
            page.getByTestId(`publishing-live-${seed.screen_ids[0]}`),
        ).toContainText(designB.name, { timeout: 15_000 });

        await page.getByTestId('publishing-tab-history').click();
        await expect(
            page
                .locator('[data-test^="publishing-history-"]')
                .filter({ hasText: 'Superseded' })
                .first(),
        ).toBeVisible({
            timeout: 10_000,
        });
    });

    test('publish playlist to two screens', async ({ page }) => {
        await login(page);
        await page.goto('/app/publishing');
        await page.getByTestId('publishing-open').click();
        await page.getByTestId('publish-type-playlist').click();
        await page
            .getByTestId('publish-content')
            .selectOption(String(seed.playlist_id));
        await page.getByTestId(`publish-screen-${seed.screen_ids[0]}`).click();
        await page.getByTestId(`publish-screen-${seed.screen_ids[1]}`).click();
        await page.getByTestId('publish-confirm').click();

        await expect(
            page.getByTestId(`publishing-live-${seed.screen_ids[0]}`),
        ).toContainText('Playlist', { timeout: 15_000 });
        await expect(
            page.getByTestId(`publishing-live-${seed.screen_ids[1]}`),
        ).toContainText('Playlist');
    });

    test('republish from history creates a new active deployment', async ({
        page,
    }) => {
        await login(page);
        await page.goto('/app/publishing');
        await page.getByTestId('publishing-tab-history').click();

        const republish = page
            .locator('[data-test^="publishing-republish-"]')
            .first();
        await expect(republish).toBeVisible({ timeout: 10_000 });
        await republish.click();
        await expect(page.getByTestId('publishing-flash')).toContainText(
            /Republished|Published/i,
            { timeout: 15_000 },
        );
    });

    test('viewer cannot open publish dialog', async ({ page }) => {
        await login(page, 'viewer@dz.local');
        await page.goto('/app/publishing');
        await expect(page.getByTestId('publishing-page')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('publishing-open')).toHaveCount(0);
    });

    test('publishing page works on mobile viewport', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page);
        await page.goto('/app/publishing');
        await expect(page.getByTestId('publishing-page')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('publishing-tab-history')).toBeVisible();
    });
});
