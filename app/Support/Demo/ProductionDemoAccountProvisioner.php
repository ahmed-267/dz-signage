<?php

namespace App\Support\Demo;

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
use App\Models\ScreenHeartbeat;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceBillingOverride;
use App\Models\WorkspaceMember;
use App\Support\Billing\BillingPlanCatalog;
use App\Support\MediaStorage;
use App\Support\Playlists\PlaylistDefaults;
use App\Support\Rendering\StarterTemplateCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Idempotent, scoped provisioner for the production demo Business.
 * Touches only the designated demo user + workspace_slug Business.
 */
final class ProductionDemoAccountProvisioner
{
    private const ANALYTICS_DAYS = 90;

    /** @var callable(string, string): void */
    private $log;

    /**
     * @return array{
     *     email: string,
     *     business: string,
     *     locations: int,
     *     tvs: int,
     *     screens: int,
     *     playlists: int,
     *     schedules: int,
     *     analytics_days: int
     * }
     */
    public function provision(string $email, string $password, callable $log): array
    {
        $this->log = $log;

        return DB::transaction(function () use ($email, $password) {
            $owner = $this->upsertOwner($email, $password);
            $workspace = $this->upsertWorkspace($owner);

            $this->seedTeam($workspace, $owner);
            $this->seedBillingOverride($workspace);
            $this->cleanJunkScreenDesigns($workspace);

            ($this->log)('info', 'Seeding demo Media…');
            $media = $this->seedMedia($workspace, $owner);

            ($this->log)('info', 'Seeding Brand Kit…');
            $this->seedBrandKit($workspace, $media['logo'] ?? null);

            ($this->log)('info', 'Seeding Locations…');
            $locations = $this->seedLocations($workspace, $owner);

            ($this->log)('info', 'Seeding Screens…');
            $designs = $this->seedDesigns($workspace, $owner, $media);

            ($this->log)('info', 'Seeding Playlists…');
            $playlists = $this->seedPlaylists($workspace, $owner, $designs);

            ($this->log)('info', 'Seeding Paired TVs…');
            $tvs = $this->seedTvs($workspace, $owner, $locations);

            ($this->log)('info', 'Seeding Schedules…');
            $schedules = $this->seedSchedules($workspace, $owner, $playlists);
            $this->attachSchedulesToTvs($schedules, $tvs);

            ($this->log)('info', 'Seeding publishing history + analytics…');
            $this->seedHistory($workspace, $owner, $tvs, $designs, $playlists);

            return [
                'email' => $email,
                'business' => $workspace->name,
                'locations' => count($locations),
                'tvs' => count($tvs),
                'screens' => count($designs),
                'playlists' => count($playlists),
                'schedules' => count($schedules),
                'analytics_days' => self::ANALYTICS_DAYS,
            ];
        });
    }

    /**
     * Refresh presence for the production demo Business only.
     * Safe to schedule — no-ops when the demo workspace is missing.
     */
    public static function refreshTelemetry(): int
    {
        $workspace = ProductionDemoAccount::findWorkspace();
        if ($workspace === null) {
            return 0;
        }

        $seedTag = ProductionDemoAccount::seedTag();
        $tvs = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('id')
            ->get()
            ->values()
            ->all();

        if ($tvs === []) {
            return 0;
        }

        $devices = [];
        $now = now();

        foreach ($tvs as $index => $tv) {
            $device = ScreenDevice::query()
                ->where('screen_id', $tv->id)
                ->where('platform_meta->seed', $seedTag)
                ->whereNull('revoked_at')
                ->first();

            if ($device === null) {
                continue;
            }

            // Window Display (index 2) stays offline; others stay fresh/online.
            $lastSeen = $index === 2
                ? $now->copy()->subHours(3)
                : $now->copy()->subSeconds(25);

            $device->forceFill([
                'last_seen_at' => $lastSeen,
                'playback_state' => $index === 2
                    ? PlayerPlaybackState::Inactive->value
                    : PlayerPlaybackState::Rendering->value,
            ])->save();

            $devices[$tv->id] = $device;
        }

        DemoHeartbeatSimulator::seedRecent(
            $tvs,
            $devices,
            allowInProduction: true,
            seedTag: $seedTag,
            profiles: DemoHeartbeatSimulator::productionDemoProfiles(),
        );

        return count($devices);
    }

