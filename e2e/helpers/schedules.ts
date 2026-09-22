import { execFileSync } from 'node:child_process';

export type SeededScreen = {
    id: number;
    name: string;
    /** Plaintext device token, for the player manifest API. */
    token: string;
};

export type SeededPlaylist = {
    id: number;
    name: string;
};

export type ScheduleFixtures = {
    /** Two published playlists, so precedence has distinguishable content. */
    playlists: { a: SeededPlaylist; b: SeededPlaylist };
    screens: SeededScreen[];
};

function tinker(script: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', script], {
        cwd: process.cwd(),
        encoding: 'utf8',
    });
}

function tinkerJson<T>(script: string, pattern: RegExp): T {
    const output = tinker(script);
    const match = output.match(pattern);

    if (!match) {
        throw new Error(`Unexpected tinker output: ${output}`);
    }

    return JSON.parse(match[0]) as T;
}

/**
 * Schedules only play **published** playlists on real Screens, and pairing a
 * screen through the UI needs a live player. Both are set up through the real
 * actions instead, so the specs can focus on scheduling itself.
 *
 * Names use the `E2E ` prefix so `LocalDevSeeder` can clean them up.
 */
export function seedScheduleFixtures(marker: string): ScheduleFixtures {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$marker = ${JSON.stringify(marker)};

$createDesign = app(\\App\\Actions\\ScreenDesigns\\CreateBlankScreenDesign::class);
$publishDesign = app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class);
$createPlaylist = app(\\App\\Actions\\Playlists\\CreatePlaylist::class);
$savePlaylist = app(\\App\\Actions\\Playlists\\SavePlaylistDraft::class);
$publishPlaylist = app(\\App\\Actions\\Playlists\\PublishPlaylist::class);

$playlists = [];

foreach (['A', 'B'] as $suffix) {
    $designName = 'E2E Schedule ' . $marker . ' Design ' . $suffix;

    $design = $createDesign->handle(
        $user,
        $workspace,
        $designName,
        \\App\\Enums\\TemplateOrientation::Landscape,
    );

    $version = $design->versions()->orderByDesc('version_number')->firstOrFail();
    $schema = $version->schema;
    $schema['elements'] = [[
        'id' => 'e2e-heading-' . \\Illuminate\\Support\\Str::lower($suffix),
        'type' => 'text',
        'name' => 'Heading',
        'x' => 160,
        'y' => 420,
        'width' => 1600,
        'height' => 240,
        'props' => [
            'text' => $designName,
            'fontSize' => 120,
            'fontWeight' => 700,
            'color' => '#ffffff',
            'textAlign' => 'center',
        ],
    ]];
    $version->forceFill(['schema' => $schema])->save();
    $publishDesign->handle($user, $design);

    $playlistName = 'E2E Schedule ' . $marker . ' Playlist ' . $suffix;
    $playlist = $createPlaylist->handle($user, $workspace, $playlistName);
    $savePlaylist->handle($user, $playlist, [
        'items' => [[
            'screen_design_id' => $design->id,
            'duration_seconds' => 10,
        ]],
    ]);
    $publishPlaylist->handle($user, $playlist);

    $playlists[$suffix] = ['id' => $playlist->id, 'name' => $playlistName];
}

$screens = [];

foreach ([1, 2] as $index) {
    $token = \\Illuminate\\Support\\Str::random(64);

    $screen = \\App\\Models\\Screen::query()->create([
        'workspace_id' => $workspace->id,
        'name' => 'E2E Schedule ' . $marker . ' TV ' . $index,
        'orientation' => 'landscape',
        'operational_status' => \\App\\Enums\\ScreenOperationalStatus::Active,
        'created_by' => $user->id,
    ]);

    \\App\\Models\\ScreenDevice::query()->create([
        'screen_id' => $screen->id,
        'device_identifier' => 'dev_e2e_' . \\Illuminate\\Support\\Str::lower((string) \\Illuminate\\Support\\Str::ulid()),
        'device_token_hash' => \\App\\Models\\ScreenDevice::hashToken($token),
        'paired_at' => now(),
    ]);

    $screens[] = ['id' => $screen->id, 'name' => $screen->name, 'token' => $token];
}

echo json_encode([
    'playlists' => ['a' => $playlists['A'], 'b' => $playlists['B']],
    'screens' => $screens,
]);
`;

    return tinkerJson<ScheduleFixtures>(script, /\{.*\}/s);
}

/**
 * Creates an already-active schedule through the real actions. Used where a
 * spec needs a second schedule to compete with, rather than to exercise the
 * create form again.
 */
export function createActiveSchedule(options: {
    name: string;
    playlistName: string;
    screenIds: number[];
    startTime: string;
    endTime: string;
    /** ISO weekdays, 1 = Monday … 7 = Sunday. Defaults to every day. */
    days?: number[];
    priority: number;
}): number {
    const days = options.days ?? [1, 2, 3, 4, 5, 6, 7];

    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;

$playlist = \\App\\Models\\Playlist::query()
    ->where('workspace_id', $workspace->id)
    ->where('name', ${JSON.stringify(options.playlistName)})
    ->firstOrFail();

$schedule = app(\\App\\Actions\\Schedules\\CreateSchedule::class)->handle($user, $workspace, [
    'name' => ${JSON.stringify(options.name)},
    'playlist_id' => $playlist->id,
    'screen_ids' => ${JSON.stringify(options.screenIds)},
    'start_time' => ${JSON.stringify(options.startTime)},
    'end_time' => ${JSON.stringify(options.endTime)},
    'days_of_week' => ${JSON.stringify(days)},
    'priority' => ${options.priority},
    'timezone' => $workspace->timezone,
]);

app(\\App\\Actions\\Schedules\\ActivateSchedule::class)->handle($user, $schedule);

echo json_encode(['id' => $schedule->id]);
`;

    return tinkerJson<{ id: number }>(script, /\{.*\}/s).id;
}

/**
 * Rewrites a schedule's window directly. Real clock travel is not available to
 * the browser, so a changeover is produced by moving the window instead of
 * moving time — the evaluator sees exactly the same situation either way.
 */
export function setScheduleWindow(
    name: string,
    startTime: string,
    endTime: string,
): void {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();

$schedule = \\App\\Models\\Schedule::query()
    ->where('workspace_id', $user->currentWorkspace->id)
    ->where('name', ${JSON.stringify(name)})
    ->firstOrFail();

$schedule->forceFill([
    'start_time' => ${JSON.stringify(startTime)},
    'end_time' => ${JSON.stringify(endTime)},
])->save();

echo 'window-updated';
`;

    const output = tinker(script);

    if (!output.includes('window-updated')) {
        throw new Error(
            `Could not move the schedule window. Output: ${output}`,
        );
    }
}

/**
 * A window that is live right now in the workspace timezone, and one that is
 * not, so schedule matching can be asserted without waiting on the clock.
 */
export function workspaceWindows(): {
    live: { start: string; end: string };
    idle: { start: string; end: string };
} {
    const payload = tinkerJson<{ hour: number }>(
        `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
echo json_encode(['hour' => (int) now($user->currentWorkspace->timezone)->format('G')]);
`,
        /\{.*\}/s,
    );

    const clock = (hour: number) =>
        `${String(((hour % 24) + 24) % 24).padStart(2, '0')}:00`;

    return {
        // Covers the whole current hour, so the window cannot lapse mid-test.
        live: { start: clock(payload.hour), end: clock(payload.hour + 2) },
        idle: { start: clock(payload.hour + 4), end: clock(payload.hour + 6) },
    };
}
