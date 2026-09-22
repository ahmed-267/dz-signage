<?php

namespace App\Http\Controllers\App;

use App\Actions\Locations\ArchiveLocation;
use App\Actions\Locations\AssignScreenToLocation;
use App\Actions\Locations\CreateLocation;
use App\Actions\Locations\DeleteLocation;
use App\Actions\Locations\SaveLocation;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ListPagination;
use App\Support\Locations\LocationAccess;
use App\Support\Screens\ScreenPresence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Location::class);

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'active');
        $sort = ListPagination::sort(
            $request,
            ['name', 'city', 'screens', 'screen_count', 'updated'],
            'name',
            'asc',
        );
        if ($sort['column'] === 'screen_count') {
            $sort['column'] = 'screens';
        }
        $perPage = ListPagination::perPage($request);

        $query = Location::query()
            ->forWorkspace($workspace)
            ->with(['managers:id,name,email'])
            ->withCount('screens');

        LocationAccess::scopeLocationsForUser($query, $user, $workspace);

        if ($status === 'archived') {
            $query->archived();
        } elseif ($status !== 'all') {
            $query->active();
        }

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'ilike', $term)
                    ->orWhere('city', 'ilike', $term)
                    ->orWhere('region', 'ilike', $term)
                    ->orWhere('postcode', 'ilike', $term)
                    ->orWhere('address_line1', 'ilike', $term);
            });
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'city' => $query->orderBy('city', $direction)->orderBy('name')->orderByDesc('id'),
            'screens' => $query->orderBy('screens_count', $direction)->orderBy('name')->orderByDesc('id'),
            'updated' => $query->orderBy('updated_at', $direction)->orderByDesc('id'),
            default => $query->orderBy('name', $direction)->orderByDesc('id'),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        $pageLocations = $paginator->getCollection();
        $presenceByLocation = $this->presenceCountsForLocations($pageLocations);

        return Inertia::render('app/locations/index', [
            'locations' => [
                'data' => $pageLocations
                    ->map(fn (Location $location) => $this->toListItem(
                        $location,
                        $presenceByLocation[$location->id] ?? ['online' => 0, 'offline' => 0],
                    ))
                    ->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'can_manage' => $user->can('create', Location::class),
            'workspace_timezone' => $workspace->timezone,
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('create', Location::class);

        return Inertia::render('app/locations/create', $this->formProps($request->user(), $workspace));
    }

    public function store(Request $request, CreateLocation $action): RedirectResponse
    {
        $this->authorize('create', Location::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $location = $action->handle($request->user(), $workspace, $this->validatedPayload($request));

        return redirect()
            ->route('app.locations.show', $location)
            ->with('success', 'Location created.');
    }

    public function show(Request $request, Location $location): Response
    {
        $this->ensureWorkspace($request, $location);
        $this->authorize('view', $location);

        $location->load([
            'managers:id,name,email',
            'creator:id,name',
            'screens' => fn ($q) => $q
                ->with(['devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at')])
                ->orderBy('name'),
        ]);

        $screens = $location->screens->map(fn (Screen $screen) => $this->toScreenListItem($screen))->values();

        return Inertia::render('app/locations/show', [
            'location' => $this->toDetail($location, [
                'online' => $screens->filter(fn (array $s) => ($s['network_state'] ?? null) === 'online')->count(),
                'offline' => $screens->filter(fn (array $s) => ($s['network_state'] ?? null) === 'offline')->count(),
            ]),
            'screens' => $screens,
            'can_manage' => $request->user()->can('update', $location),
            'can_assign_managers' => $request->user()->can('assignManagers', $location),
            'can_delete' => $request->user()->can('delete', $location) && $location->screens->isEmpty(),
        ]);
    }

    public function edit(Request $request, Location $location): Response
    {
        $this->ensureWorkspace($request, $location);
        $this->authorize('update', $location);

        $workspace = $request->user()->currentWorkspace;
        $location->load(['managers:id,name,email']);

        return Inertia::render('app/locations/edit', [
            ...$this->formProps($request->user(), $workspace),
            'location' => $this->toDetail($location, ['online' => 0, 'offline' => 0]),
            'can_assign_managers' => $request->user()->can('assignManagers', $location),
        ]);
    }

    public function update(Request $request, Location $location, SaveLocation $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $location);
        $this->authorize('update', $location);

        $payload = $this->validatedPayload($request);
        if (! $request->user()->can('assignManagers', $location)) {
            unset($payload['manager_ids']);
        }

        $action->handle($request->user(), $location, $payload);

        return redirect()
            ->route('app.locations.show', $location)
            ->with('success', 'Location saved.');
    }

    public function archive(Request $request, Location $location, ArchiveLocation $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $location);
        $this->authorize('archive', $location);

        $action->handle($request->user(), $location);

        return redirect()
            ->route('app.locations')
            ->with('success', 'Location archived.');
    }

    public function destroy(Request $request, Location $location, DeleteLocation $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $location);
        $this->authorize('delete', $location);

        try {
            $action->handle($request->user(), $location);
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('app.locations')
            ->with('success', 'Location deleted.');
    }

    public function assignScreen(
        Request $request,
        Location $location,
        AssignScreenToLocation $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $location);
        $this->authorize('assignScreen', $location);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'screen_id' => ['required', 'integer'],
        ]);

        $screen = Screen::query()
            ->forWorkspace($workspace)
            ->whereKey($data['screen_id'])
            ->firstOrFail();

        $this->authorize('update', $screen);

        if (! LocationAccess::canAccessScreen($request->user(), $screen)
            && LocationAccess::isLocationManager($request->user(), $workspace)) {
            abort(403);
        }

        $action->handle($request->user(), $screen, $location);

        return redirect()->back()->with('success', 'Screen assigned to location.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(User $user, Workspace $workspace): array
    {
        $canAssignManagers = ($user->roleIn($workspace)?->canManageWorkspace() ?? false);

        return [
            'defaults' => [
                'country' => 'UK',
                'timezone' => $workspace->timezone,
            ],
            'timezones' => timezone_identifiers_list(),
            'manager_options' => $canAssignManagers
                ? $workspace->members()
                    ->where('role', WorkspaceRole::LocationManager->value)
                    ->with('user:id,name,email')
                    ->get()
                    ->map(fn ($member) => [
                        'id' => $member->user_id,
                        'name' => $member->user?->name,
                        'email' => $member->user?->email,
                    ])
                    ->filter(fn (array $row) => $row['name'] !== null)
                    ->values()
                : [],
            'can_assign_managers' => $canAssignManagers,
            'workspace_timezone' => $workspace->timezone,
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     address_line1: string|null,
     *     address_line2: string|null,
     *     city: string|null,
     *     region: string|null,
     *     postcode: string|null,
     *     country: string|null,
     *     timezone: string,
     *     notes: string|null,
     *     manager_ids: list<int>|null,
     * }
     */
    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:64'],
            'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', 'timezone:all'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'manager_ids' => ['nullable', 'array'],
            'manager_ids.*' => ['integer'],
        ]);

        return [
            'name' => $data['name'],
            'address_line1' => $data['address_line1'] ?? null,
            'address_line2' => $data['address_line2'] ?? null,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'postcode' => $data['postcode'] ?? null,
            'country' => $data['country'] ?? null,
            'timezone' => $data['timezone'],
            'notes' => $data['notes'] ?? null,
            'manager_ids' => array_key_exists('manager_ids', $data)
                ? array_values(array_map('intval', $data['manager_ids'] ?? []))
                : null,
        ];
    }

    private function ensureWorkspace(Request $request, Location $location): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $location->workspace_id === (int) $workspace->id,
            404,
        );
    }

    /**
     * @param  iterable<int, Location>  $locations
     * @return array<int, array{online: int, offline: int}>
     */
    private function presenceCountsForLocations(iterable $locations): array
    {
        $ids = collect($locations)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $screens = Screen::query()
            ->whereIn('location_id', $ids)
            ->with(['devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at')])
            ->get();

        $counts = [];
        foreach ($ids as $id) {
            $counts[(int) $id] = ['online' => 0, 'offline' => 0];
        }

        foreach ($screens as $screen) {
            $device = $screen->devices->first();
            $network = ScreenPresence::networkState($device);
            $locationId = (int) $screen->location_id;
            if (! isset($counts[$locationId])) {
                continue;
            }
            $counts[$locationId][$network]++;
        }

        return $counts;
    }

    /**
     * @param  array{online: int, offline: int}  $presence
     * @return array<string, mixed>
     */
    private function toListItem(Location $location, array $presence): array
    {
        return [
            'id' => $location->id,
            'name' => $location->name,
            'city' => $location->city,
            'region' => $location->region,
            'postcode' => $location->postcode,
            'country' => $location->country,
            'timezone' => $location->timezone,
            'address' => $location->formattedAddress(),
            'archived_at' => $location->archived_at?->toIso8601String(),
            'is_archived' => $location->isArchived(),
            'screen_count' => (int) ($location->screens_count ?? $location->screens()->count()),
            'online_count' => $presence['online'],
            'offline_count' => $presence['offline'],
            'manager_count' => $location->relationLoaded('managers')
                ? $location->managers->count()
                : $location->managers()->count(),
            'updated_at' => $location->updated_at?->toIso8601String(),
            'created_at' => $location->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array{online: int, offline: int}  $presence
     * @return array<string, mixed>
     */
    private function toDetail(Location $location, array $presence): array
    {
        $item = $this->toListItem($location, $presence);
        $item['address_line1'] = $location->address_line1;
        $item['address_line2'] = $location->address_line2;
        $item['notes'] = $location->notes;
        $item['managers'] = $location->relationLoaded('managers')
            ? $location->managers->map(fn (User $manager) => [
                'id' => $manager->id,
                'name' => $manager->name,
                'email' => $manager->email,
            ])->values()
            : [];
        $item['manager_ids'] = collect($item['managers'])->pluck('id')->values()->all();
        $item['creator_name'] = $location->relationLoaded('creator')
            ? $location->creator?->name
            : null;

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function toScreenListItem(Screen $screen): array
    {
        $device = $screen->relationLoaded('devices')
            ? $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null)
            : $screen->activeDevice();

        $state = ScreenPresence::snapshot($screen, $device, null);

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'orientation' => $screen->orientation,
            'operational_status' => $screen->operational_status->value,
            'operational_status_label' => $screen->operational_status->label(),
            'pairing_state' => $state['pairing_state'],
            'network_state' => $state['network_state'],
            'health' => $state['health'],
            'health_label' => $state['health_label'],
            'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
        ];
    }
}