    private function upsertOwner(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = User::query()->create([
                'name' => 'Demo Owner',
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'name' => 'Demo Owner',
                'password' => Hash::make($password),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        return $user;
    }

    private function upsertWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::query()->updateOrCreate(
            ['slug' => ProductionDemoAccount::workspaceSlug()],
            [
                'name' => ProductionDemoAccount::workspaceName(),
                'industry' => WorkspaceIndustry::Cafe,
                'country' => 'United Kingdom',
                'timezone' => 'Europe/London',
            ],
        );

        WorkspaceMember::query()->updateOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
            ['role' => WorkspaceRole::Owner],
        );

        $owner->forceFill(['current_workspace_id' => $workspace->id])->save();

        return $workspace;
    }

    private function seedTeam(Workspace $workspace, User $owner): void
    {
        $members = [
            ['email' => 'aisha.khan.demo@rmsignage.com', 'name' => 'Aisha Khan', 'role' => WorkspaceRole::Admin],
            ['email' => 'sam.chen.demo@rmsignage.com', 'name' => 'Sam Chen', 'role' => WorkspaceRole::Designer],
            ['email' => 'ibrahim.hassan.demo@rmsignage.com', 'name' => 'Ibrahim Hassan', 'role' => WorkspaceRole::ContentManager],
        ];

        $keepUserIds = [$owner->id];

        foreach ($members as $spec) {
            $user = User::query()->where('email', $spec['email'])->first();
            if ($user === null) {
                $user = User::query()->create([
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'password' => Hash::make(Str::password(32)),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill(['name' => $spec['name']])->save();
            }

            WorkspaceMember::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $user->id],
                ['role' => $spec['role']],
            );

            $keepUserIds[] = $user->id;
        }

        // Scoped: remove only extra memberships on this demo Business.
        WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('user_id', $keepUserIds)
            ->delete();
    }

