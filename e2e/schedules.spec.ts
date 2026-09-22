import {
    expect,
    test,
    type APIRequestContext,
    type Page,
} from '@playwright/test';
import { registerCustomer } from './helpers/auth';
import { publishPlaylistToScreen } from './helpers/playlists';
import {
    createActiveSchedule,
    seedScheduleFixtures,
    setScheduleWindow,
    workspaceWindows,
    type ScheduleFixtures,
} from './helpers/schedules';

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

type CreateOptions = {
    name: string;
    playlistId: number;
    screenIds: number[];
    startTime: string;
    endTime: string;
    /** Day shortcut button; omit to keep the default selection. */
    dayShortcut?: 'every-day' | 'weekdays' | 'weekends';
    priority?: number;
};

/**
 * Drives the real create form stepper and returns the edit URL it lands on.
 * `store` redirects to the editor, so every spec that needs a saved schedule
 * goes through the same UI path.
 */
async function createSchedule(
    page: Page,
    options: CreateOptions,
): Promise<string> {
    await page.goto('/app/schedules/create');
    await expect(page.getByTestId('schedule-form')).toBeVisible({
        timeout: 15_000,
    });
    await expect(page.getByTestId('schedule-stepper')).toBeVisible();

    // Basics
    await page.getByTestId('schedule-name').fill(options.name);
    if (options.priority !== undefined) {
        await page
            .getByTestId('schedule-priority')
            .selectOption(String(options.priority));
    }
    await page.getByTestId('schedule-step-next').click();

    // Content
    await page
        .getByTestId(`schedule-playlist-option-${options.playlistId}`)
        .click();
    await page.getByTestId('schedule-step-next').click();

    // Timing
    await page.getByTestId('schedule-start-time').fill(options.startTime);
    await page.getByTestId('schedule-end-time').fill(options.endTime);
    if (options.dayShortcut) {
        await page.getByTestId(`schedule-days-${options.dayShortcut}`).click();
    }
    await page.getByTestId('schedule-step-next').click();

    // TVs
    for (const screenId of options.screenIds) {
        await page.getByTestId(`schedule-screen-${screenId}`).click();
    }
    await page.getByTestId('schedule-step-next').click();

    // Review → save
    await expect(page.getByTestId('schedule-section-review')).toBeVisible();
    await page.getByTestId('schedule-save').click();
    await page.waitForURL(/\/app\/schedules\/\d+\/edit/, { timeout: 20_000 });

    return page.url();
}

function scheduleIdFrom(url: string): number {
    const id = Number(/\/app\/schedules\/(\d+)\/edit/.exec(url)?.[1]);
    expect(Number.isFinite(id)).toBe(true);

    return id;
}

async function manifest(
    request: APIRequestContext,
    token: string,
): Promise<Record<string, unknown>> {
    const response = await request.get('/player/api/manifest', {
        headers: { 'X-Device-Token': token, Accept: 'application/json' },
    });
    expect(response.ok()).toBe(true);

    return (await response.json()) as Record<string, unknown>;
}

