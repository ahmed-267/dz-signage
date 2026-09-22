<?php

namespace Database\Seeders;

use App\Actions\ScreenDesigns\PruneAbandonedScreenDesigns;
use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Enums\MediaType;
use App\Enums\PlaybackEventType;
use App\Enums\PlayerPlaybackState;
use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\TemplateOrientation;
use App\Enums\WorkspaceIndustry;
use App\Enums\WorkspaceRole;
use App\Models\BrandKit;
use App\Models\Deployment;
use App\Models\Location;
use App\Models\MediaAsset;
use App\Models\PlaybackEvent;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDailyStat;
use App\Models\ScreenDesign;
use App\Models\ScreenDesignVersion;
use App\Models\ScreenDevice;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Support\Demo\DemoHeartbeatSimulator;
use App\Support\Demo\DemoImageFactory;
use App\Support\Demo\DemoPdfFactory;
use App\Support\Demo\DemoPhotoCatalog;
use App\Support\Demo\E2eArtifactCleaner;
use App\Support\MediaStorage;
use App\Support\Playlists\PlaylistDefaults;
use App\Support\Rendering\StarterTemplateCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Coherent demo content for the Local Dev Workspace.
 *
 * Run: php artisan db:seed --class=DemoWorkspaceSeeder
 * Refuses to run in production.
 */
class DemoWorkspaceSeeder extends Seeder
{
    /**
     * When true, wipe demo-tagged history (devices / daily stats / deployments /
     * playback events) before recreating ~80 days of telemetry.
     */
    public bool $freshDemo = false;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->demoLog('warn', 'DemoWorkspaceSeeder refused in production.');