    private function seedBillingOverride(Workspace $workspace): void
    {
        $business = BillingPlanCatalog::plan('business') ?? [];

        WorkspaceBillingOverride::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'screen_limit' => (int) ($business['screen_limit'] ?? 20),
                'storage_gb' => (int) ($business['storage_gb'] ?? 500),
                'team_limit' => (int) ($business['team_limit'] ?? 15),
                'features' => array_values($business['features'] ?? []),
            ],
        );
    }

    private function cleanJunkScreenDesigns(Workspace $workspace): void
    {
        $pruner = app(PruneAbandonedScreenDesigns::class);
        $keep = array_keys($this->designDefinitions());

        ScreenDesign::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keep)
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_number')])
            ->get()
            ->each(fn (ScreenDesign $design) => $pruner->deleteCascade($design));
    }

    /**
     * @return array<string, array{template: string, media: string, logo: string|null}>
     */
    private function designDefinitions(): array
    {
        return [
            'Morning Coffee' => ['template' => 'cafe-promotion', 'media' => 'iced-latte', 'logo' => 'logo-north-bean'],
            'Breakfast Offer' => ['template' => 'cafe-promotion', 'media' => 'pastry-display', 'logo' => 'logo-north-bean'],
            'Lunch Menu' => ['template' => 'restaurant-digital-menu', 'media' => 'plated-lunch-special', 'logo' => 'logo-north-bean'],
            'Afternoon Treat' => ['template' => 'general-promotion-portrait', 'media' => 'latte-art-cups', 'logo' => 'logo-north-bean'],
            'Weekend Promotion' => ['template' => 'retail-sale-promotion', 'media' => 'retail-fashion-floor', 'logo' => 'logo-retail'],
            'Opening Hours' => ['template' => 'information-board', 'media' => 'cafe-interior', 'logo' => 'logo-north-bean'],
            'Corporate Welcome' => ['template' => 'corporate-welcome', 'media' => 'corporate-office', 'logo' => 'logo-corporate'],
            'Hotel Welcome' => ['template' => 'hotel-welcome', 'media' => 'hotel-lobby', 'logo' => 'logo-corporate'],
            'Event Announcement' => ['template' => 'event-welcome', 'media' => 'conference-stage', 'logo' => 'logo-corporate'],
            'Seasonal Promotion' => ['template' => 'general-promotion-landscape', 'media' => 'mountain-landscape', 'logo' => 'logo-north-bean'],
        ];
    }

    /**
     * @return array<string, MediaAsset>
     */
    private function seedMedia(Workspace $workspace, User $owner): array
    {
        $seedTag = ProductionDemoAccount::seedTag();
        $bySlug = [];

        MediaAsset::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query): void {
                $query->where('name', 'like', 'E2E %')
                    ->orWhere('name', 'like', 'Design Img%')
                    ->orWhere('name', 'like', 'Untitled%');
            })
            ->get()
            ->each(fn (MediaAsset $asset) => $this->deleteMediaAsset($asset));

        foreach (DemoPhotoCatalog::all() as $photo) {
            $path = DemoPhotoCatalog::path($photo['file']);
            if (! is_file($path)) {
                ($this->log)('warn', "Missing demo photo {$photo['file']} — skip.");

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

        $aliases = [
            'cafe-latte-promo' => 'iced-latte',
            'restaurant-plate' => 'plated-lunch-special',
            'retail-fashion' => 'retail-fashion-floor',
            'corporate-welcome' => 'corporate-office',
            'conference-event' => 'conference-stage',
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
                $bySlug[$spec['slug']] = $this->storeBinaryMedia(
                    $workspace,
                    $owner,
                    $spec['slug'],
                    $spec['name'],
                    MediaType::Logo,
                    (string) file_get_contents($svgPath),
                    'svg',
                    'image/svg+xml',
                    512,
                    160,
                );
            } else {
                $bySlug[$spec['slug']] = $this->storeBinaryMedia(
                    $workspace,
                    $owner,
                    $spec['slug'],
                    $spec['name'],
                    MediaType::Logo,
                    DemoImageFactory::logo($spec['fallback_mark'], $spec['bg'], $spec['fg']),
                    'png',
                    'image/png',
                    512,
                    160,
                );
            }
        }

        foreach ([
            ['slug' => 'doc-summer-menu', 'name' => 'Summer Menu.pdf', 'body' => "North & Bean Café\nSummer Menu\n\nIced Latte — £3.40\nSeasonal pastry — £2.90\n"],
            ['slug' => 'doc-event-programme', 'name' => 'Event Programme.pdf', 'body' => "Product Summit Programme\n09:00 Registration\n10:00 Keynote\n"],
            ['slug' => 'doc-visitor-info', 'name' => 'Visitor Information.pdf', 'body' => "Visitor Information\nPlease sign in at reception.\n"],
        ] as $spec) {
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

        foreach ([
            ['slug' => 'text-welcome', 'name' => 'Welcome Message', 'text' => 'Welcome to North & Bean. Please order at the counter.'],
            ['slug' => 'text-weekend-sale', 'name' => 'Weekend Sale Announcement', 'text' => 'Weekend sale — 20% off pastries Saturday & Sunday.'],
            ['slug' => 'text-opening-hours', 'name' => 'Opening Hours Notice', 'text' => 'Open Mon–Fri 07:00–18:00 · Sat–Sun 08:00–16:00'],
        ] as $spec) {
            $bySlug[$spec['slug']] = MediaAsset::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $spec['name']],
                [
                    'type' => MediaType::Text,
                    'text_content' => $spec['text'],
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'metadata' => ['seed' => $seedTag, 'slug' => $spec['slug']],
                ],
            );
        }

        foreach ([
            ['slug' => 'link-website', 'name' => 'North & Bean Website', 'url' => 'https://www.example.com'],
            ['slug' => 'link-menu', 'name' => 'Online Menu', 'url' => 'https://www.example.com/menu'],
            ['slug' => 'link-events', 'name' => 'Events Calendar', 'url' => 'https://www.example.com/events'],
        ] as $spec) {
            $bySlug[$spec['slug']] = MediaAsset::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $spec['name']],
                [
                    'type' => MediaType::Link,
                    'url' => $spec['url'],
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                    'metadata' => ['seed' => $seedTag, 'slug' => $spec['slug']],
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
                'seed' => ProductionDemoAccount::seedTag(),
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

    private function deleteMediaAsset(MediaAsset $asset): void
    {
        if (filled($asset->storage_path) && filled($asset->storage_disk)) {
            try {
                Storage::disk($asset->storage_disk)->delete($asset->storage_path);
            } catch (\Throwable) {
                // ignore storage cleanup failures
            }
        }
        $asset->delete();
    }

    private function seedBrandKit(Workspace $workspace, ?MediaAsset $logo): void
    {
        BrandKit::query()->updateOrCreate(
            ['workspace_id' => $workspace->id],
            [
                'name' => 'North & Bean Café',
                'tagline' => 'Coffee, brunch & good company',
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
        $defs = [
            ['slug' => 'nottingham-cafe', 'name' => 'Nottingham Café', 'city' => 'Nottingham', 'address' => '42 Demo Market Row'],
            ['slug' => 'birmingham-store', 'name' => 'Birmingham Store', 'city' => 'Birmingham', 'address' => '18 Example High Street'],
            ['slug' => 'london-office', 'name' => 'London Office', 'city' => 'London', 'address' => '7 Sample Quay Walk'],
        ];

        $keepNames = array_column($defs, 'name');
        Location::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keepNames)
            ->delete();

        $out = [];
        foreach ($defs as $def) {
            $out[$def['slug']] = Location::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $def['name']],
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
        $catalog = collect(StarterTemplateCatalog::definitions())->keyBy('slug');
        $designs = [];

        foreach ($this->designDefinitions() as $name => $meta) {
            $def = $catalog->get($meta['template']);
            if ($def === null) {
                continue;
            }

            $template = Template::query()->where('slug', $meta['template'])->first();
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
                ['workspace_id' => $workspace->id, 'name' => $name],
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
                ['screen_design_id' => $design->id, 'version_number' => 1],
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

            $designs[$name] = $design->fresh(['publishedVersion']) ?? $design;
        }

        return $designs;
    }

    /**
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
            'Café Daily Rotation' => ['Morning Coffee', 'Breakfast Offer', 'Lunch Menu', 'Afternoon Treat'],
            'Weekend Promotions' => ['Weekend Promotion', 'Seasonal Promotion', 'Morning Coffee'],
            'Retail Promotions' => ['Weekend Promotion', 'Seasonal Promotion'],
            'Corporate Lobby' => ['Corporate Welcome', 'Opening Hours', 'Event Announcement'],
            'Event Rotation' => ['Event Announcement', 'Hotel Welcome', 'Corporate Welcome'],
        ];

        $keep = array_keys($defs);
        Playlist::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keep)
            ->get()
            ->each(function (Playlist $playlist): void {
                PlaylistItem::query()
                    ->whereIn('playlist_version_id', $playlist->versions()->pluck('id'))
                    ->delete();
                $playlist->versions()->delete();
                $playlist->delete();
            });

        $playlists = [];

        foreach ($defs as $name => $screenNames) {
            $items = [];
            foreach ($screenNames as $screenName) {
                if (isset($designs[$screenName]) && $designs[$screenName]->publishedVersion !== null) {
                    $items[] = $designs[$screenName];
                }
            }
            if ($items === []) {
                continue;
            }

            $playlist = Playlist::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $name],
                [
                    'orientation' => $items[0]->orientation,
                    'status' => PlaylistStatus::Published,
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );

            $version = PlaylistVersion::query()->updateOrCreate(
                ['playlist_id' => $playlist->id, 'version_number' => 1],
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
     * @param  array<string, Location>  $locations
     * @return list<Screen>
     */
    private function seedTvs(Workspace $workspace, User $owner, array $locations): array
    {
        $defs = [
            ['name' => 'Counter TV', 'location' => 'nottingham-cafe'],
            ['name' => 'Menu Board', 'location' => 'nottingham-cafe'],
            ['name' => 'Window Display', 'location' => 'birmingham-store'],
            ['name' => 'Reception TV', 'location' => 'london-office'],
            ['name' => 'Kitchen Display', 'location' => 'nottingham-cafe'],
        ];

        $keep = array_column($defs, 'name');
        Screen::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keep)
            ->get()
            ->each(function (Screen $screen): void {
                ScreenDevice::query()->where('screen_id', $screen->id)->delete();
                $screen->delete();
            });

        $tvs = [];
        foreach ($defs as $def) {
            $tvs[] = Screen::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $def['name']],
                [
                    'orientation' => 'landscape',
                    'operational_status' => ScreenOperationalStatus::Active,
                    'created_by' => $owner->id,
                    'location_id' => $locations[$def['location']]->id ?? null,
                ],
            );
        }

        return $tvs;
    }

    /**
     * @param  array<string, Playlist>  $playlists
     * @return list<Schedule>
     */
    private function seedSchedules(Workspace $workspace, User $owner, array $playlists): array
    {
        $cafe = $playlists['Café Daily Rotation'] ?? null;
        $weekend = $playlists['Weekend Promotions'] ?? null;
        $retail = $playlists['Retail Promotions'] ?? null;

        if ($cafe?->published_version_id === null) {
            return [];
        }

        $defs = [
            [
                'name' => 'Morning Menu',
                'playlist' => $cafe,
                'start' => '07:00:00',
                'end' => '11:00:00',
                'days' => [1, 2, 3, 4, 5],
                'priority' => 5,
            ],
            [
                'name' => 'Lunch Menu',
                'playlist' => $cafe,
                'start' => '11:00:00',
                'end' => '15:00:00',
                'days' => [1, 2, 3, 4, 5],
                'priority' => 5,
            ],
            [
                'name' => 'Afternoon Promotion',
                'playlist' => $retail ?? $cafe,
                'start' => '15:00:00',
                'end' => '18:00:00',
                'days' => [1, 2, 3, 4, 5],
                'priority' => 5,
            ],
            [
                'name' => 'Weekend Promotion',
                'playlist' => $weekend ?? $cafe,
                'start' => '09:00:00',
                'end' => '18:00:00',
                'days' => [6, 7],
                'priority' => 5,
            ],
            [
                'name' => 'Flash Weekend Boost',
                'playlist' => $weekend ?? $cafe,
                'start' => '10:00:00',
                'end' => '14:00:00',
                'days' => [6, 7],
                'priority' => 9,
            ],
        ];

        $keep = array_column($defs, 'name');
        Schedule::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotIn('name', $keep)
            ->get()
            ->each(function (Schedule $schedule): void {
                $schedule->screens()->detach();
                $schedule->delete();
            });

        $schedules = [];
        foreach ($defs as $def) {
            /** @var Playlist $playlist */
            $playlist = $def['playlist'];
            if ($playlist->published_version_id === null) {
                continue;
            }

            $schedules[] = Schedule::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $def['name']],
                [
                    'playlist_id' => $playlist->id,
                    'playlist_version_id' => $playlist->published_version_id,
                    'timezone' => 'Europe/London',
                    'start_time' => $def['start'],
                    'end_time' => $def['end'],
                    'days_of_week' => $def['days'],
                    'priority' => $def['priority'],
                    'status' => ScheduleStatus::Active,
                    'activated_at' => now(),
                    'created_by' => $owner->id,
                    'updated_by' => $owner->id,
                ],
            );
        }

        return $schedules;
    }

    /**
     * @param  list<Schedule>  $schedules
     * @param  list<Screen>  $tvs
     */
    private function attachSchedulesToTvs(array $schedules, array $tvs): void
    {
        $ids = collect($tvs)->pluck('id')->all();
        foreach ($schedules as $schedule) {
            $schedule->screens()->sync($ids);
        }
    }

    /**
     * @param  list<Screen>  $tvs
     * @param  array<string, ScreenDesign>  $designs
     * @param  array<string, Playlist>  $playlists
     */
    private function seedHistory(
        Workspace $workspace,
        User $owner,
        array $tvs,
        array $designs,
        array $playlists,
    ): void {
        mt_srand(904201);

        $this->purgeDemoHistory($workspace, $tvs);
        $devices = $this->seedScreenDevices($tvs);
        $deployments = $this->seedHistoricalDeployments($workspace, $owner, $tvs, $designs, $playlists, $devices);

        DemoHeartbeatSimulator::seedRecent(
            $tvs,
            $devices,
            allowInProduction: true,
            seedTag: ProductionDemoAccount::seedTag(),
            profiles: DemoHeartbeatSimulator::productionDemoProfiles(),
        );

        $this->seedDailyStats($workspace, $tvs);
        $this->seedPlaybackEvents($workspace, $tvs, $devices, $designs, $deployments, $playlists);

        mt_srand();
    }

    /**
     * @param  list<Screen>  $tvs
     */
    private function purgeDemoHistory(Workspace $workspace, array $tvs): void
    {
        $seedTag = ProductionDemoAccount::seedTag();
        $tvIds = collect($tvs)->pluck('id')->filter()->all();

        PlaybackEvent::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($query) use ($seedTag): void {
                $query->where('meta->seed', $seedTag)
                    ->orWhere('idempotency_key', 'like', 'rms-demo-%');
            })
            ->delete();

        if ($tvIds === []) {
            return;
        }

        ScreenDailyStat::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('screen_id', $tvIds)
            ->delete();

        Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('screen_id', $tvIds)
            ->delete();

        ScreenHeartbeat::query()
            ->whereIn('screen_id', $tvIds)
            ->where('metadata->seed', $seedTag)
            ->delete();

        ScreenDevice::query()
            ->whereIn('screen_id', $tvIds)
            ->where('platform_meta->seed', $seedTag)
            ->delete();
    }

    /**
     * Create paired demo devices WITHOUT issuing usable plaintext tokens.
     *
     * @param  list<Screen>  $tvs
     * @return array<int, ScreenDevice>
     */
    private function seedScreenDevices(array $tvs): array
    {
        $devices = [];
        $now = now();
        $seedTag = ProductionDemoAccount::seedTag();

        foreach ($tvs as $index => $tv) {
            $slug = Str::slug($tv->name);
            $hardwareId = 'rms-demo-device-'.$slug;

            // Hash of an ephemeral secret — plaintext is discarded immediately.
            $unusableHash = ScreenDevice::hashToken(bin2hex(random_bytes(32)));

            $lastSeen = match ($index) {
                2 => $now->copy()->subHours(3),
                default => $now->copy()->subSeconds(20 + $index),
            };

            $device = ScreenDevice::query()->updateOrCreate(
                ['device_identifier' => $hardwareId],
                [
                    'screen_id' => $tv->id,
                    'device_token_hash' => $unusableHash,
                    'device_name' => 'Demo '.$tv->name,
                    'platform_meta' => [
                        'seed' => $seedTag,
                        'hardware_id' => $hardwareId,
                        'demo' => true,
                        'credentials_issued' => false,
                    ],
                    'paired_at' => $now->copy()->subDays(self::ANALYTICS_DAYS)->addHours($index),
                    'revoked_at' => null,
                    'last_seen_at' => $lastSeen,
                    'player_version' => 'demo-1.0.0',
                    'viewport_width' => 1920,
                    'viewport_height' => 1080,
                    'reported_orientation' => 'landscape',
                    'playback_state' => $index === 2
                        ? PlayerPlaybackState::Inactive->value
                        : PlayerPlaybackState::Rendering->value,
                ],
            );

            $devices[$tv->id] = $device;
        }

        return $devices;
    }

    /**
     * @param  list<Screen>  $tvs
     * @param  array<string, ScreenDesign>  $designs
     * @param  array<string, Playlist>  $playlists
     * @param  array<int, ScreenDevice>  $devices
     * @return list<Deployment>
     */
    private function seedHistoricalDeployments(
        Workspace $workspace,
        User $owner,
        array $tvs,
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

        if ($designList === [] || $tvs === []) {
            return [];
        }

        $created = [];
        $waves = [
            ['days_ago' => 85, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 60, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 40, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 22, 'status' => DeploymentStatus::Failed],
            ['days_ago' => 14, 'status' => DeploymentStatus::Superseded],
            ['days_ago' => 5, 'status' => DeploymentStatus::Active],
            ['days_ago' => 2, 'status' => DeploymentStatus::Pending],
            ['days_ago' => 1, 'status' => DeploymentStatus::Active],
        ];

        foreach ($tvs as $tvIndex => $tv) {
            $activeKept = false;

            foreach ($waves as $waveIndex => $wave) {
                if ($tvIndex === 2 && $wave['status'] === DeploymentStatus::Pending) {
                    // Window Display keeps an older Active + no pending clutter.
                    continue;
                }

                $deployedAt = now()->subDays($wave['days_ago'])->startOfDay()->setTime(9 + ($waveIndex % 6), 15, $tvIndex);

                $usePlaylist = $playlistList !== [] && ($waveIndex % 3 === 1);
                if ($usePlaylist) {
                    $playlist = $playlistList[$tvIndex % count($playlistList)];
                    $attrs = [
                        'content_type' => DeploymentContentType::Playlist,
                        'screen_design_id' => null,
                        'screen_design_version_id' => null,
                        'playlist_id' => $playlist->id,
                        'playlist_version_id' => $playlist->published_version_id,
                    ];
                } else {
                    $design = $designList[$tvIndex % count($designList)];
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
                if ($status === DeploymentStatus::Pending && $activeKept) {
                    // One pending republish is fine; keep Active as current.
                }

                $deployment = Deployment::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'screen_id' => $tv->id,
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

                if ($status === DeploymentStatus::Active && isset($devices[$tv->id])) {
                    $devices[$tv->id]->forceFill([
                        'reported_deployment_id' => $deployment->id,
                    ])->save();
                }
            }
        }

        return $created;
    }

    /**
     * @param  list<Screen>  $tvs
     */
    private function seedDailyStats(Workspace $workspace, array $tvs): void
    {
        $daySeconds = 86400;

        foreach ($tvs as $tvIndex => $tv) {
            for ($offset = self::ANALYTICS_DAYS - 1; $offset >= 0; $offset--) {
                $date = Carbon::now()->subDays($offset)->startOfDay();
                $dow = (int) $date->dayOfWeekIso; // 1=Mon … 7=Sun
                $isWeekend = $dow >= 6;

                // Window Display (2) has weaker availability + short outage story.
                $baseOnline = match ($tvIndex) {
                    2 => 12 * 3600 + mt_rand(0, 5 * 3600),
                    4 => 19 * 3600 + mt_rand(0, 3 * 3600),
                    default => 21 * 3600 + mt_rand(0, 3 * 3600),
                };

                if ($tvIndex === 2 && $offset === 12) {
                    $baseOnline = mt_rand(1 * 3600, 4 * 3600);
                } elseif (mt_rand(1, 100) <= ($tvIndex === 2 ? 16 : 7)) {
                    $baseOnline = mt_rand(3 * 3600, 11 * 3600);
                }

                $online = min($daySeconds, max(0, $baseOnline));
                $offline = $daySeconds - $online;

                // Morning coffee bias + weekend promotion bias.
                $playMult = $isWeekend ? 1.25 : 1.0;
                if (! $isWeekend && $tvIndex <= 1) {
                    $playMult *= 1.15;
                }

                $playCount = (int) max(0, round(($online / $daySeconds) * mt_rand(18, 40) * $playMult));
                $playbackSeconds = (int) min($online, $playCount * mt_rand(22, 48));
                $errors = $tvIndex === 2 && mt_rand(1, 100) <= 20
                    ? mt_rand(1, 2)
                    : (mt_rand(1, 100) <= 4 ? 1 : 0);

                ScreenDailyStat::query()->updateOrCreate(
                    [
                        'screen_id' => $tv->id,
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
     * @param  list<Screen>  $tvs
     * @param  array<int, ScreenDevice>  $devices
     * @param  array<string, ScreenDesign>  $designs
     * @param  list<Deployment>  $deployments
     * @param  array<string, Playlist>  $playlists
     */
    private function seedPlaybackEvents(
        Workspace $workspace,
        array $tvs,
        array $devices,
        array $designs,
        array $deployments,
        array $playlists,
    ): void {
        $seedTag = ProductionDemoAccount::seedTag();
        $designByName = $designs;
        $morning = $designByName['Morning Coffee']->published_version_id ?? null;
        $weekend = $designByName['Weekend Promotion']->published_version_id ?? null;
        $allVersions = collect($designs)
            ->map(fn (ScreenDesign $d) => $d->published_version_id)
            ->filter()
            ->values()
            ->all();

        $playlistVersions = collect($playlists)
            ->map(fn (Playlist $p) => $p->published_version_id)
            ->filter()
            ->values()
            ->all();

        if ($allVersions === [] || $tvs === []) {
            return;
        }

        $deploymentsByScreen = collect($deployments)->groupBy('screen_id');
        $eventCount = 0;
        $target = 420;

        foreach ($tvs as $tvIndex => $tv) {
            $device = $devices[$tv->id] ?? null;
            $screenDeployments = $deploymentsByScreen->get($tv->id, collect());

            for ($day = self::ANALYTICS_DAYS - 2; $day >= 0; $day -= 2 + ($tvIndex % 2)) {
                if ($eventCount >= $target) {
                    break 2;
                }

                $date = Carbon::now()->subDays($day);
                $isWeekend = $date->isWeekend();
                $hour = $isWeekend ? 11 + ($tvIndex % 4) : (8 + ($eventCount % 4)); // mornings stronger weekdays
                $occurred = $date->copy()->setTime($hour, mt_rand(0, 50));

                $versionId = $allVersions[$eventCount % count($allVersions)];
                if (! $isWeekend && $morning !== null && $hour < 11 && mt_rand(1, 100) <= 70) {
                    $versionId = $morning;
                }
                if ($isWeekend && $weekend !== null && mt_rand(1, 100) <= 65) {
                    $versionId = $weekend;
                }

                $playlistVersionId = $playlistVersions !== [] && $eventCount % 5 === 0
                    ? $playlistVersions[$eventCount % count($playlistVersions)]
                    : null;
                $deployment = $screenDeployments->first();

                $startKey = sprintf('rms-demo-%d-%s-start', $tv->id, $occurred->format('YmdHi'));
                PlaybackEvent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'idempotency_key' => $startKey,
                    ],
                    [
                        'screen_id' => $tv->id,
                        'screen_device_id' => $device?->id,
                        'type' => $playlistVersionId
                            ? PlaybackEventType::PlaylistItemStarted
                            : PlaybackEventType::ContentStarted,
                        'occurred_at' => $occurred,
                        'deployment_id' => $deployment?->id,
                        'screen_design_version_id' => $versionId,
                        'playlist_version_id' => $playlistVersionId,
                        'duration_seconds' => null,
                        'meta' => ['seed' => $seedTag],
                    ],
                );
                $eventCount++;

                $endAt = $occurred->copy()->addSeconds(mt_rand(25, 55));
                $endKey = sprintf('rms-demo-%d-%s-end', $tv->id, $occurred->format('YmdHi'));
                PlaybackEvent::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'idempotency_key' => $endKey,
                    ],
                    [
                        'screen_id' => $tv->id,
                        'screen_device_id' => $device?->id,
                        'type' => PlaybackEventType::ContentEnded,
                        'occurred_at' => $endAt,
                        'deployment_id' => $deployment?->id,
                        'screen_design_version_id' => $versionId,
                        'playlist_version_id' => $playlistVersionId,
                        'duration_seconds' => $endAt->diffInSeconds($occurred),
                        'meta' => ['seed' => $seedTag],
                    ],
                );
                $eventCount++;

                if ($tvIndex === 2 && $day % 14 === 0) {
                    $errKey = sprintf('rms-demo-%d-%s-err', $tv->id, $occurred->format('YmdHi'));
                    PlaybackEvent::query()->updateOrCreate(
                        [
                            'workspace_id' => $workspace->id,
                            'idempotency_key' => $errKey,
                        ],
                        [
                            'screen_id' => $tv->id,
                            'screen_device_id' => $device?->id,
                            'type' => PlaybackEventType::PlayerError,
                            'occurred_at' => $occurred->copy()->addMinutes(2),
                            'deployment_id' => $deployment?->id,
                            'screen_design_version_id' => $versionId,
                            'error_code' => 'demo_playback_glitch',
                            'meta' => ['seed' => $seedTag],
                        ],
                    );
                    $eventCount++;
                }
            }
        }
    }
}
