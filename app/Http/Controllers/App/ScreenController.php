<?php

namespace App\Http\Controllers\App;

use App\Actions\Deployments\PublishDesignToScreens;
use App\Actions\Locations\AssignScreenToLocation;
use App\Actions\Pairing\ClaimPairingSession;
use App\Actions\Screens\RenameScreen;
use App\Actions\Screens\RevokeScreenDevice;
use App\Actions\Screens\SetScreenOperationalStatus;
use App\Enums\DeploymentStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\ScreenOperationalStatus;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Location;
use App\Models\PairingSession;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\ListPagination;
use App\Support\Locations\LocationAccess;
use App\Support\Schedules\ScheduleEvaluator;
use App\Support\Screens\ScreenContentResolver;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Screen::class);

        $q = trim((string) $request->input('q', ''));
        $filter = (string) $request->input('filter', 'all');
        $sort = ListPagination::sort(
            $request,
            ['name', 'seen', 'created', 'updated'],
            'updated',
            'desc',
        );
        $locationId = (int) $request->input('location', 0);

        $query = Screen::query()
            ->forWorkspace($workspace)
            ->with([
                'location:id,name,city',
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->with([
                        'screenDesign:id,name,orientation',
                        'screenDesignVersion:id,version_number',
                        'playlist:id,name,orientation',
                        'playlistVersion:id,playlist_id,version_number',
                    ])
                    ->latest('id'),
            ]);

        LocationAccess::scopeScreensForUser($query, $user, $workspace);

        if ($q !== '') {
            $query->where('name', 'ilike', '%'.$q.'%');
        }

        if ($locationId > 0) {
            $query->where('location_id', $locationId);
        }

        $screens = $query->get();

        $allMapped = $screens
            ->map(fn (Screen $screen) => $this->toListItem($screen))
            ->values();

        $mapped = $this->applyStateFilter($allMapped, $filter);
        $mapped = $this->applySort($mapped, $sort['column'], $sort['direction'], $screens);

        $publishedDesigns = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->where('status', ScreenDesignStatus::Published)
            ->whereNotNull('published_version_id')
            ->orderBy('name')
            ->get(['id', 'name', 'orientation']);

        $locationOptions = $this->locationOptions($user, $workspace);

        return Inertia::render('app/screens/index', [
            'screens' => $mapped->values(),
            'filters' => [
                'q' => $q,
                'filter' => $filter,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'location' => $locationId > 0 ? $locationId : null,
            ],
            'counts' => [
                'all' => $allMapped->count(),
                'online' => $allMapped->filter(fn ($s) => ($s['network_state'] ?? null) === 'online')->count(),
                'offline' => $allMapped->filter(fn ($s) => ($s['network_state'] ?? null) === 'offline')->count(),
                'active' => $allMapped->filter(fn ($s) => ($s['operational_status'] ?? null) === 'active')->count(),
                'inactive' => $allMapped->filter(fn ($s) => ($s['operational_status'] ?? null) === 'inactive')->count(),
                'connected' => $allMapped->filter(fn ($s) => ($s['pairing_state'] ?? null) === 'connected')->count(),
                'disconnected' => $allMapped->filter(fn ($s) => ($s['pairing_state'] ?? null) === 'disconnected')->count(),
            ],
            'published_designs' => $publishedDesigns->map(fn (ScreenDesign $design) => [
                'id' => $design->id,
                'name' => $design->name,
                'orientation' => $design->orientation->value,
            ])->values(),
            'locations' => $locationOptions,
            'require_location' => LocationAccess::isLocationManager($user, $workspace),
            'can_manage' => $user->can('create', Screen::class),
            'can_publish' => $user->can('create', Deployment::class),
            'licences' => $this->licenceSummary($user, $workspace),
            'workspace_name' => $workspace->name,
            'player_url' => url('/player'),
            'heartbeat_interval_seconds' => ScreenPresence::heartbeatIntervalSeconds(),
            'online_threshold_seconds' => ScreenPresence::onlineThresholdSeconds(),
        ]);
    }

    public function show(
        Request $request,
        Screen $screen,
        ScreenContentResolver $resolver,
        ScheduleEvaluator $evaluator,
    ): Response {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('view', $screen);

        $screen->load([
            'location:id,name,city,timezone',
            'devices' => fn ($q) => $q->orderByDesc('paired_at'),
            'deployments' => fn ($q) => $q
                ->with([
                    'screenDesign:id,name,orientation',
                    'screenDesignVersion:id,version_number',
                    'playlist:id,name,orientation',
                    'playlistVersion' => fn ($rel) => $rel
                        ->select('id', 'playlist_id', 'version_number')
                        ->withCount(['items' => fn ($items) => $items->where('is_active', true)]),
                ])
                ->latest('id')
                ->limit(8),
            'workspace:id,name,timezone',
        ]);

        $workspace = $request->user()->currentWorkspace;

        $publishedDesigns = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->where('status', ScreenDesignStatus::Published)
            ->whereNotNull('published_version_id')
            ->orderBy('name')
            ->get(['id', 'name', 'orientation']);

        $recentHeartbeats = ScreenHeartbeat::query()
            ->where('screen_id', $screen->id)
            ->orderByDesc('recorded_at')
            ->limit(12)
            ->get()
            ->map(fn (ScreenHeartbeat $hb) => [
                'id' => $hb->id,
                'recorded_at' => $hb->recorded_at->toIso8601String(),
                'playback_state' => $hb->playback_state,
                'error_code' => $hb->error_code,
                'deployment_id' => $hb->deployment_id,
                'viewport_width' => $hb->viewport_width,
                'viewport_height' => $hb->viewport_height,
                'orientation' => $hb->orientation,
                'player_version' => $hb->player_version,
            ])
            ->values();

        return Inertia::render('app/screens/show', [
            'screen' => $this->toDetail($screen, $resolver, $evaluator),
            'recent_heartbeats' => $recentHeartbeats,
            'published_designs' => $publishedDesigns->map(fn (ScreenDesign $design) => [
                'id' => $design->id,
                'name' => $design->name,
                'orientation' => $design->orientation->value,
            ])->values(),
            'locations' => $this->locationOptions($request->user(), $workspace),
            'can_manage' => $request->user()->can('update', $screen),
            'can_publish' => $request->user()->can('publish', $screen),
            'workspace_name' => $workspace?->name,
            'workspace_timezone' => $workspace?->timezone,
        ]);
    }

    public function pairShow(Request $request, string $publicId): Response
    {
        $this->authorize('pair', Screen::class);

        $session = PairingSession::query()
            ->where('public_id', $publicId)
            ->first();

        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('app/screens/pair', [
            'pairing' => [
                'public_id' => $publicId,
                'status' => $session === null
                    ? 'expired'
                    : ($session->isClaimed() ? 'claimed' : ($session->isExpired() ? 'expired' : 'pending')),
                'expires_at' => $session?->expires_at?->toIso8601String(),
            ],
            'locations' => $workspace
                ? $this->locationOptions($request->user(), $workspace)
                : [],
            'require_location' => $workspace !== null
                && LocationAccess::isLocationManager($request->user(), $workspace),
            'workspace_name' => $workspace?->name,
            'can_manage' => $request->user()->can('pair', Screen::class),
        ]);
    }

    public function storePair(Request $request, ClaimPairingSession $action): RedirectResponse
    {
        $this->authorize('pair', Screen::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = $this->validatedLocationId($request->user(), $workspace, $data['location_id'] ?? null);

        $result = $action->handle(
            $request->user(),
            $workspace,
            $data['name'],
            $data['code'],
            null,
            $data['orientation'] ?? null,
            $locationId,
        );

        return redirect()
            ->route('app.screens.show', $result['screen'])
            ->with('success', 'Screen paired.');
    }

    public function storePairByPublicId(
        Request $request,
        string $publicId,
        ClaimPairingSession $action,
    ): RedirectResponse {
        $this->authorize('pair', Screen::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'orientation' => ['nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = $this->validatedLocationId($request->user(), $workspace, $data['location_id'] ?? null);

        $result = $action->handle(
            $request->user(),
            $workspace,
            $data['name'],
            null,
            $publicId,
            $data['orientation'] ?? null,
            $locationId,
        );

        return redirect()
            ->route('app.screens.show', $result['screen'])
            ->with('success', 'Screen paired.');
    }

    public function rename(Request $request, Screen $screen, RenameScreen $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('update', $screen);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $action->handle($request->user(), $screen, $data['name']);

        return redirect()->back()->with('success', 'Screen renamed.');
    }

    public function updateLocation(
        Request $request,
        Screen $screen,
        AssignScreenToLocation $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('update', $screen);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
        if ($locationId === 0) {
            $locationId = null;
        }

        if (LocationAccess::isLocationManager($request->user(), $workspace) && $locationId === null) {
            return redirect()->back()->withErrors([
                'location_id' => 'Location Managers must keep screens at an assigned location.',
            ]);
        }

        if (! LocationAccess::canAssignLocation($request->user(), $workspace, $locationId)) {
            return redirect()->back()->withErrors([
                'location_id' => 'Invalid location.',
            ]);
        }

        $location = $locationId === null
            ? null
            : Location::query()->forWorkspace($workspace)->whereKey($locationId)->firstOrFail();

        $action->handle($request->user(), $screen, $location);

        return redirect()->back()->with('success', 'Screen location updated.');
    }

    public function setStatus(
        Request $request,
        Screen $screen,
        SetScreenOperationalStatus $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('update', $screen);

        $data = $request->validate([
            'operational_status' => ['required', Rule::enum(ScreenOperationalStatus::class)],
        ]);

        $action->handle(
            $request->user(),
            $screen,
            ScreenOperationalStatus::from($data['operational_status']),
        );

        return redirect()->back()->with('success', 'Screen status updated.');
    }

    public function bulkStatus(Request $request, SetScreenOperationalStatus $action): RedirectResponse
    {
        $this->authorize('create', Screen::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer'],
            'operational_status' => ['required', Rule::enum(ScreenOperationalStatus::class)],
        ]);

        $status = ScreenOperationalStatus::from($data['operational_status']);
        $screensQuery = Screen::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $data['screen_ids']);
        LocationAccess::scopeScreensForUser($screensQuery, $request->user(), $workspace);
        $screens = $screensQuery->get();

        foreach ($screens as $screen) {
            $this->authorize('update', $screen);
            $action->handle($request->user(), $screen, $status);
        }

        return redirect()->back()->with('success', 'Screens updated.');
    }

    public function bulkPublish(Request $request, PublishDesignToScreens $action): RedirectResponse
    {
        $this->authorize('create', Deployment::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer'],
            'screen_design_id' => ['required', 'integer'],
        ]);

        $design = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->whereKey($data['screen_design_id'])
            ->firstOrFail();

        $action->handle($request->user(), $workspace, $design, $data['screen_ids']);

        return redirect()->back()->with('success', 'Design published to selected screens.');
    }

    public function destroy(Request $request, Screen $screen, RevokeScreenDevice $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('revoke', $screen);

        $device = $screen->activeDevice();
        if ($device !== null) {
            $action->handle($request->user(), $device);
        }

        return redirect()
            ->route('app.screens')
            ->with('success', 'Screen unpaired.');
    }

    public function publishContent(
        Request $request,
        Screen $screen,
        PublishDesignToScreens $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screen);
        $this->authorize('publish', $screen);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'screen_design_id' => ['required', 'integer'],
        ]);

        $design = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->whereKey($data['screen_design_id'])
            ->firstOrFail();

        $this->authorize('create', Deployment::class);

        $action->handle($request->user(), $workspace, $design, [$screen->id]);

        return redirect()->back()->with('success', 'Design published to screen.');
    }

    /**
     * Screen licence usage for the Screens header. Only rendered for roles that
     * can see billing, and only once licences actually matter to the Workspace.
     * BillingEntitlement stays the single source of the counts.
     *
     * @return array{used: int, licensed: int, remaining: int, manage_url: string}|null
     */
    private function licenceSummary(User $user, Workspace $workspace): ?array
    {
        if (! ($user->roleIn($workspace)?->canViewBilling() ?? false)) {
            return null;
        }

        $summary = BillingEntitlement::summary($workspace);

        if (! $summary['has_subscription'] && ! $summary['enforce']) {
            return null;
        }

        return [
            'used' => $summary['used'],
            'licensed' => $summary['licensed'],
            'remaining' => $summary['remaining'],
            'manage_url' => route('app.settings.tab', ['tab' => 'billing']),
        ];
    }

    private function ensureWorkspace(Request $request, Screen $screen): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $screen->workspace_id === (int) $workspace->id,
            404,
        );
    }

    /**
     * @return list<array{id: int, name: string, city: string|null}>
     */
    private function locationOptions(User $user, Workspace $workspace): array
    {
        $query = Location::query()
            ->forWorkspace($workspace)
            ->active()
            ->orderBy('name');

        LocationAccess::scopeLocationsForUser($query, $user, $workspace);

        $options = [];
        foreach ($query->get(['id', 'name', 'city']) as $location) {
            $options[] = [
                'id' => $location->id,
                'name' => $location->name,
                'city' => $location->city,
            ];
        }

        return $options;
    }

    private function validatedLocationId(User $user, Workspace $workspace, mixed $raw): ?int
    {
        $locationId = $raw === null || $raw === '' ? null : (int) $raw;
        if ($locationId === 0) {
            $locationId = null;
        }

        if (LocationAccess::isLocationManager($user, $workspace) && $locationId === null) {
            throw ValidationException::withMessages([
                'location_id' => 'A location is required when pairing as a Location Manager.',
            ]);
        }

        if (! LocationAccess::canAssignLocation($user, $workspace, $locationId)) {
            throw ValidationException::withMessages([
                'location_id' => 'Invalid location.',
            ]);
        }

        return $locationId;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function applyStateFilter(Collection $items, string $filter): Collection
    {
        $matches = match ($filter) {
            'online' => fn (array $s): bool => ($s['network_state'] ?? null) === 'online',
            'offline' => fn (array $s): bool => ($s['network_state'] ?? null) === 'offline',
            'active' => fn (array $s): bool => ($s['operational_status'] ?? null) === 'active',
            'inactive' => fn (array $s): bool => ($s['operational_status'] ?? null) === 'inactive',
            'connected' => fn (array $s): bool => ($s['pairing_state'] ?? null) === 'connected',
            'disconnected' => fn (array $s): bool => ($s['pairing_state'] ?? null) === 'disconnected',
            default => null,
        };

        if ($matches === null) {
            return $items;
        }

        return $items->filter($matches)->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @param  Collection<int, Screen>  $models
     * @return Collection<int, array<string, mixed>>
     */
    private function applySort(Collection $items, string $column, string $direction, Collection $models): Collection
    {
        $byId = $models->keyBy('id');
        $descending = $direction === 'desc';

        $sorted = match ($column) {
            'name' => $items->sortBy(
                fn (array $s) => mb_strtolower((string) $s['name']),
                descending: $descending,
            ),
            'seen' => $items->sortBy(
                fn (array $s) => $s['last_seen_at'] ?? ($descending ? '' : '9999'),
                descending: $descending,
            ),
            'created' => $items->sortBy(
                fn (array $s) => $byId->get($s['id'])?->created_at->timestamp ?? 0,
                descending: $descending,
            ),
            default => $items->sortBy(
                fn (array $s) => $s['updated_at'] ?? '',
                descending: $descending,
            ),
        };

        return $sorted->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Screen $screen): array
    {
        $device = $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $deployment = $screen->relationLoaded('deployments')
            ? $screen->deployments->first(fn (Deployment $d) => $d->status === DeploymentStatus::Active)
            : $screen->activeDeployment();

        $state = ScreenPresence::snapshot($screen, $device, $deployment);

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'orientation' => $screen->orientation,
            'location_id' => $screen->location_id,
            'location_name' => $screen->relationLoaded('location')
                ? $screen->location?->name
                : $screen->location?->name,
            'operational_status' => $screen->operational_status->value,
            'operational_status_label' => $screen->operational_status->label(),
            'pairing_state' => $state['pairing_state'],
            'network_state' => $state['network_state'],
            'health' => $state['health'],
            'health_label' => $state['health_label'],
            'content_sync' => $state['content_sync'],
            'content_sync_label' => $state['content_sync_label'],
            'orientation_mismatch' => $state['orientation_mismatch'],
            'content_type' => $deployment?->content_type->value,
            'content_name' => $deployment?->contentName(),
            'content_orientation' => $deployment?->contentOrientation(),
            // Legacy keys: kept so design-only surfaces stay unchanged, but they
            // resolve playlist deployments too rather than reporting nothing.
            'current_design_name' => $deployment?->contentName(),
            'current_design_orientation' => $deployment?->contentOrientation(),
            'current_version_number' => $deployment?->contentVersionNumber(),
            'deployed_at' => $deployment?->deployed_at?->toIso8601String(),
            'paired_at' => $device?->paired_at?->toIso8601String(),
            'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
            'player_version' => $device?->player_version,
            'viewport_width' => $device?->viewport_width,
            'viewport_height' => $device?->viewport_height,
            'reported_orientation' => $device?->reported_orientation,
            'playback_state' => $device?->playback_state,
            'updated_at' => $screen->updated_at?->toIso8601String(),
            'created_at' => $screen->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(
        Screen $screen,
        ScreenContentResolver $resolver,
        ScheduleEvaluator $evaluator,
    ): array {
        $item = $this->toListItem($screen);
        $device = $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            ?? $screen->devices->first();
        $active = $screen->deployments->first(
            fn (Deployment $d) => $d->status === DeploymentStatus::Active,
        );

        $item['workspace_name'] = $screen->workspace?->name;
        $item['location'] = $screen->location === null ? null : [
            'id' => $screen->location->id,
            'name' => $screen->location->name,
            'city' => $screen->location->city,
            'timezone' => $screen->location->timezone,
        ];
        $item['device'] = $device === null ? null : [
            'id' => $device->id,
            'device_identifier' => $device->device_identifier,
            'device_name' => $device->device_name,
            'paired_at' => $device->paired_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'revoked_at' => $device->revoked_at?->toIso8601String(),
            'player_version' => $device->player_version,
            'viewport_width' => $device->viewport_width,
            'viewport_height' => $device->viewport_height,
            'reported_orientation' => $device->reported_orientation,
            'playback_state' => $device->playback_state,
            'last_error_code' => $device->last_error_code,
            'reported_deployment_id' => $device->reported_deployment_id,
            'platform_meta' => $device->platform_meta,
            'offline' => $this->offlineState($device->platform_meta),
        ];

        $item['current_deployment'] = $active === null ? null : [
            'id' => $active->id,
            'status' => $active->status->value,
            'content_type' => $active->content_type->value,
            'content_name' => $active->contentName(),
            'playlist_id' => $active->playlist_id,
            'playlist_item_count' => $active->activePlaylistItemCount(),
            'design_name' => $active->contentName(),
            'design_orientation' => $active->contentOrientation(),
            'version_number' => $active->contentVersionNumber(),
            'deployed_at' => $active->deployed_at?->toIso8601String(),
        ];

        $item['deployments'] = $screen->deployments->map(fn (Deployment $deployment) => [
            'id' => $deployment->id,
            'status' => $deployment->status->value,
            'content_type' => $deployment->content_type->value,
            'content_name' => $deployment->contentName(),
            'design_name' => $deployment->contentName(),
            'version_number' => $deployment->contentVersionNumber(),
            'deployed_at' => $deployment->deployed_at?->toIso8601String(),
        ])->values();

        $item = [...$item, ...$this->scheduleState($screen, $resolver, $evaluator)];

        return $item;
    }

    /**
     * Offline readiness reported by the Player via heartbeat metadata.
     *
     * @param  array<string, mixed>|null  $platformMeta
     * @return array<string, mixed>|null
     */
    private function offlineState(?array $platformMeta): ?array
    {
        if ($platformMeta === null || ! isset($platformMeta['offline']) || ! is_array($platformMeta['offline'])) {
            return null;
        }

        $offline = $platformMeta['offline'];

        return [
            'cache_ready' => (bool) ($offline['cache_ready'] ?? false),
            'package_version' => isset($offline['package_version']) ? (string) $offline['package_version'] : null,
            'last_sync_at' => isset($offline['last_sync_at']) ? (string) $offline['last_sync_at'] : null,
            'last_offline_at' => isset($offline['last_offline_at']) ? (string) $offline['last_offline_at'] : null,
            'sync_error' => isset($offline['sync_error']) ? (string) $offline['sync_error'] : null,
            'reported_at' => isset($offline['reported_at']) ? (string) $offline['reported_at'] : null,
        ];
    }

    /**
     * What is driving this screen right now, and which schedule comes next.
     * `ScreenContentResolver` owns precedence and `ScheduleEvaluator` owns the
     * timing rules; this only shapes the payload.
     *
     * @return array<string, mixed>
     */
    private function scheduleState(
        Screen $screen,
        ScreenContentResolver $resolver,
        ScheduleEvaluator $evaluator,
    ): array {
        $now = now();
        $content = $resolver->resolve($screen, $now);
        $current = $content->window;
        $next = $evaluator->findNextForScreen($screen, $now);

        return [
            'content_source' => $content->source->value,
            'content_source_label' => $content->source->label(),
            'current_schedule' => $current === null ? null : [
                'id' => $current->schedule->id,
                'name' => $current->schedule->name,
                'playlist_name' => $current->schedule->playlist?->name,
                'priority' => $current->schedule->priority,
                'timezone' => $current->schedule->timezone,
                'starts_at' => $current->startsAtUtc()->toIso8601String(),
                'ends_at' => $current->endsAtUtc()->toIso8601String(),
                'starts_at_local' => $current->startsAt->copy()
                    ->setTimezone($current->schedule->timezone)->format('Y-m-d H:i'),
                'ends_at_local' => $current->endsAt->copy()
                    ->setTimezone($current->schedule->timezone)->format('Y-m-d H:i'),
            ],
            'next_schedule' => $next === null ? null : [
                'id' => $next['schedule']->id,
                'name' => $next['schedule']->name,
                'playlist_name' => $next['schedule']->playlist?->name,
                'priority' => $next['schedule']->priority,
                'timezone' => $next['schedule']->timezone,
                'starts_at' => $next['starts_at']->toIso8601String(),
                'ends_at' => $next['ends_at']->toIso8601String(),
                'starts_at_local' => $next['window']->startsAt->copy()
                    ->setTimezone($next['schedule']->timezone)->format('Y-m-d H:i'),
                'ends_at_local' => $next['window']->endsAt->copy()
                    ->setTimezone($next['schedule']->timezone)->format('Y-m-d H:i'),
            ],
        ];
    }
}
