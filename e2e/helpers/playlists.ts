import { execFileSync } from 'node:child_process';

export type SeededDesign = {
    id: number;
    name: string;
};

/**
 * Playlists may only reference published Screen Designs, so the fixtures are
 * created through the real actions rather than the UI (which would mean
 * driving the design editor three times per run).
 *
 * Names use the `E2E ` prefix so `LocalDevSeeder` can clean them up.
 */
export function seedPublishedDesigns(
    marker: string,
    count = 3,
): SeededDesign[] {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$create = app(\\App\\Actions\\ScreenDesigns\\CreateBlankScreenDesign::class);
$publish = app(\\App\\Actions\\ScreenDesigns\\PublishScreenDesign::class);
$seeded = [];

foreach (range(1, ${count}) as $index) {
    $name = 'E2E Playlist ' . ${JSON.stringify(marker)} . ' ' . $index;

    $design = $create->handle(
        $user,
        $workspace,
        $name,
        \\App\\Enums\\TemplateOrientation::Landscape,
    );

    $version = $design->versions()->orderByDesc('version_number')->firstOrFail();
    $schema = $version->schema;
    $schema['elements'] = [[
        'id' => 'e2e-heading-' . $index,
        'type' => 'text',
        'name' => 'Heading',
        'x' => 160,
        'y' => 420,
        'width' => 1600,
        'height' => 240,
        'props' => [
            'text' => $name,
            'fontSize' => 120,
            'fontWeight' => 700,
            'color' => '#ffffff',
            'textAlign' => 'center',
        ],
    ]];
    $version->forceFill(['schema' => $schema])->save();

    $publish->handle($user, $design);

    $seeded[] = ['id' => $design->id, 'name' => $name];
}

echo json_encode($seeded);
`;

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', '--execute', script],
        {
            cwd: process.cwd(),
            encoding: 'utf8',
        },
    );

    const match = output.match(/\[.*\]/s);
    if (!match) {
        throw new Error(`Could not seed published designs. Output: ${output}`);
    }

    return JSON.parse(match[0]) as SeededDesign[];
}

/**
 * Deploys a published playlist to a screen. The library's Publish to Screen
 * dialog covers this too, but the action is invoked directly here so the
 * Player's playlist manifest path is exercised without depending on a screen
 * being pairable through the UI first.
 */
export function publishPlaylistToScreen(
    playlistName: string,
    screenId: number,
): void {
    const script = `
$user = \\App\\Models\\User::where('email', 'owner@dz.local')->firstOrFail();
$workspace = $user->currentWorkspace;
$playlist = \\App\\Models\\Playlist::query()
    ->where('workspace_id', $workspace->id)
    ->where('name', ${JSON.stringify(playlistName)})
    ->firstOrFail();

app(\\App\\Actions\\Playlists\\PublishPlaylistToScreens::class)
    ->handle($user, $workspace, $playlist, [${screenId}]);

echo 'deployed';
`;

    let output = '';
    try {
        output = execFileSync(
            'php',
            ['artisan', 'tinker', '--execute', script],
            {
                cwd: process.cwd(),
                encoding: 'utf8',
                stdio: ['pipe', 'pipe', 'pipe'],
            },
        );
    } catch (error) {
        const err = error as {
            stdout?: string;
            stderr?: string;
            message?: string;
        };
        throw new Error(
            `Could not deploy playlist. stdout=${err.stdout ?? ''} stderr=${err.stderr ?? ''} message=${err.message ?? ''}`,
        );
    }

    if (!output.includes('deployed')) {
        throw new Error(`Could not deploy playlist. Output: ${output}`);
    }
}