            return;
        }

        E2eArtifactCleaner::run();

        $owner = User::query()->where('email', 'owner@dz.local')->first();
        if ($owner === null) {
            $this->demoLog('warn', 'Run LocalDevSeeder first (owner@dz.local missing).');

            return;
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['slug' => 'local-dev-workspace'],
            [
                'name' => 'North & Bean Café',
                'industry' => WorkspaceIndustry::Cafe,
                'country' => 'United Kingdom',
                'timezone' => 'Europe/London',
            ],
        );

        if ($workspace->name === 'Local Dev Workspace') {
            $workspace->forceFill(['name' => 'North & Bean Café'])->save();
        }

        WorkspaceMember::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
            ['role' => WorkspaceRole::Owner],
        );
        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        $this->demoLog('info', 'Cleaning untitled/blank demo Screen Designs…');
        $this->cleanJunkScreenDesigns($workspace);

        $this->demoLog('info', 'Seeding demo Media (curated photos)…');
        $media = $this->seedMedia($workspace, $owner);

        $this->demoLog('info', 'Seeding Brand Kit…');
        $this->seedBrandKit($workspace, $media['logo'] ?? null);

        $this->demoLog('info', 'Seeding Locations…');
        $locations = $this->seedLocations($workspace, $owner);

        $this->demoLog('info', 'Seeding Screen Designs from starter templates…');
        $designs = $this->seedDesigns($workspace, $owner, $media);

        $this->demoLog('info', 'Seeding Playlists…');
        $playlists = $this->seedPlaylists($workspace, $owner, $designs);

        $this->demoLog('info', 'Seeding Schedules…');
        $this->seedSchedules($workspace, $owner, $playlists);

        $this->demoLog('info', 'Seeding demo Screens…');
        $screens = $this->seedScreens($workspace, $owner, $locations);

        $this->demoLog('info', 'Seeding demo history (~80 days)…');
        $this->seedHistory($workspace, $owner, $screens, $designs, $playlists);

        $this->demoLog('info', 'Demo workspace ready for '.$workspace->name);
    }

    private function demoLog(string $level, string $message): void
    {
        /** @var mixed $command */
        $command = $this->command;

        if (! is_object($command)) {
            return;
        }

        if ($level === 'warn' && method_exists($command, 'warn')) {
            $command->warn($message);

            return;
        }

        if (method_exists($command, 'info')) {
            $command->info($message);
        }
    }

    /**
     * Remove Untitled / blank / empty-schema Screen Designs for this demo
     * workspace only. E2E fixtures are already stripped by E2eArtifactCleaner.
     */
    private function cleanJunkScreenDesigns(Workspace $workspace): void
    {
        $pruner = app(PruneAbandonedScreenDesigns::class);

        $candidates = ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('name', 'like', 'Untitled%')
                    ->orWhere('name', 'like', 'Blank%')
                    ->orWhere('name', 'like', 'New Landscape%')
                    ->orWhere('name', 'like', 'New Portrait%')
                    ->orWhere('name', 'like', 'New Screen Design%')
                    ->orWhere('name', 'like', 'E2E %')
                    ->orWhere('name', 'like', 'AI %')
                    ->orWhere('name', 'like', 'AI ·%')
                    ->orWhere('name', 'like', 'North & Bean%');
            })
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->get();

        $keepNames = [
            'Morning Coffee Promotion',
            'Lunch Menu Board',
            'Weekend Sale',
            'Lobby Welcome',
            'Hotel Welcome',
            'Friday Announcement',
            'Prayer Information',
            'Company News',
        ];

        foreach ($candidates as $design) {
            if (in_array($design->name, $keepNames, true)) {
                continue;
            }

            // Brand-kit naming bug left Use Template copies titled "North & Bean Café".
            // Curated demo designs use keep-list names — drop the spam regardless of schema.
            if (preg_match('/^(North & Bean|AI[\s·])/iu', $design->name) === 1) {
                $pruner->deleteCascade($design);

                continue;
            }

            $pruner->deleteCascade($design);
        }

        // Strict curated set: anything outside the keep list is disposable demo pollution
        // (including meaningful Template/AI copies left by E2E against owner@dz.local).
        ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keepNames)
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->get()
            ->each(function (ScreenDesign $design) use ($pruner): void {
                $pruner->deleteCascade($design);
            });
    }

    /**
     * @return array<string, MediaAsset>
     */
    private function seedMedia(Workspace $workspace, User $owner): array
    {
        $bySlug = [];

        // Drop E2E / AI leftover images and legacy ring logos from earlier seeds.
        MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('name', 'like', 'Design Img%')
                    ->orWhere('name', 'like', 'E2E %')
                    ->orWhere(function ($legacy): void {
                        $legacy->where('type', MediaType::Logo)
                            ->where('extension', 'png')
                            ->where('metadata->seed', 'demo-workspace');
                    });
            })
            ->get()
            ->each(function (MediaAsset $asset): void {
                if (filled($asset->storage_path) && filled($asset->storage_disk)) {
                    try {
                        Storage::disk($asset->storage_disk)->delete($asset->storage_path);
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                $asset->delete();
            });

        // Drop legacy synthetic silhouette images from earlier demo seeds.
        MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('metadata->seed', 'demo-workspace')
                    ->where(function ($inner): void {
                        $inner->where('type', MediaType::Image)
                            ->where(function ($img): void {
                                $img->where('extension', 'png')
                                    ->orWhereIn('metadata->slug', [
                                        'cafe-latte-promo',
                                        'restaurant-plate',
                                        'retail-fashion',
                                        'hotel-lobby',
                                        'corporate-welcome',
                                        'gym-session',
                                        'property-listing',
                                        'conference-event',
                                        'school-notice',
                                        'healthcare-waiting',
                                        'masjid-welcome',
                                        'abstract-signage',
                                    ]);
                            });
                    });
            })
            ->get()
            ->each(function (MediaAsset $asset): void {
                // Keep only if already a curated JPEG with a current catalog slug.
                $slug = is_array($asset->metadata) ? ($asset->metadata['slug'] ?? null) : null;
                $currentSlugs = collect(DemoPhotoCatalog::all())->pluck('slug')->all();
                if ($asset->extension === 'jpg' && is_string($slug) && in_array($slug, $currentSlugs, true)) {
                    return;
                }

                if (filled($asset->storage_path) && filled($asset->storage_disk)) {
                    try {
                        Storage::disk($asset->storage_disk)->delete($asset->storage_path);
                    } catch (\Throwable) {
                        // ignore
                    }
                }
                $asset->delete();
            });

        foreach (DemoPhotoCatalog::all() as $photo) {
            $path = DemoPhotoCatalog::path($photo['file']);
            if (! is_file($path)) {
                $this->demoLog('warn', "Missing demo photo {$photo['file']} — skip.");

                continue;
            }

            $binary = (string) file_get_contents($path);
            $size = @getimagesize($path);
            $bySlug[$photo['slug']] = $this->storeBinaryMedia(
                $workspace,
                $owner,
                $photo['slug'],
                $photo['name'],
                MediaType::Image,
                $binary,
                'jpg',
                'image/jpeg',
                is_array($size) ? (int) $size[0] : 1600,
                is_array($size) ? (int) $size[1] : 1067,
            );
        }

        // Alias slugs used when binding designs.
        $aliases = [
            'cafe-latte-promo' => 'iced-latte',
            'restaurant-plate' => 'plated-lunch-special',
            'retail-fashion' => 'retail-fashion-floor',
            'corporate-welcome' => 'corporate-office',
            'gym-session' => 'gym-floor',
            'property-listing' => 'property-exterior',
            'conference-event' => 'conference-stage',
            'school-notice' => 'education-campus',
            'healthcare-waiting' => 'healthcare-clinic',
            'masjid-welcome' => 'masjid-interior',
            'abstract-signage' => 'mountain-landscape',
        ];
        foreach ($aliases as $alias => $slug) {
            if (isset($bySlug[$slug])) {
                $bySlug[$alias] = $bySlug[$slug];
            }
        }

        $logos = [
            [
                'slug' => 'logo-north-bean',
                'name' => 'North & Bean Logo',
                'file' => 'north-bean.svg',
                'fallback_mark' => 'North & Bean',
                'bg' => [54, 36, 27],
                'fg' => [251, 191, 36],
            ],
            [
                'slug' => 'logo-retail',
                'name' => 'Demo Retail Logo',
                'file' => 'retail.svg',
                'fallback_mark' => 'RETAIL',
                'bg' => [15, 23, 42],
                'fg' => [56, 189, 248],
            ],
            [
                'slug' => 'logo-corporate',
                'name' => 'Demo Corporate Logo',
                'file' => 'corporate.svg',
                'fallback_mark' => 'CORPORATE',
                'bg' => [30, 41, 59],
                'fg' => [226, 232, 240],
            ],
        ];

        foreach ($logos as $spec) {
            $svgPath = resource_path('demo/logos/'.$spec['file']);
            if (is_file($svgPath)) {
                $binary = (string) file_get_contents($svgPath);
                $bySlug[$spec['slug']] = $this->storeBinaryMedia(
                    $workspace,
                    $owner,
                    $spec['slug'],
                    $spec['name'],
                    MediaType::Logo,
                    $binary,
                    'svg',
                    'image/svg+xml',
                    512,
                    160,
                );
            } else {
                $binary = DemoImageFactory::logo($spec['fallback_mark'], $spec['bg'], $spec['fg']);
                $bySlug[$spec['slug']] = $this->storeBinaryMedia(
                    $workspace,
                    $owner,
                    $spec['slug'],
                    $spec['name'],
                    MediaType::Logo,
                    $binary,
                    'png',
                    'image/png',
                    512,
                    160,
                );
            }
        }

        $docs = [
            ['slug' => 'doc-summer-menu', 'name' => 'Summer Menu.pdf', 'body' => "North & Bean Café\nSummer Menu\n\nIced Latte — £3.40\nIced Mocha — £3.80\nSeasonal pastry — £2.90\n"],
            ['slug' => 'doc-event-programme', 'name' => 'Event Programme.pdf', 'body' => "Product Summit Programme\n09:00 Registration\n10:00 Keynote\n14:00 Breakouts\n"],
            ['slug' => 'doc-visitor-info', 'name' => 'Visitor Information.pdf', 'body' => "Visitor Information\nPlease sign in at reception.\nWi-Fi: Guest / welcome\n"],
        ];

        foreach ($docs as $spec) {
            $bySlug[$spec['slug']] = $this->storeBinaryMedia(
                $workspace,
                $owner,
                $spec['slug'],
                $spec['name'],
                MediaType::Document,
                DemoPdfFactory::document($spec['name'], $spec['body']),
                'pdf',
                'application/pdf',
            );
        }

        $texts = [
            ['slug' => 'text-welcome', 'name' => 'Welcome Message', 'text' => 'Welcome to North & Bean. Please order at the counter.'],
            ['slug' => 'text-weekend-sale', 'name' => 'Weekend Sale Announcement', 'text' => 'Weekend sale — 20% off pastries Saturday & Sunday.'],
            ['slug' => 'text-opening-hours', 'name' => 'Opening Hours Notice', 'text' => 'Open Mon–Fri 07:00–18:00 · Sat–Sun 08:00–16:00'],
        ];

        foreach ($texts as $spec) {
            $bySlug[$spec['slug']] = MediaAsset::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $spec['name'],
                ],
                [
                    'type' => MediaType::Text,
                    'text_content' => $spec['text'],
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'metadata' => ['seed' => 'demo-workspace', 'slug' => $spec['slug']],
                ],
            );
        }

        $links = [
            ['slug' => 'link-website', 'name' => 'North & Bean Website', 'url' => 'https://www.example.com'],
            ['slug' => 'link-menu', 'name' => 'Online Menu', 'url' => 'https://www.example.com/menu'],
            ['slug' => 'link-events', 'name' => 'Events Calendar', 'url' => 'https://www.example.com/events'],
        ];

        foreach ($links as $spec) {
            $bySlug[$spec['slug']] = MediaAsset::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $spec['name'],
                ],
                [
                    'type' => MediaType::Link,
                    'url' => $spec['url'],
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'metadata' => ['seed' => 'demo-workspace', 'slug' => $spec['slug']],
                ],
            );
        }

        $bySlug['logo'] = $bySlug['logo-north-bean'];

        return $bySlug;
    }

    private function storeBinaryMedia(
        Workspace $workspace,
        User $owner,
        string $slug,
        string $name,
        MediaType $type,
        string $binary,
        string $extension,
        string $mime,
        ?int $width = null,
        ?int $height = null,
    ): MediaAsset {
        $path = "workspaces/{$workspace->id}/media/demo/{$slug}.{$extension}";
        Storage::disk(MediaStorage::disk())->put($path, $binary);

        $existing = MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where('metadata->slug', $slug)
            ->first();

        $attrs = [
            'name' => $name,
            'type' => $type,
            'original_filename' => "{$slug}.{$extension}",
            'storage_disk' => MediaStorage::disk(),
            'storage_path' => $path,
            'mime_type' => $mime,
            'extension' => $extension,
            'size_bytes' => strlen($binary),
            'width' => $width,
            'height' => $height,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'metadata' => [
                'seed' => 'demo-workspace',
                'slug' => $slug,
            ],
        ];

        if ($existing !== null) {
            $existing->forceFill($attrs)->save();

            return $existing->fresh() ?? $existing;
        }

        return MediaAsset::query()->create([
            'workspace_id' => $workspace->id,
            ...$attrs,
        ]);
    }

    private function seedBrandKit(Workspace $workspace, ?MediaAsset $logo): void
    {
        if (! class_exists(BrandKit::class)) {
            return;
        }

        BrandKit::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'name' => 'North & Bean',
                'tagline' => 'Coffee worth gathering for',
                'primary_color' => '#C47A3F',
                'secondary_color' => '#36241B',
                'accent_color' => '#FBBF24',
                'background_color' => '#1C1410',
                'text_color' => '#F8FAFC',
                'heading_font' => 'Outfit',
                'body_font' => 'system-ui',
                'logo_media_asset_id' => $logo?->id,
            ],
        );
    }

    /**
     * @return array<string, Location>
     */
    private function seedLocations(Workspace $workspace, User $owner): array
    {
        if (! class_exists(Location::class)) {
            return [];
        }

        $defs = [
            ['slug' => 'nottingham-cafe', 'name' => 'Nottingham Café', 'city' => 'Nottingham', 'address' => '12 Market Street'],
            ['slug' => 'birmingham-retail', 'name' => 'Birmingham Retail Store', 'city' => 'Birmingham', 'address' => '88 High Street'],
            ['slug' => 'london-office', 'name' => 'London Office', 'city' => 'London', 'address' => '1 King William Street'],
        ];

        $out = [];
        foreach ($defs as $def) {
            $out[$def['slug']] = Location::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $def['name'],
                ],
                [
                    'address_line1' => $def['address'],
                    'city' => $def['city'],
                    'country' => 'United Kingdom',
                    'timezone' => 'Europe/London',
                    'created_by' => $owner->id,
                ],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, MediaAsset>  $media
     * @return array<string, ScreenDesign>
     */
    private function seedDesigns(Workspace $workspace, User $owner, array $media): array
    {
        $wanted = [
            'cafe-promotion' => ['name' => 'Morning Coffee Promotion', 'media' => 'iced-latte', 'logo' => 'logo-north-bean'],
            'restaurant-digital-menu' => ['name' => 'Lunch Menu Board', 'media' => 'plated-lunch-special', 'logo' => 'logo-north-bean'],
            'retail-sale-promotion' => ['name' => 'Weekend Sale', 'media' => 'retail-fashion-floor', 'logo' => 'logo-retail'],
            'corporate-welcome' => ['name' => 'Lobby Welcome', 'media' => 'corporate-office', 'logo' => 'logo-corporate'],
            'hotel-welcome' => ['name' => 'Hotel Welcome', 'media' => 'hotel-lobby', 'logo' => 'logo-corporate'],
            'jumuah-announcement' => ['name' => 'Friday Announcement', 'media' => 'masjid-interior', 'logo' => null],
            'prayer-times-landscape' => ['name' => 'Prayer Information', 'media' => 'masjid-interior', 'logo' => null],
            'corporate-announcement' => ['name' => 'Company News', 'media' => 'office-meeting-space', 'logo' => 'logo-corporate'],
        ];

        $catalog = collect(StarterTemplateCatalog::definitions())->keyBy('slug');
        $designs = [];

        foreach ($wanted as $templateSlug => $meta) {
            $def = $catalog->get($templateSlug);
            if ($def === null) {
                continue;
            }

            $template = Template::query()->where('slug', $templateSlug)->first();
            $schema = is_array($def['schema'] ?? null) ? $def['schema'] : [];
            $schema = $this->bindMediaIntoSchema(
                $schema,
                $media[$meta['media']] ?? null,
                $meta['logo'] !== null ? ($media[$meta['logo']] ?? null) : null,
            );

            $orientation = $def['orientation'] instanceof TemplateOrientation
                ? $def['orientation']
                : (TemplateOrientation::tryFrom((string) ($def['orientation'] ?? 'landscape')) ?? TemplateOrientation::Landscape);
            $isPortrait = $orientation === TemplateOrientation::Portrait;

            $design = ScreenDesign::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $meta['name'],
                ],
                [
                    'orientation' => $orientation,
                    'canvas_width' => $isPortrait ? 1080 : 1920,
                    'canvas_height' => $isPortrait ? 1920 : 1080,
                    'status' => ScreenDesignStatus::Published,
                    'source_template_id' => $template?->id,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );

            $version = ScreenDesignVersion::query()->updateOrCreate(
                [
                    'screen_design_id' => $design->id,
                    'version_number' => 1,
                ],
                [
                    'schema' => $schema,
                    'created_by' => $owner->id,
                    'published_at' => now(),
                ],
            );

            $design->forceFill([
                'published_version_id' => $version->id,
                'status' => ScreenDesignStatus::Published,
            ])->save();

            $designs[$templateSlug] = $design->fresh(['publishedVersion']) ?? $design;
        }

        return $designs;
    }

    /**
     * Bind real photo / logo MediaAssets into image & logo placeholders.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private function bindMediaIntoSchema(array $schema, ?MediaAsset $image, ?MediaAsset $logo): array
    {
        $elements = $schema['elements'] ?? null;
        if (! is_array($elements)) {
            return $schema;
        }

        foreach ($elements as $index => $element) {
            if (! is_array($element)) {
                continue;
            }

            $type = $element['type'] ?? null;
            $props = is_array($element['props'] ?? null) ? $element['props'] : [];

            if ($type === 'image' && $image !== null) {
                $props['mediaAssetId'] = $image->id;
                $props['placeholder'] = false;
                $element['props'] = $props;
                $elements[$index] = $element;
            }

            if ($type === 'logo' && $logo !== null) {
                $props['mediaAssetId'] = $logo->id;
                $props['placeholder'] = false;
                $element['props'] = $props;
                $elements[$index] = $element;
            }
        }

        $schema['elements'] = array_values($elements);

        return $schema;
    }

    /**
     * @param  array<string, ScreenDesign>  $designs
     * @return array<string, Playlist>
     */
    private function seedPlaylists(Workspace $workspace, User $owner, array $designs): array
    {
        $defs = [
            'Cafe Daily Rotation' => ['cafe-promotion', 'restaurant-digital-menu', 'retail-sale-promotion'],
            'Retail Promotions' => ['retail-sale-promotion', 'cafe-promotion'],
            'Masjid Information' => ['jumuah-announcement', 'prayer-times-landscape'],
            'Corporate Lobby' => ['corporate-welcome', 'corporate-announcement'],
            'Hotel Lobby' => ['hotel-welcome', 'corporate-welcome'],
        ];

        $playlists = [];

        foreach ($defs as $name => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                if (isset($designs[$slug]) && $designs[$slug]->publishedVersion !== null) {
                    $items[] = $designs[$slug];
                }
            }
            if ($items === []) {
                continue;
            }

            $playlist = Playlist::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $name,
                ],
                [
                    'orientation' => $items[0]->orientation,
                    'status' => PlaylistStatus::Published,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );

            $version = PlaylistVersion::query()->updateOrCreate(
                [
                    'playlist_id' => $playlist->id,
                    'version_number' => 1,
                ],
                [
                    'created_by' => $owner->id,
                    'published_at' => now(),
                ],
            );

            PlaylistItem::query()->where('playlist_version_id', $version->id)->delete();

            foreach ($items as $index => $design) {
                PlaylistItem::query()->create([
                    'playlist_version_id' => $version->id,
                    'position' => $index + 1,
                    'screen_design_id' => $design->id,
                    'screen_design_version_id' => $design->published_version_id,
                    'duration_seconds' => PlaylistDefaults::durationSeconds(),
                    'transition' => PlaylistDefaults::transition(),
                    'transition_speed' => PlaylistDefaults::transitionSpeed(),
                    'is_active' => true,
                ]);
            }

            $playlist->forceFill([
                'published_version_id' => $version->id,
                'published_at' => now(),
                'status' => PlaylistStatus::Published,
            ])->save();

            $playlists[$name] = $playlist->fresh(['publishedVersion']) ?? $playlist;
        }

        return $playlists;
    }

    /**
     * @param  array<string, Playlist>  $playlists
     */
    private function seedSchedules(Workspace $workspace, User $owner, array $playlists): void
    {
        $cafe = $playlists['Cafe Daily Rotation'] ?? null;
        if ($cafe?->published_version_id === null) {
            return;
        }

        Schedule::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'name' => 'Weekday Café Hours',
            ],
            [
                'playlist_id' => $cafe->id,
                'playlist_version_id' => $cafe->published_version_id,
                'timezone' => 'Europe/London',
                'start_time' => '07:00:00',
                'end_time' => '18:00:00',
                'days_of_week' => [1, 2, 3, 4, 5],
                'priority' => 8,
                'status' => ScheduleStatus::Active,
                'activated_at' => now(),
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ],
        );
    }

    /**
     * @param  array<string, Location>  $locations
     * @return list<Screen>
     */
    private function seedScreens(Workspace $workspace, User $owner, array $locations): array
    {
        $defs = [
            ['name' => 'Counter Screen', 'location' => 'nottingham-cafe', 'slug' => 'counter-screen'],
            ['name' => 'Menu Board', 'location' => 'nottingham-cafe', 'slug' => 'menu-board'],
            ['name' => 'Window Display', 'location' => 'birmingham-retail', 'slug' => 'window-display'],
            ['name' => 'Reception TV', 'location' => 'london-office', 'slug' => 'reception-tv'],
            ['name' => 'Kitchen Prep Display', 'location' => 'nottingham-cafe', 'slug' => 'kitchen-prep'],
        ];

        $screens = [];
        foreach ($defs as $def) {
            $screen = Screen::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'name' => $def['name'],
                ],
                [
                    'orientation' => 'landscape',
                    'operational_status' => ScreenOperationalStatus::Active,
                    'created_by' => $owner->id,
                    'location_id' => $locations[$def['location']]->id ?? null,
                ],
            );

            if (isset($locations[$def['location']]) && $screen->location_id === null) {
                $screen->forceFill(['location_id' => $locations[$def['location']]->id])->save();
            }

            $screens[] = $screen;
        }

        return $screens;
    }

    /**
     * @param  list<Screen>  $screens
     * @param  array<string, ScreenDesign>  $designs
     * @param  array<string, Playlist>  $playlists
     */
    private function seedHistory(
        Workspace $workspace,
        User $owner,
        array $screens,
        array $designs,
        array $playlists,
    ): void {
        mt_srand(4242);

        if ($this->freshDemo) {
            $this->purgeDemoHistory($workspace, $screens);
        }

        $devices = $this->seedScreenDevices($screens);
        $deployments = $this->seedHistoricalDeployments($workspace, $owner, $screens, $designs, $playlists, $devices);
        DemoHeartbeatSimulator::seedRecent($screens, $devices);
        $this->seedDailyStats($workspace, $screens);
        $this->seedPlaybackEvents($workspace, $screens, $devices, $designs, $deployments, $playlists);

        mt_srand();
    }

    /**
     * @param  list<Screen>  $screens
     */
    private function purgeDemoHistory(Workspace $workspace, array $screens): void
    {
        $screenIds = collect($screens)->pluck('id')->filter()->all();

        PlaybackEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('meta->seed', 'demo-workspace')
                    ->orWhere('idempotency_key', 'like', 'demo-%');
            })
            ->delete();

        if ($screenIds !== []) {
            ScreenDailyStat::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('screen_id', $screenIds)
                ->delete();

            Deployment::query()
                ->where('workspace_id', $workspace->id)
                ->whereIn('screen_id', $screenIds)
                ->delete();

            ScreenDevice::query()
                ->whereIn('screen_id', $screenIds)
                ->where('platform_meta->seed', 'demo-workspace')
                ->delete();
        }
    }

    /**
     * @param  list<Screen>  $screens
     * @return array<int, ScreenDevice>
     */
    private function seedScreenDevices(array $screens): array
    {
        $devices = [];
        $now = now();

        foreach ($screens as $index => $screen) {
            $slug = Str::slug($screen->name);
            $hardwareId = 'demo-device-'.$slug;
            $token = 'demo-token-'.$slug.'-4242';

            // Mix of online / offline / attention-friendly last_seen values.
            $lastSeen = match ($index) {
                0, 1 => $now->copy()->subSeconds(20),
                2 => $now->copy()->subMinutes(12),
                3 => $now->copy()->subHours(3),
                default => $now->copy()->subDays(2),
            };

            $playback = match ($index) {
                4 => PlayerPlaybackState::Error->value,
                default => PlayerPlaybackState::Rendering->value,
            };

            $device = ScreenDevice::query()->updateOrCreate(
                ['device_identifier' => $hardwareId],
                [
                    'screen_id' => $screen->id,
                    'device_token_hash' => ScreenDevice::hashToken($token),
                    'device_name' => 'Demo '.$screen->name,
                    'platform_meta' => [
                        'seed' => 'demo-workspace',
                        'hardware_id' => $hardwareId,
                        'demo' => true,
                    ],
                    'paired_at' => $now->copy()->subDays(80)->addHours($index),
                    'revoked_at' => null,
                    'last_seen_at' => $lastSeen,
                    'player_version' => 'demo-1.0.0',
                    'viewport_width' => 1920,
                    'viewport_height' => 1080,
                    'reported_orientation' => 'landscape',
                    'playback_state' => $playback,
                ],
            );

            $devices[$screen->id] = $device;
        }

        return $devices;
    }

    /**
     * @param  list<Screen>  $screens
     * @param  array<string, ScreenDesign>  $designs
     * @param  array<string, Playlist>  $playlists
     * @param  array<int, ScreenDevice>  $devices
     * @return list<Deployment>
     */
    private function seedHistoricalDeployments(
        Workspace $workspace,
        User $owner,
        array $screens,
        array $designs,
        array $playlists,
        array $devices,
    ): array {
        $designList = array_values(array_filter(
            $designs,
            fn (ScreenDesign $d) => $d->published_version_id !== null,
        ));
        $playlistList = array_values(array_filter(
            $playlists,
            fn (Playlist $p) => $p->published_version_id !== null,
        ));

        if ($designList === [] || $screens === []) {
            return [];
        }

        $created = [];
        $waves = [
            ['days_ago' => 75, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 55, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 35, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 18, 'status' => DeploymentStatus::Failed],
            ['days_ago' => 12, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 4, 'status' => DeploymentStatus::Active],
            ['days_ago' => 2, 'status' => DeploymentStatus::Failed],
            ['days_ago' => 1, 'status' => DeploymentStatus::Active],
        ];

        foreach ($screens as $screenIndex => $screen) {
            $activeKept = false;

            foreach ($waves as $waveIndex => $wave) {
                // Skip the kitchen screen's latest active so it stays Attention (no content).
                if ($screenIndex === 4 && $wave['status'] === DeploymentStatus::Active) {
                    continue;
                }

                $deployedAt = now()->subDays($wave['days_ago'])->startOfDay()->setTime(9 + ($waveIndex % 6), 15, $screenIndex);

                $usePlaylist = $playlistList !== [] && ($waveIndex % 3 === 1);
                if ($usePlaylist) {
                    $playlist = $playlistList[$screenIndex % count($playlistList)];
                    $attrs = [
                        'content_type' => DeploymentContentType::Playlist,
                        'screen_design_id' => null,
                        'screen_design_version_id' => null,
                        'playlist_id' => $playlist->id,
                        'playlist_version_id' => $playlist->published_version_id,
                    ];
                } else {
                    $design = $designList[$screenIndex % count($designList)];
                    $attrs = [
                        'content_type' => DeploymentContentType::ScreenDesign,
                        'screen_design_id' => $design->id,
                        'screen_design_version_id' => $design->published_version_id,
                        'playlist_id' => null,
                        'playlist_version_id' => null,
                    ];
                }

                $status = $wave['status'];
                if ($status === DeploymentStatus::Active && $activeKept) {
                    $status = DeploymentStatus::Superseded;
                }
                if ($status === DeploymentStatus::Active) {
                    $activeKept = true;
                }

                $deployment = Deployment::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'screen_id' => $screen->id,
                        'deployed_at' => $deployedAt->toDateTimeString(),
                    ],
                    [
                        ...$attrs,
                        'status' => $status,
                        'deployed_by' => $owner->id,
                        'superseded_at' => $status === DeploymentStatus::Superseded
                            ? $deployedAt->copy()->addDays(3)->toDateTimeString()
                            : null,
                    ],
                );

                $created[] = $deployment;

                if ($status === DeploymentStatus::Active && isset($devices[$screen->id])) {
                    $devices[$screen->id]->forceFill([
                        'reported_deployment_id' => $deployment->id,
                    ])->save();
                }
            }
        }

        return $created;
    }

    /**
     * @param  list<Screen>  $screens
     */
    private function seedDailyStats(Workspace $workspace, array $screens): void
    {
        $daySeconds = 86400;
        $historyDays = 80;

        foreach ($screens as $screenIndex => $screen) {
            for ($offset = $historyDays - 1; $offset >= 0; $offset--) {
                $date = Carbon::now()->subDays($offset)->startOfDay();

                // Most screens ~20–24h online; screen index 3 is worse; occasional dips.
                $baseOnline = match ($screenIndex) {
                    3 => 14 * 3600 + mt_rand(0, 4 * 3600),
                    4 => 18 * 3600 + mt_rand(0, 3 * 3600),
                    default => 20 * 3600 + mt_rand(0, 4 * 3600),
                };

                // Occasional outage days (~8% chance), worse screen more often.
                $dipChance = $screenIndex === 3 ? 18 : 8;
                if (mt_rand(1, 100) <= $dipChance) {
                    $baseOnline = mt_rand(2 * 3600, 10 * 3600);
                }

                $online = min($daySeconds, max(0, $baseOnline));
                $offline = $daySeconds - $online;
                $playCount = (int) max(0, round(($online / $daySeconds) * mt_rand(18, 42)));
                $playbackSeconds = (int) min($online, $playCount * mt_rand(20, 45));
                $errors = $screenIndex === 4 && mt_rand(1, 100) <= 25 ? mt_rand(1, 3) : (mt_rand(1, 100) <= 5 ? 1 : 0);

                ScreenDailyStat::query()->updateOrCreate(
                    [
                        'screen_id' => $screen->id,
                        'stat_date' => $date->toDateString(),
                    ],
                    [
                        'workspace_id' => $workspace->id,
                        'online_seconds' => $online,
                        'offline_seconds' => $offline,
                        'playback_seconds' => $playbackSeconds,
                        'content_play_count' => $playCount,
                        'error_count' => $errors,
                        'heartbeat_count' => (int) round($online / 45),
                    ],
                );
            }
        }
    }

    /**
     * @param  list<Screen>  $screens
     * @param  array<int, ScreenDevice>  $devices
     * @param  array<string, ScreenDesign>  $designs
     * @param  list<Deployment>  $deployments
     * @param  array<string, Playlist>  $playlists
     */
    private function seedPlaybackEvents(
        Workspace $workspace,
        array $screens,
        array $devices,
        array $designs,
        array $deployments,
        array $playlists,
    ): void {
        $designVersions = collect($designs)
            ->map(fn (ScreenDesign $d) => $d->published_version_id)
            ->filter()
            ->values()
            ->all();

        $playlistVersions = collect($playlists)
            ->map(fn (Playlist $p) => $p->published_version_id)
            ->filter()
            ->values()
            ->all();

        if ($designVersions === [] || $screens === []) {
            return;
        }

        $deploymentsByScreen = collect($deployments)->groupBy('screen_id');
        $eventCount = 0;
        $target = 320;

        foreach ($screens as $screenIndex => $screen) {
            $device = $devices[$screen->id] ?? null;
            $screenDeployments = $deploymentsByScreen->get($screen->id, collect());

            // ~ every 3–4 days across 80 days → a few hundred events total.
            for ($day = 78; $day >= 0; $day -= 3 + ($screenIndex % 2)) {
                if ($eventCount >= $target) {
                    break 2;
                }

                $occurred = Carbon::now()->subDays($day)->setTime(10 + ($screenIndex % 5), mt_rand(0, 50));
                $versionId = $designVersions[$eventCount % count($designVersions)];
                $playlistVersionId = $playlistVersions !== [] && $eventCount % 4 === 0
                    ? $playlistVersions[$eventCount % count($playlistVersions)]
                    : null;
                $deployment = $screenDeployments->first();

                $startKey = sprintf('demo-%d-%s-start', $screen->id, $occurred->format('YmdHi'));
                PlaybackEvent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'idempotency_key' => $startKey,
                    ],
                    [
                        'screen_id' => $screen->id,
                        'screen_device_id' => $device?->id,
                        'type' => $playlistVersionId
                            ? PlaybackEventType::PlaylistItemStarted
                            : PlaybackEventType::ContentStarted,
                        'occurred_at' => $occurred,
                        'deployment_id' => $deployment?->id,
                        'screen_design_version_id' => $versionId,
                        'playlist_version_id' => $playlistVersionId,
                        'duration_seconds' => null,
                        'meta' => ['seed' => 'demo-workspace'],
                    ],
                );
                $eventCount++;

                $endAt = $occurred->copy()->addSeconds(mt_rand(25, 55));
                $endKey = sprintf('demo-%d-%s-end', $screen->id, $occurred->format('YmdHi'));
                PlaybackEvent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'idempotency_key' => $endKey,
                    ],
                    [
                        'screen_id' => $screen->id,
                        'screen_device_id' => $device?->id,
                        'type' => PlaybackEventType::ContentEnded,
                        'occurred_at' => $endAt,
                        'deployment_id' => $deployment?->id,
                        'screen_design_version_id' => $versionId,
                        'playlist_version_id' => $playlistVersionId,
                        'duration_seconds' => $endAt->diffInSeconds($occurred),
                        'meta' => ['seed' => 'demo-workspace'],
                    ],
                );
                $eventCount++;

                if ($screenIndex === 4 && $day % 12 === 0) {
                    $errKey = sprintf('demo-%d-%s-err', $screen->id, $occurred->format('YmdHi'));
                    PlaybackEvent::query()->updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'idempotency_key' => $errKey,
                        ],
                        [
                            'screen_id' => $screen->id,
                            'screen_device_id' => $device?->id,
                            'type' => PlaybackEventType::PlayerError,
                            'occurred_at' => $occurred->copy()->addMinutes(2),
                            'deployment_id' => $deployment?->id,
                            'screen_design_version_id' => $versionId,
                            'error_code' => 'demo_playback_glitch',
                            'meta' => ['seed' => 'demo-workspace'],
                        ],
                    );
                    $eventCount++;
                }
            }
        }
    }
}
