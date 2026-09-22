<?php

namespace App\Http\Controllers\App;

use App\Actions\Schedules\ActivateSchedule;
use App\Actions\Schedules\ArchiveSchedule;
use App\Actions\Schedules\BulkDeleteSchedules;
use App\Actions\Schedules\CreateSchedule;
use App\Actions\Schedules\DeleteSchedule;
use App\Actions\Schedules\DetectScheduleConflicts;
use App\Actions\Schedules\DuplicateSchedule;
use App\Actions\Schedules\PauseSchedule;
use App\Actions\Schedules\SaveSchedule;
use App\Enums\PlaylistStatus;
use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Schedule;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\ScreenDevice;
use App\Models\Workspace;
use App\Support\ListPagination;
use App\Support\Playlists\PlaylistRuntimeCalculator;
use App\Support\ProductLabels;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\MediaMap;
use App\Support\Schedules\ScheduleDays;
use App\Support\Schedules\ScheduleDefaults;
use App\Support\Schedules\ScheduleEvaluator;
use App\Support\Schedules\ScheduleWindow;
use App\Support\Screens\ScreenPresence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly ScheduleEvaluator $evaluator,
        private readonly DetectScheduleConflicts $conflictDetector,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Schedule::class);

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $screenId = (int) $request->input('screen', 0);
        $playlistId = (int) $request->input('playlist', 0);
        $sort = ListPagination::sort(
            $request,
            ['name', 'priority', 'status', 'updated', 'created', 'start_time'],
            'updated',
            'desc',
        );
        $perPage = ListPagination::perPage($request, 20, [10, 20, 50]);

        $now = now();
        $today = $now->copy()->setTimezone($workspace->timezone)->format('Y-m-d');

        $query = Schedule::query()
            ->forWorkspace($workspace)
            ->with([
                'creator:id,name',
                'playlist:id,name,orientation,status',
                'playlistVersion:id,playlist_id,version_number,published_at',
                'screens:id,name,orientation,operational_status',
            ]);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'ilike', $term)
                ->orWhere('description', 'ilike', $term)
            );
        }

        $this->applyStatusFilter($query, $status, $today);

        if ($screenId > 0) {
            $query->whereHas('screens', fn (Builder $inner) => $inner->whereKey($screenId));
        }

        if ($playlistId > 0) {
            $query->where('playlist_id', $playlistId);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'name' => $query->orderBy('name', $direction)->orderByDesc('id'),
            'priority' => $direction === 'desc'
                ? $query->orderByPrecedence()
                : $query->orderBy('priority')->orderBy('name')->orderByDesc('id'),
            'status' => $query->orderBy('status', $direction)->orderBy('name')->orderByDesc('id'),
            'created' => $query->orderBy('created_at', $direction)->orderByDesc('id'),
            'start_time' => $query->orderBy('start_time', $direction)->orderByDesc('id'),
            default => $query->orderBy('updated_at', $direction)->orderByDesc('id'),
        };

        $paginator = $query->paginate($perPage)->withQueryString();

        $conflictFilter = (string) $request->input('conflicts', 'all');

        $data = $paginator->getCollection()
            ->map(function (Schedule $schedule) use ($now) {
                $item = $this->toListItem($schedule, $now);
                $item['conflict_count'] = $schedule->status === ScheduleStatus::Active
                    ? count($this->conflictDetector->handle($schedule))
                    : 0;

                return $item;
            })
            ->values();

        if ($conflictFilter === 'only') {
            $data = $data->filter(fn (array $item) => $item['conflict_count'] > 0)->values();
        }

        return Inertia::render('app/schedules/index', [
            'schedules' => [
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'screen' => $screenId > 0 ? $screenId : null,
                'playlist' => $playlistId > 0 ? $playlistId : null,
                'conflicts' => $conflictFilter === 'only' ? 'only' : 'all',
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'calendar' => $this->calendar($workspace, $now, (string) $request->input('week', '')),
            'statuses' => $this->statusOptions(),
            'screens' => $this->screenOptions($workspace),
            'playlists' => $this->publishedPlaylists($workspace, withPreview: false),
            'config' => ScheduleDefaults::forFrontend(),
            'workspace_timezone' => $workspace->timezone,
            'can_manage' => $user->can('create', Schedule::class),
        ]);
    }

    public function create(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('create', Schedule::class);

        return Inertia::render('app/schedules/create', $this->formProps($workspace));
    }

    public function store(Request $request, CreateSchedule $action): RedirectResponse
    {
        $this->authorize('create', Schedule::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $this->validatePayload($request);

        $schedule = $action->handle($request->user(), $workspace, $data);

        return redirect()
            ->route('app.schedules.edit', $schedule)
            ->with('success', 'Schedule created.');
    }

    public function edit(Request $request, Schedule $schedule): Response
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('view', $schedule);

        $workspace = $request->user()->currentWorkspace;
        $schedule->load([
            'creator:id,name',
            'playlist:id,name,orientation,status',
            'playlistVersion:id,playlist_id,version_number,published_at',
            'screens:id,name,orientation,operational_status',
        ]);

        return Inertia::render('app/schedules/edit', [
            ...$this->formProps($workspace),
            'schedule' => $this->toDetail($schedule, now()),
            'can_edit' => $request->user()->can('update', $schedule),
        ]);
    }

    public function update(Request $request, Schedule $schedule, SaveSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('update', $schedule);

        $action->handle($request->user(), $schedule, $this->validatePayload($request));

        return redirect()->back()->with('success', 'Schedule saved.');
    }

    public function activate(Request $request, Schedule $schedule, ActivateSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('activate', $schedule);

        $action->handle($request->user(), $schedule);

        return redirect()->back()->with('success', 'Schedule activated.');
    }

    public function pause(Request $request, Schedule $schedule, PauseSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('pause', $schedule);

        $action->handle($request->user(), $schedule);

        return redirect()->back()->with('success', 'Schedule paused.');
    }

    public function duplicate(Request $request, Schedule $schedule, DuplicateSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('duplicate', $schedule);

        $copy = $action->handle($request->user(), $schedule);

        return redirect()
            ->route('app.schedules.edit', $copy)
            ->with('success', 'Schedule duplicated.');
    }

    public function archive(Request $request, Schedule $schedule, ArchiveSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('archive', $schedule);

        $action->handle($request->user(), $schedule);

        return redirect()->back()->with('success', 'Schedule archived.');
    }

    public function destroy(Request $request, Schedule $schedule, DeleteSchedule $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('delete', $schedule);

        $action->handle($schedule);

        return redirect()
            ->route('app.schedules')
            ->with('success', 'Schedule deleted.');
    }

    public function bulkDestroy(Request $request, BulkDeleteSchedules $action): RedirectResponse
    {
        $this->authorize('create', Schedule::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $inWorkspace = Schedule::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $ids)
            ->exists();
        abort_unless($inWorkspace, 404);

        $result = $action->handle($request->user(), $workspace, $ids);

        return $this->bulkDeleteRedirect($result, 'Schedule', 'Schedules');
    }

    /**
     * @param  array{deleted: list<int>, failed: list<array{id: int, name: string, reason: string}>}  $result
     */
    private function bulkDeleteRedirect(array $result, string $singular, string $plural): RedirectResponse
    {
        $deletedCount = count($result['deleted']);
        $failed = $result['failed'];
        $redirect = redirect()->route('app.schedules');

        if ($deletedCount > 0) {
            $label = $deletedCount === 1 ? $singular : $plural;
            $redirect = $redirect->with('success', "{$deletedCount} {$label} deleted.");
        }

        if ($failed !== []) {
            $details = collect($failed)
                ->take(5)
                ->map(fn (array $row) => "{$row['name']}: {$row['reason']}")
                ->implode(' ');
            $more = count($failed) > 5 ? ' …' : '';
            $message = count($failed) === 1
                ? "Could not delete 1 {$singular}. {$details}{$more}"
                : 'Could not delete '.count($failed)." {$plural}. {$details}{$more}";

            return $redirect->with('error', $message);
        }

        if ($deletedCount === 0) {
            return $redirect->with('error', "No {$plural} were deleted.");
        }

        return $redirect;
    }

    /**
     * Preview payload: what this schedule plays, when it plays next, and which
     * live schedules it overlaps.
     */
    public function preview(
        Request $request,
        Schedule $schedule,
        DetectScheduleConflicts $conflicts,
    ): JsonResponse {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('view', $schedule);

        $now = now();
        $schedule->load([
            'playlist:id,name,orientation,status',
            'playlistVersion.items.screenDesign:id,name,orientation,canvas_width,canvas_height',
            'playlistVersion.items.screenDesignVersion:id,screen_design_id,version_number,schema',
            'screens:id,name,orientation,operational_status',
        ]);

        $items = [];
        $mediaIds = [];

        foreach ($schedule->playlistVersion->items ?? [] as $item) {
            if (! $item->is_active) {
                continue;
            }

            $payload = $this->toItemPayload($item);
            $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($payload['schema'])];
            $items[] = $payload;
        }

        return response()->json([
            'schedule' => $this->toDetail($schedule, $now),
            'items' => $items,
            'total_duration_seconds' => PlaylistRuntimeCalculator::totalSeconds($items),
            'version_scope' => 'pinned',
            'playlist_version_id' => $schedule->playlist_version_id,
            'media_map' => MediaMap::build($schedule->workspace_id, $mediaIds),
            'upcoming' => array_map(
                fn (ScheduleWindow $window) => $this->toWindowPayload($window),
                $this->evaluator->upcomingWindows($schedule, $now),
            ),
            'conflicts' => $conflicts->handle($schedule),
        ]);
    }

    /**
     * Overlap warnings for a schedule. Overlaps never block a save.
     */
    public function conflicts(
        Request $request,
        Schedule $schedule,
        DetectScheduleConflicts $action,
    ): JsonResponse {
        $this->ensureWorkspace($request, $schedule);
        $this->authorize('view', $schedule);

        return response()->json([
            'conflicts' => $action->handle($schedule),
        ]);
    }

    /**
     * Live overlap check for create/edit drafts before save.
     */
    public function previewConflicts(
        Request $request,
        DetectScheduleConflicts $action,
    ): JsonResponse {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Schedule::class);

        $data = $request->validate([
            'except_id' => ['sometimes', 'nullable', 'integer'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'string', 'max:8'],
            'end_time' => ['sometimes', 'string', 'max:8'],
            'days_of_week' => ['sometimes', 'array'],
            'days_of_week.*' => ['integer'],
            'priority' => ['sometimes', 'integer'],
            'screen_ids' => ['sometimes', 'array'],
            'screen_ids.*' => ['integer'],
        ]);

        $exceptId = isset($data['except_id']) ? (int) $data['except_id'] : null;
        if ($exceptId) {
            $existing = Schedule::query()->forWorkspace($workspace)->whereKey($exceptId)->first();
            abort_unless($existing !== null, 404);
            $this->authorize('view', $existing);
        }

        $screenIds = array_values(array_map('intval', $data['screen_ids'] ?? []));

        return response()->json([
            'conflicts' => $action->forDraft(
                (int) $workspace->id,
                $data,
                $screenIds,
                $exceptId,
            ),
        ]);
    }

    /**
     * Shape-only validation. Business rules (published playlists, pinned
     * versions, timezone identifiers, window sanity) belong to the actions so
     * they hold for every caller.
     *
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'playlist_id' => ['sometimes', 'nullable', 'integer'],
            'screen_ids' => ['sometimes', 'array'],
            'screen_ids.*' => ['integer'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date'],
            'start_time' => ['sometimes', 'string', 'max:8'],
            'end_time' => ['sometimes', 'string', 'max:8'],
            'days_of_week' => ['sometimes', 'array'],
            'days_of_week.*' => ['integer'],
            'priority' => ['sometimes', 'integer'],
        ], [], [
            'name' => ProductLabels::SCHEDULE_NAME,
        ]);
    }

    /**
     * Status filter, including the derived Ended state which is never stored.
     *
     * @param  Builder<Schedule>  $query
     */
    private function applyStatusFilter(Builder $query, string $status, string $today): void
    {
        if ($status === 'ended') {
            $query->where('status', ScheduleStatus::Active)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today);

            return;
        }

        if ($status === ScheduleStatus::Active->value) {
            // An active schedule past its end date shows as Ended instead.
            $query->where('status', ScheduleStatus::Active)
                ->where(fn (Builder $inner) => $inner
                    ->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today)
                );

            return;
        }

        if (in_array($status, ScheduleStatus::values(), true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Workspace $workspace): array
    {
        return [
            'playlists' => $this->publishedPlaylists($workspace, withPreview: true),
            'screens' => $this->screenOptions($workspace),
            'locations' => $this->locationOptions($workspace),
            'timezones' => $this->timezones(),
            'workspace_timezone' => $workspace->timezone,
            'day_labels' => ScheduleDays::labels(),
            'day_short_labels' => ScheduleDays::shortLabels(),
            'config' => ScheduleDefaults::forFrontend(),
            'statuses' => $this->statusOptions(),
        ];
    }

    /**
     * Schedules only play **published** playlists, pinned to the version that
     * was published when the schedule was saved.
     *
     * Runtime figures come from PlaylistRuntimeCalculator (published scope).
     * When a newer draft exists with a different total, draft_total_duration_seconds
     * is included so the form can show both.
     *
     * @return array<int, array<string, mixed>>
     */
    private function publishedPlaylists(Workspace $workspace, bool $withPreview = false): array
    {
        $versionRelations = [
            'publishedVersion.items' => fn ($items) => $items->orderBy('position'),
            'versions' => fn ($versions) => $versions
                ->orderByDesc('version_number')
                ->with(['items' => fn ($items) => $items->orderBy('position')]),
        ];

        if ($withPreview) {
            $versionRelations['publishedVersion.items'] = fn ($items) => $items
                ->where('is_active', true)
                ->orderBy('position')
                ->with([
                    'screenDesign:id,name,canvas_width,canvas_height',
                    'screenDesignVersion:id,screen_design_id,schema',
                ]);
        }

        return Playlist::query()
            ->forWorkspace($workspace)
            ->where('status', PlaylistStatus::Published)
            ->whereNotNull('published_version_id')
            ->with($versionRelations)
            ->orderBy('name')
            ->get()
            ->map(function (Playlist $playlist) use ($withPreview) {
                $version = $playlist->publishedVersion;
                $previewItem = null;
                $publishedDuration = PlaylistRuntimeCalculator::totalSeconds($version);

                $latest = $playlist->versions->sortByDesc('version_number')->first();
                $draftDuration = null;
                $publishedId = $version !== null ? (int) $version->id : 0;
                if (
                    $latest !== null
                    && ($latest->published_at === null || (int) $latest->id !== $publishedId)
                ) {
                    $candidate = PlaylistRuntimeCalculator::totalSeconds($latest);
                    if ($candidate !== $publishedDuration) {
                        $draftDuration = $candidate;
                    }
                }

                if ($withPreview && $version !== null) {
                    $activeItems = $version->items->filter(fn (PlaylistItem $item) => $item->is_active)->values();
                    $first = $activeItems->first();

                    if ($first !== null) {
                        $previewDesign = $first->screenDesign;
                        $previewVersion = $first->screenDesignVersion;
                        $previewItem = [
                            'screen_design_id' => $first->screen_design_id,
                            'name' => $previewDesign?->name,
                            'canvas_width' => $previewDesign?->canvas_width,
                            'canvas_height' => $previewDesign?->canvas_height,
                            'schema' => LayoutSchemaNormalizer::normalize(
                                $previewVersion === null ? [] : $previewVersion->schema,
                            ),
                        ];
                    }
                }

                $activeCount = $version === null
                    ? 0
                    : $version->items->filter(fn (PlaylistItem $item) => $item->is_active)->count();

                return [
                    'id' => $playlist->id,
                    'name' => $playlist->name,
                    'orientation' => $playlist->orientation?->value,
                    'orientation_label' => $playlist->orientation?->label(),
                    'published_version_id' => $playlist->published_version_id,
                    'published_version_number' => $version?->version_number,
                    'item_count' => $activeCount,
                    'total_duration_seconds' => $publishedDuration,
                    'draft_total_duration_seconds' => $draftDuration,
                    'version_scope' => 'published',
                    'published_at' => $playlist->published_at?->toIso8601String(),
                    'preview_item' => $previewItem,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function screenOptions(Workspace $workspace): array
    {
        return Screen::query()
            ->forWorkspace($workspace)
            ->with([
                'location:id,name',
                'devices' => fn ($query) => $query
                    ->whereNull('revoked_at')
                    ->orderByDesc('paired_at'),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'orientation', 'operational_status', 'location_id'])
            ->map(function (Screen $screen) {
                $device = $screen->devices->first(
                    fn (ScreenDevice $candidate) => $candidate->revoked_at === null,
                );

                return [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'orientation' => $screen->orientation,
                    'operational_status' => $screen->operational_status->value,
                    'location_id' => $screen->location_id,
                    'location_name' => $screen->location?->name,
                    'network_state' => ScreenPresence::networkState($device),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function locationOptions(Workspace $workspace): array
    {
        return Location::query()
            ->forWorkspace($workspace)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        $options = array_map(
            fn (ScheduleStatus $status) => ['value' => $status->value, 'label' => $status->label()],
            ScheduleStatus::cases(),
        );

        // Ended is derived, not stored, but users filter by it.
        $options[] = ['value' => 'ended', 'label' => 'Ended'];

        return $options;
    }

    /**
     * @return array<int, string>
     */
    private function timezones(): array
    {
        return timezone_identifiers_list();
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Schedule $schedule, CarbonInterface $now): array
    {
        // Only an active schedule is on screen, so a draft or paused schedule
        // reports its next window but never a current one.
        $current = $schedule->status === ScheduleStatus::Active
            ? $this->evaluator->window($schedule, $now)
            : null;
        $isLive = $current !== null;
        $next = $this->evaluator->nextWindow($schedule, $now);

        return [
            'id' => $schedule->id,
            'name' => $schedule->name,
            'description' => $schedule->description,
            'status' => $schedule->displayStatus($now),
            'status_label' => $schedule->displayStatusLabel($now),
            'stored_status' => $schedule->status->value,
            'has_ended' => $schedule->hasEnded($now),
            'is_live' => $isLive,
            'priority' => $schedule->priority,
            'playlist_id' => $schedule->playlist_id,
            'playlist_name' => $schedule->playlist?->name,
            'playlist_orientation' => $schedule->playlist?->orientation?->value,
            'playlist_version_id' => $schedule->playlist_version_id,
            'playlist_version_number' => $schedule->playlistVersion?->version_number,
            'timezone' => $schedule->timezone,
            'start_date' => $schedule->start_date?->format('Y-m-d'),
            'end_date' => $schedule->end_date?->format('Y-m-d'),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'days_of_week' => $schedule->days(),
            'day_labels' => array_map(
                fn (int $day): string => ScheduleDays::shortLabels()[$day],
                $schedule->days(),
            ),
            'crosses_midnight' => $schedule->crossesMidnight(),
            'duration_minutes' => $schedule->durationMinutes(),
            'screen_count' => $schedule->screens->count(),
            'screens' => $schedule->screens
                ->map(fn (Screen $screen) => [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'orientation' => $screen->orientation,
                    'operational_status' => $screen->operational_status->value,
                ])
                ->values()
                ->all(),
            'current_window' => $current !== null ? $this->toWindowPayload($current) : null,
            'next_window' => $next !== null ? $this->toWindowPayload($next) : null,
            'activated_at' => $schedule->activated_at?->toIso8601String(),
            'created_by_name' => $schedule->creator?->name,
            'updated_at' => $schedule->updated_at?->toIso8601String(),
            'created_at' => $schedule->created_at?->toIso8601String(),
            'conflict_count' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDetail(Schedule $schedule, CarbonInterface $now): array
    {
        return [
            ...$this->toListItem($schedule, $now),
            'screen_ids' => $schedule->screens->pluck('id')->values()->all(),
            'upcoming' => array_map(
                fn (ScheduleWindow $window) => $this->toWindowPayload($window),
                $this->evaluator->upcomingWindows($schedule, $now, 3),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toWindowPayload(ScheduleWindow $window): array
    {
        $timezone = $window->schedule->timezone;

        return [
            'starts_at' => $window->startsAtUtc()->toIso8601String(),
            'ends_at' => $window->endsAtUtc()->toIso8601String(),
            'starts_at_local' => $window->startsAt->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            'ends_at_local' => $window->endsAt->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            'timezone' => $timezone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toItemPayload(PlaylistItem $item): array
    {
        $design = $item->screenDesign;
        $version = $item->screenDesignVersion;

        return [
            'position' => $item->position,
            'screen_design_id' => $item->screen_design_id,
            'screen_design_version_id' => $item->screen_design_version_id,
            'name' => $design?->name,
            'canvas_width' => $design?->canvas_width,
            'canvas_height' => $design?->canvas_height,
            'duration_seconds' => $item->duration_seconds,
            'loop_count' => max(1, (int) $item->loop_count),
            'effective_duration_seconds' => PlaylistRuntimeCalculator::itemEffectiveSeconds($item),
            'transition' => $item->transition->value,
            'transition_speed' => $item->transition_speed->value,
            'schema' => LayoutSchemaNormalizer::normalize($version === null ? [] : $version->schema),
        ];
    }

    /**
     * Week grid for the schedules calendar, laid out in the workspace
     * timezone. A schedule in another timezone is converted, so blocks may
     * start on a different local day than the schedule's own.
     *
     * @return array<string, mixed>
     */
    private function calendar(Workspace $workspace, CarbonInterface $now, string $weekInput): array
    {
        $timezone = $workspace->timezone;
        $reference = $now->copy()->setTimezone($timezone);

        if ($weekInput !== '') {
            try {
                $reference = Carbon::parse($weekInput, $timezone);
            } catch (\Throwable) {
                // Unparseable week parameter falls back to the current week.
            }
        }

        $weekStart = $reference->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);

        $schedules = Schedule::query()
            ->forWorkspace($workspace)
            ->where('status', '!=', ScheduleStatus::Archived->value)
            ->with(['playlist:id,name'])
            ->orderByPrecedence()
            ->get();

        $blocks = [];
        foreach ($schedules as $schedule) {
            // Scan a day either side so windows from adjacent timezones and
            // windows that cross midnight land on the right grid day.
            for ($offset = -1; $offset <= 7; $offset++) {
                $window = $this->evaluator->occurrenceOn(
                    $schedule,
                    $weekStart->copy()->addDays($offset)->setTimezone($schedule->timezone),
                );

                if ($window === null) {
                    continue;
                }

                $localStart = $window->startsAt->copy()->setTimezone($timezone);
                $day = $localStart->format('Y-m-d');

                if ($day < $weekStart->format('Y-m-d') || $day > $weekEnd->format('Y-m-d')) {
                    continue;
                }

                $startMinutes = ($localStart->hour * 60) + $localStart->minute;
                $blocks[$day][] = [
                    'schedule_id' => $schedule->id,
                    'name' => $schedule->name,
                    'playlist_name' => $schedule->playlist?->name,
                    'priority' => $schedule->priority,
                    'status' => $schedule->displayStatus($now),
                    'status_label' => $schedule->displayStatusLabel($now),
                    'timezone' => $schedule->timezone,
                    'start_minutes' => $startMinutes,
                    // May exceed 1440 when the block runs into the next day.
                    'end_minutes' => $startMinutes + $schedule->durationMinutes(),
                    'crosses_midnight' => $startMinutes + $schedule->durationMinutes() > 1440,
                    'starts_at' => $window->startsAtUtc()->toIso8601String(),
                    'ends_at' => $window->endsAtUtc()->toIso8601String(),
                ];
            }
        }

        $days = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $weekStart->copy()->addDays($offset);
            $key = $date->format('Y-m-d');
            $isoWeekday = $date->isoWeekday();

            $days[] = [
                'date' => $key,
                'iso_weekday' => $isoWeekday,
                'label' => ScheduleDays::labels()[$isoWeekday],
                'short_label' => ScheduleDays::shortLabels()[$isoWeekday],
                'is_today' => $key === $now->copy()->setTimezone($timezone)->format('Y-m-d'),
                'blocks' => $blocks[$key] ?? [],
            ];
        }

        return [
            'timezone' => $timezone,
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'previous_week' => $weekStart->copy()->subWeek()->format('Y-m-d'),
            'next_week' => $weekStart->copy()->addWeek()->format('Y-m-d'),
            'days' => $days,
        ];
    }

    private function ensureWorkspace(Request $request, Schedule $schedule): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $schedule->workspace_id === (int) $workspace->id,
            404,
        );
    }
}