test.describe('schedules phase 8', () => {
    test.describe.configure({ mode: 'serial' });

    const stamp = Date.now();
    const marker = `S${stamp}`;

    let fixtures: ScheduleFixtures;
    let windows: ReturnType<typeof workspaceWindows>;
    let primaryEditUrl = '';
    const primaryName = `E2E Schedule ${marker} Lunch`;
    const overlapName = `E2E Schedule ${marker} Overlap`;
    const overnightName = `E2E Schedule ${marker} Overnight`;
    const multiScreenName = `E2E Schedule ${marker} All Screens`;
    // Three live windows on one screen, separated only by priority.
    const lowPriorityName = `E2E Schedule ${marker} Low`;
    const highPriorityName = `E2E Schedule ${marker} High`;
    const pausedName = `E2E Schedule ${marker} Paused`;

    test.beforeAll(() => {
        fixtures = seedScheduleFixtures(marker);
        windows = workspaceWindows();
    });

    // A
    test('create a weekday 09:00–17:00 schedule and review its summary', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules');
        await expect(
            page.getByRole('heading', { name: 'Schedules', exact: true }),
        ).toBeVisible({ timeout: 15_000 });

        primaryEditUrl = await createSchedule(page, {
            name: primaryName,
            playlistId: fixtures.playlists.a.id,
            screenIds: [fixtures.screens[0].id],
            startTime: '09:00',
            endTime: '17:00',
            dayShortcut: 'weekdays',
        });

        await expect(
            page.getByTestId('schedule-summary-playlist'),
        ).toContainText(fixtures.playlists.a.name);
        await expect(page.getByTestId('schedule-summary-screens')).toHaveText(
            fixtures.screens[0].name,
        );
        await expect(page.getByTestId('schedule-summary-time')).toContainText(
            '09:00',
        );
        await expect(page.getByTestId('schedule-summary-time')).toContainText(
            '17:00',
        );
        await expect(page.getByTestId('schedule-summary-days')).toHaveText(
            'Mon–Fri',
        );
        await expect(page.getByTestId('schedule-form-status')).toHaveText(
            'Draft',
        );

        // The draft survives a reload — it is persisted, not local state.
        await page.reload();
        await expect(page.getByTestId('schedule-summary-days')).toHaveText(
            'Mon–Fri',
            { timeout: 15_000 },
        );
    });

    // B
    test('activating a draft schedule makes it Active', async ({ page }) => {
        await login(page, 'owner@dz.local');
        await page.goto(primaryEditUrl);

        await page.getByTestId('schedule-activate').click();

        await expect(page.getByTestId('schedule-edit-status')).toHaveText(
            'Active',
            { timeout: 15_000 },
        );

        const scheduleId = scheduleIdFrom(primaryEditUrl);
        await page.goto('/app/schedules');
        await page.getByTestId('schedules-search').fill(primaryName);
        await expect(
            page.getByTestId(`schedule-status-${scheduleId}`),
        ).toHaveText('Active', { timeout: 15_000 });
    });

    // C
    test('an overlapping schedule warns about priority', async ({ page }) => {
        await login(page, 'owner@dz.local');

        // Same screen, same weekdays, overlapping hours as the active schedule.
        const editUrl = await createSchedule(page, {
            name: overlapName,
            playlistId: fixtures.playlists.b.id,
            screenIds: [fixtures.screens[0].id],
            startTime: '12:00',
            endTime: '15:00',
            dayShortcut: 'weekdays',
            priority: 5,
        });

        const banner = page.getByTestId('schedule-conflict-banner');
        await expect(banner).toBeVisible({ timeout: 15_000 });
        await expect(banner).toContainText(
            /overlaps another active schedule/i,
        );
        await expect(banner).toContainText(/Priority conflict|same priority/i);
        await expect(banner).toContainText(primaryName);

        // Priority 5 loses to the active schedule's default priority of 10.
        await expect(banner).toContainText(`“${primaryName}” wins`);

        // A warning must never block the save.
        await expect(page.getByTestId('schedule-save')).toBeEnabled();
        expect(scheduleIdFrom(editUrl)).toBeGreaterThan(0);
    });

    // D
    test('the higher priority schedule wins on a shared screen', async ({
        page,
    }) => {
        const screen = fixtures.screens[0];

        createActiveSchedule({
            name: lowPriorityName,
            playlistName: fixtures.playlists.a.name,
            screenIds: [screen.id],
            startTime: windows.live.start,
            endTime: windows.live.end,
            priority: 5,
        });
        createActiveSchedule({
            name: highPriorityName,
            playlistName: fixtures.playlists.b.name,
            screenIds: [screen.id],
            startTime: windows.live.start,
            endTime: windows.live.end,
            priority: 10,
        });

        await login(page, 'owner@dz.local');
        await page.goto(`/app/screens/${screen.id}`);

        await expect(page.getByTestId('screen-content-source')).toHaveAttribute(
            'data-source',
            'schedule',
            { timeout: 15_000 },
        );
        await expect(page.getByTestId('screen-current-schedule')).toContainText(
            highPriorityName,
        );
    });

    // E
    test('a paused schedule is no longer selected for the screen', async ({
        page,
    }) => {
        const screen = fixtures.screens[0];

        // Outranks the schedules from the precedence spec, so it takes over.
        const scheduleId = createActiveSchedule({
            name: pausedName,
            playlistName: fixtures.playlists.a.name,
            screenIds: [screen.id],
            startTime: windows.live.start,
            endTime: windows.live.end,
            priority: 10,
        });

        await login(page, 'owner@dz.local');
        await page.goto(`/app/screens/${screen.id}`);
        await expect(page.getByTestId('screen-current-schedule')).toContainText(
            pausedName,
            { timeout: 15_000 },
        );

        await page.goto(`/app/schedules/${scheduleId}/edit`);
        await page.getByTestId('schedule-pause').click();
        await expect(page.getByTestId('schedule-edit-status')).toHaveText(
            'Paused',
            { timeout: 15_000 },
        );

        // The screen falls to the next-highest live schedule rather than
        // simply losing the paused one, which is the assertion that proves a
        // paused schedule is skipped instead of merely hidden.
        await page.goto(`/app/screens/${screen.id}`);
        await expect(page.getByTestId('screen-current-schedule')).toContainText(
            highPriorityName,
            { timeout: 15_000 },
        );
        await expect(page.getByTestId('screen-schedule')).not.toContainText(
            pausedName,
        );
    });

    // F
    test('a schedule can target several screens at once', async ({ page }) => {
        await login(page, 'owner@dz.local');

        const editUrl = await createSchedule(page, {
            name: multiScreenName,
            playlistId: fixtures.playlists.a.id,
            screenIds: fixtures.screens.map((screen) => screen.id),
            startTime: '08:00',
            endTime: '09:30',
            dayShortcut: 'every-day',
        });

        const summary = page.getByTestId('schedule-summary-screens');
        for (const screen of fixtures.screens) {
            await expect(summary).toContainText(screen.name);
        }

        const scheduleId = scheduleIdFrom(editUrl);
        await page.goto('/app/schedules');
        await page.getByTestId('schedules-search').fill(multiScreenName);
        await expect(
            page.getByTestId(`schedule-screens-${scheduleId}`),
        ).toContainText(String(fixtures.screens.length), { timeout: 15_000 });
    });

    // G
    test('an overnight 22:00–02:00 window is hinted and saved', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules/create');

        await page.getByTestId('schedule-name').fill(overnightName);
        await page.getByTestId('schedule-step-next').click();

        await page
            .getByTestId(`schedule-playlist-option-${fixtures.playlists.a.id}`)
            .click();
        await page.getByTestId('schedule-step-next').click();

        await page.getByTestId('schedule-start-time').fill('22:00');
        await page.getByTestId('schedule-end-time').fill('02:00');
        await expect(page.getByTestId('schedule-overnight-hint')).toBeVisible();
        await page.getByTestId('schedule-step-next').click();

        await page
            .getByTestId(`schedule-screen-${fixtures.screens[1].id}`)
            .click();
        await page.getByTestId('schedule-step-next').click();

        await expect(page.getByTestId('schedule-summary-time')).toContainText(
            'next day',
        );

        await page.getByTestId('schedule-save').click();
        await page.waitForURL(/\/app\/schedules\/\d+\/edit/, {
            timeout: 20_000,
        });

        // Edit lands on Review — open Timing to confirm the overnight window.
        await page.getByTestId('schedule-step-timing').click();
        await expect(page.getByTestId('schedule-start-time')).toHaveValue(
            '22:00',
        );
        await expect(page.getByTestId('schedule-end-time')).toHaveValue(
            '02:00',
        );
        await expect(page.getByTestId('schedule-overnight-hint')).toBeVisible();
    });

    test('create form stepper validates and advances through steps', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules/create');

        await expect(page.getByTestId('schedule-stepper')).toBeVisible();
        await expect(page.getByTestId('schedule-step-basics')).toHaveAttribute(
            'data-active',
            'true',
        );

        // Cannot leave Basics without a name.
        await page.getByTestId('schedule-step-next').click();
        await expect(page.getByTestId('schedule-step-error')).toBeVisible();
        await expect(page.getByTestId('schedule-section-basics')).toBeVisible();

        await page.getByTestId('schedule-name').fill(`E2E Stepper ${marker}`);
        await page.getByTestId('schedule-step-next').click();
        await expect(
            page.getByTestId('schedule-section-content'),
        ).toBeVisible();
        await expect(page.getByTestId('schedule-step-basics')).toHaveAttribute(
            'data-completed',
            'true',
        );

        await page
            .getByTestId(`schedule-playlist-option-${fixtures.playlists.a.id}`)
            .click();
        await page.getByTestId('schedule-step-next').click();
        await expect(page.getByTestId('schedule-section-timing')).toBeVisible();

        await page.getByTestId('schedule-step-next').click();
        await expect(page.getByTestId('schedule-section-tvs')).toBeVisible();

        await page
            .getByTestId(`schedule-screen-${fixtures.screens[0].id}`)
            .click();
        await page.getByTestId('schedule-step-next').click();
        await expect(page.getByTestId('schedule-section-review')).toBeVisible();
        await expect(page.getByTestId('schedule-save')).toBeVisible();

        await page.getByTestId('schedule-step-back').click();
        await expect(page.getByTestId('schedule-section-tvs')).toBeVisible();
    });

    // H
    test('the player manifest changes over when the window closes', async ({
        request,
    }) => {
        const screen = fixtures.screens[1];
        const changeover = `E2E Schedule ${marker} Changeover`;

        // Deployment is the always-on fallback the schedule takes over from.
        publishPlaylistToScreen(fixtures.playlists.a.name, screen.id);

        const deployed = await manifest(request, screen.token);
        expect(deployed.contentSource).toBe('deployment');

        createActiveSchedule({
            name: changeover,
            playlistName: fixtures.playlists.b.name,
            screenIds: [screen.id],
            startTime: windows.live.start,
            endTime: windows.live.end,
            priority: 9,
        });

        const scheduled = await manifest(request, screen.token);
        expect(scheduled.contentSource).toBe('schedule');
        expect(scheduled.scheduleName).toBe(changeover);
        expect(scheduled.deploymentVersion).not.toBe(
            deployed.deploymentVersion,
        );

        // Move the window out of the way: the same evaluation now falls back.
        setScheduleWindow(changeover, windows.idle.start, windows.idle.end);

        const afterHandover = await manifest(request, screen.token);
        expect(afterHandover.contentSource).toBe('deployment');
        expect(afterHandover.scheduleId ?? null).toBeNull();

        const check = await request.get('/player/api/manifest/check', {
            headers: { 'X-Device-Token': screen.token },
        });
        expect(check.ok()).toBe(true);
        const checkBody = (await check.json()) as Record<string, unknown>;
        expect(checkBody.content_source).toBe('deployment');
        expect(checkBody.version).toBe(afterHandover.deploymentVersion);
    });

    // Week calendar tab
    test('the week calendar lists schedules for the workspace week', async ({
        page,
    }) => {
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules');

        await page.getByTestId('schedules-tab-calendar').click();
        await expect(page.getByTestId('schedule-calendar')).toBeVisible({
            timeout: 15_000,
        });

        await page.getByTestId('schedule-calendar-next').click();
        await expect(page.getByTestId('schedule-calendar')).toBeVisible({
            timeout: 15_000,
        });
    });

    // Preview dialog
    test('preview reports the pinned playlist and upcoming windows', async ({
        page,
    }) => {
        const scheduleId = scheduleIdFrom(primaryEditUrl);

        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules');
        await page.getByTestId('schedules-search').fill(primaryName);
        await expect(
            page.getByTestId(`schedule-row-${scheduleId}`),
        ).toBeVisible({ timeout: 15_000 });

        await page.getByTestId(`schedule-menu-${scheduleId}`).click();
        await page.getByTestId(`schedule-preview-${scheduleId}`).click();

        const dialog = page.getByTestId('schedule-preview-dialog');
        await expect(dialog).toBeVisible({ timeout: 15_000 });
        await expect(
            page.getByTestId('schedule-preview-playlist'),
        ).toContainText(fixtures.playlists.a.name, { timeout: 15_000 });
        await expect(page.getByTestId('schedule-preview-days')).toHaveText(
            'Mon–Fri',
        );
        await expect(page.getByTestId('schedule-preview-status')).toHaveText(
            'Active',
        );
    });

    // I
    test('schedules are isolated to their workspace', async ({ page }) => {
        await registerCustomer(page);
        await page.goto('/app/schedules');

        await expect(
            page.getByRole('heading', { name: 'Schedules', exact: true }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(page.getByText(primaryName)).toHaveCount(0);
        await expect(page.getByText('No schedules yet')).toBeVisible();
    });

    // J
    test('viewer cannot mutate schedules', async ({ page }) => {
        await login(page, 'viewer@dz.local');
        await page.goto('/app/schedules');

        await expect(
            page.getByRole('heading', { name: 'Schedules', exact: true }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('schedules-create')).toHaveCount(0);

        await page.goto(primaryEditUrl);
        await expect(page.getByTestId('schedule-form')).toBeVisible({
            timeout: 15_000,
        });
        await expect(page.getByTestId('schedule-save')).toBeDisabled();
        await expect(page.getByTestId('schedule-activate')).toHaveCount(0);
        await expect(page.getByTestId('schedule-edit-duplicate')).toHaveCount(
            0,
        );
    });

    // K
    test('the schedules library works on a mobile viewport', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await login(page, 'owner@dz.local');
        await page.goto('/app/schedules');

        await expect(
            page.getByRole('heading', { name: 'Schedules', exact: true }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(page.getByTestId('schedules-create')).toBeVisible();

        // The week grid is desktop only; phones get the agenda instead.
        await page.getByTestId('schedules-tab-calendar').click();
        await expect(page.getByTestId('schedule-calendar')).toBeVisible({
            timeout: 15_000,
        });
        await expect(
            page.locator('[data-test^="schedule-agenda-day-"]').first(),
        ).toBeVisible();
    });
});
