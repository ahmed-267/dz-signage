<?php

namespace App\Http\Controllers\App;

use App\Actions\Playlists\ArchivePlaylist;
use App\Actions\Playlists\BulkDeletePlaylists;
use App\Actions\Playlists\CreatePlaylist;
use App\Actions\Playlists\DeletePlaylist;
use App\Actions\Playlists\DuplicatePlaylist;
use App\Actions\Playlists\PublishPlaylist;
use App\Actions\Playlists\PublishPlaylistToScreens;
use App\Actions\Playlists\SavePlaylistDraft;
use App\Enums\DeploymentStatus;
use App\Enums\PlaylistStatus;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\PlaylistVersion;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Support\Playlists\PlaylistDefaults;
use App\Support\Playlists\PlaylistRuntimeCalculator;
use App\Support\ProductLabels;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\Rendering\MediaMap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlaylistController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Playlist::class);

        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $orientation = (string) $request->input('orientation', 'all');
        $sort = (string) $request->input('sort', 'updated_desc');

        $query = Playlist::query()
            ->forWorkspace($workspace)
            ->with([
                'creator:id,name',
                'versions' => fn ($versions) => $versions->orderByDesc('version_number'),
                'versions.items.screenDesign:id,name,orientation,canvas_width,canvas_height',
                'versions.items.screenDesignVersion:id,screen_design_id,version_number,schema,published_at',
            ])
            ->withCount([
                'deployments as assigned_tv_count' => fn ($deployments) => $deployments
                    ->where('status', DeploymentStatus::Active),
                'schedules as schedule_count',
            ]);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(fn ($inner) => $inner
                ->where('name', 'ilike', $term)
                ->orWhere('description', 'ilike', $term)
            );
        }

        if ($status !== 'all' && in_array($status, array_column(PlaylistStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($orientation !== 'all' && in_array($orientation, ['landscape', 'portrait'], true)) {
            $query->where('orientation', $orientation);
        }

        $query = match ($sort) {
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'created_desc' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate(24)->withQueryString();

        $items = $paginator->getCollection()->map(fn (Playlist $playlist) => $this->toListItem($playlist))->values();

        $mediaIds = [];
        foreach ($items as $item) {
            $previewItems = $item['preview_items'] ?? [];
            if (! is_array($previewItems) || $previewItems === []) {
                $preview = $item['preview_item'] ?? null;
                $previewItems = is_array($preview) ? [$preview] : [];
            }
            foreach ($previewItems as $preview) {
                if (is_array($preview)) {
                    $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($preview['schema'] ?? [])];
                }
            }
        }

        return Inertia::render('app/playlists/index', [
            'playlists' => [
                'data' => $items,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
                'links' => [
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'orientation' => $orientation,
                'sort' => $sort,
            ],
            'media_map' => $this->mediaMap($workspace->id, $mediaIds),
            'config' => PlaylistDefaults::forFrontend(),
            'can_manage' => $user->can('create', Playlist::class),
            'can_publish_to_screens' => $user->can('create', Deployment::class),
            'statuses' => array_map(
                fn (PlaylistStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                PlaylistStatus::cases(),
            ),
            'screens' => $this->screensForPublishing($request),
        ]);
    }

    public function store(Request $request, CreatePlaylist $action): RedirectResponse
    {
        $this->authorize('create', Playlist::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'orientation' => ['nullable', Rule::enum(TemplateOrientation::class)],
        ], [], [
            'name' => ProductLabels::PLAYLIST_NAME,
        ]);

        $playlist = $action->handle(
            $request->user(),
            $workspace,
            (string) ($data['name'] ?? 'Untitled Playlist'),
            $data['description'] ?? null,
            isset($data['orientation']) ? TemplateOrientation::from($data['orientation']) : null,
        );

        return redirect()
            ->route('app.playlists.edit', $playlist)
            ->with('success', 'Playlist created.');
    }

    public function edit(Request $request, Playlist $playlist): Response
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('view', $playlist);

        $version = $this->editableVersion($playlist);
        $items = $version !== null ? $this->itemPayloads($version) : [];

        $mediaIds = [];
        foreach ($items as $item) {
            $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($item['schema'] ?? [])];
        }

        return Inertia::render('app/playlists/edit', [
            'playlist' => $this->toEditorPayload($playlist, $version, $items),
            'media_map' => $this->mediaMap($playlist->workspace_id, $mediaIds),
            'config' => PlaylistDefaults::forFrontend(),
            'can_edit' => $request->user()->can('update', $playlist),
            'can_publish_to_screens' => $request->user()->can('publishToScreens', $playlist),
            'screens' => $this->screensForPublishing($request),
            'published_designs' => $this->publishedDesignPage($request, $playlist->workspace_id),
        ]);
    }

    public function update(Request $request, Playlist $playlist, SavePlaylistDraft $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('update', $playlist);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'orientation' => ['sometimes', 'nullable', Rule::enum(TemplateOrientation::class)],
            'items' => ['sometimes', 'array'],
            'items.*.screen_design_id' => ['required', 'integer'],
            'items.*.screen_design_version_id' => ['nullable', 'integer'],
            'items.*.duration_seconds' => ['nullable', 'integer'],
            'items.*.loop_count' => ['nullable', 'integer', 'min:1', 'max:99'],
            'items.*.transition' => ['nullable', 'string'],
            'items.*.transition_speed' => ['nullable', 'string'],
            'items.*.is_active' => ['nullable', 'boolean'],
        ], [], [
            'name' => ProductLabels::PLAYLIST_NAME,
        ]);

        $action->handle($request->user(), $playlist, $data);

        return redirect()->back()->with('success', 'Playlist draft saved.');
    }

    public function publish(Request $request, Playlist $playlist, PublishPlaylist $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('publish', $playlist);

        $action->handle($request->user(), $playlist);

        return redirect()
            ->back()
            ->with('success', 'Playlist published. This finalises a version for publishing — it does not send content to TVs.');
    }

    public function duplicate(Request $request, Playlist $playlist, DuplicatePlaylist $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('duplicate', $playlist);

        $copy = $action->handle($request->user(), $playlist);

        return redirect()
            ->route('app.playlists.edit', $copy)
            ->with('success', 'Playlist duplicated.');
    }

    public function archive(Request $request, Playlist $playlist, ArchivePlaylist $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('archive', $playlist);

        $action->handle($request->user(), $playlist);

        return redirect()->back()->with('success', 'Playlist archived.');
    }

    public function destroy(Request $request, Playlist $playlist, DeletePlaylist $action): RedirectResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('delete', $playlist);

        $action->handle($playlist);

        return redirect()
            ->route('app.playlists')
            ->with('success', 'Playlist deleted.');
    }

    public function bulkDestroy(Request $request, BulkDeletePlaylists $action): RedirectResponse
    {
        $this->authorize('create', Playlist::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $inWorkspace = Playlist::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $ids)
            ->exists();
        abort_unless($inWorkspace, 404);

        $result = $action->handle($request->user(), $workspace, $ids);

        return $this->bulkDeleteRedirect($result, 'Playlist', 'Playlists');
    }

    /**
     * @param  array{deleted: list<int>, failed: list<array{id: int, name: string, reason: string}>}  $result
     */
    private function bulkDeleteRedirect(array $result, string $singular, string $plural): RedirectResponse
    {
        $deletedCount = count($result['deleted']);
        $failed = $result['failed'];
        $redirect = redirect()->route('app.playlists');

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

    public function preview(Request $request, Playlist $playlist): JsonResponse
    {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('view', $playlist);

        $version = $playlist->publishedVersion ?? $this->editableVersion($playlist);
        $items = $version !== null
            ? array_values(array_filter($this->itemPayloads($version), fn (array $item) => $item['is_active']))
            : [];

        $mediaIds = [];
        foreach ($items as $item) {
            $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($item['schema'] ?? [])];
        }

        return response()->json([
            'id' => $playlist->id,
            'name' => $playlist->name,
            'orientation' => $playlist->orientation?->value,
            'status' => $playlist->status->value,
            'playlist_version_id' => $version?->id,
            'version_number' => $version?->version_number,
            'version_scope' => $version !== null && $playlist->published_version_id === $version->id
                ? 'published'
                : 'latest',
            'total_duration_seconds' => PlaylistRuntimeCalculator::totalSeconds($items),
            'items' => $items,
            'media_map' => $this->mediaMap($playlist->workspace_id, $mediaIds),
        ]);
    }

    public function publishToScreens(
        Request $request,
        Playlist $playlist,
        PublishPlaylistToScreens $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $playlist);
        $this->authorize('publishToScreens', $playlist);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer'],
        ]);

        $action->handle($request->user(), $workspace, $playlist, $data['screen_ids']);

        return redirect()->back()->with('success', 'Playlist published to selected screens.');
    }

    /**
     * Picker endpoint: published Screen Designs available for playlist items.
     */
    public function publishedDesigns(Request $request): JsonResponse
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Playlist::class);

        return response()->json($this->publishedDesignPage($request, $workspace->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function publishedDesignPage(Request $request, int $workspaceId): array
    {
        $q = trim((string) $request->input('q', ''));
        $orientation = (string) $request->input('orientation', 'all');

        $query = ScreenDesign::query()
            ->forWorkspace($workspaceId)
            ->where('status', ScreenDesignStatus::Published)
            ->whereNotNull('published_version_id')
            ->with('publishedVersion');

        if ($q !== '') {
            $query->where('name', 'ilike', '%'.$q.'%');
        }

        if ($orientation !== 'all' && in_array($orientation, ['landscape', 'portrait'], true)) {
            $query->where('orientation', $orientation);
        }

        $paginator = $query
            ->orderBy('name')
            ->orderByDesc('id')
            ->paginate(24, ['*'], 'designs_page')
            ->withQueryString();

        $mediaIds = [];
        $designs = $paginator->getCollection()->map(function (ScreenDesign $design) use (&$mediaIds): array {
            $published = $design->publishedVersion;
            $schema = LayoutSchemaNormalizer::normalize($published === null ? [] : $published->schema);
            $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($schema)];

            return [
                'id' => $design->id,
                'name' => $design->name,
                'orientation' => $design->orientation->value,
                'orientation_label' => $design->orientation->label(),
                'canvas_width' => $design->canvas_width,
                'canvas_height' => $design->canvas_height,
                'published_version_id' => $design->published_version_id,
                'published_version_number' => $published?->version_number,
                'schema' => $schema,
                'updated_at' => $design->updated_at?->toIso8601String(),
            ];
        })->values();

        return [
            'data' => $designs,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'q' => $q,
                'orientation' => $orientation,
            ],
            'media_map' => $this->mediaMap($workspaceId, $mediaIds),
        ];
    }

    /**
     * The version a draft editor should show: the latest one, published or not.
     */
    private function editableVersion(Playlist $playlist): ?PlaylistVersion
    {
        $version = $playlist->relationLoaded('versions')
            ? $playlist->versions->sortByDesc('version_number')->first()
            : $playlist->latestVersion();

        $version?->loadMissing(['items.screenDesign', 'items.screenDesignVersion']);

        return $version;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemPayloads(PlaylistVersion $version): array
    {
        $version->loadMissing(['items.screenDesign', 'items.screenDesignVersion']);

        return $version->items->map(function (PlaylistItem $item): array {
            $design = $item->screenDesign;
            $designVersion = $item->screenDesignVersion;

            return [
                'id' => $item->id,
                'position' => $item->position,
                'screen_design_id' => $item->screen_design_id,
                'screen_design_version_id' => $item->screen_design_version_id,
                'name' => $design?->name,
                'orientation' => $design?->orientation->value,
                'canvas_width' => $design?->canvas_width,
                'canvas_height' => $design?->canvas_height,
                'design_version_number' => $designVersion?->version_number,
                'duration_seconds' => $item->duration_seconds,
                'loop_count' => max(1, (int) $item->loop_count),
                'effective_duration_seconds' => PlaylistRuntimeCalculator::itemEffectiveSeconds($item),
                'transition' => $item->transition->value,
                'transition_label' => $item->transition->label(),
                'transition_speed' => $item->transition_speed->value,
                'transition_speed_label' => $item->transition_speed->label(),
                'is_active' => $item->is_active,
                'schema' => LayoutSchemaNormalizer::normalize($designVersion === null ? [] : $designVersion->schema),
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function toEditorPayload(Playlist $playlist, ?PlaylistVersion $version, array $items): array
    {
        $activeItems = array_values(array_filter($items, fn (array $item) => $item['is_active']));

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'orientation' => $playlist->orientation?->value,
            'orientation_label' => $playlist->orientation?->label(),
            'status' => $playlist->status->value,
            'status_label' => $playlist->status->label(),
            'published_version_id' => $playlist->published_version_id,
            'published_at' => $playlist->published_at?->toIso8601String(),
            'latest_version_id' => $version?->id,
            'latest_version_number' => $version?->version_number,
            'latest_published' => $version?->published_at !== null,
            'item_count' => count($items),
            'active_item_count' => count($activeItems),
            'total_duration_seconds' => PlaylistRuntimeCalculator::totalSeconds($items),
            'version_scope' => 'latest',
            'items' => $items,
            'updated_at' => $playlist->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Playlist $playlist): array
    {
        $version = $playlist->versions->sortByDesc('version_number')->first();
        $items = $version !== null ? $version->items : collect();
        $activeItems = $items->filter(fn (PlaylistItem $item) => $item->is_active)->values();
        $sequence = $activeItems->isNotEmpty() ? $activeItems : $items->values();

        $previewItems = $sequence
            ->take(8)
            ->map(function (PlaylistItem $item) {
                $previewDesign = $item->screenDesign;
                $previewVersion = $item->screenDesignVersion;

                return [
                    'screen_design_id' => $item->screen_design_id,
                    'name' => $previewDesign?->name,
                    'canvas_width' => $previewDesign?->canvas_width,
                    'canvas_height' => $previewDesign?->canvas_height,
                    'duration_seconds' => (int) $item->duration_seconds,
                    'loop_count' => max(1, (int) $item->loop_count),
                    'effective_duration_seconds' => PlaylistRuntimeCalculator::itemEffectiveSeconds($item),
                    'schema' => LayoutSchemaNormalizer::normalize($previewVersion === null ? [] : $previewVersion->schema),
                ];
            })
            ->values()
            ->all();

        $previewItem = $previewItems[0] ?? null;

        return [
            'id' => $playlist->id,
            'name' => $playlist->name,
            'description' => $playlist->description,
            'orientation' => $playlist->orientation?->value,
            'orientation_label' => $playlist->orientation?->label(),
            'status' => $playlist->status->value,
            'status_label' => $playlist->status->label(),
            'item_count' => $items->count(),
            'active_item_count' => $activeItems->count(),
            'total_duration_seconds' => PlaylistRuntimeCalculator::totalSeconds($version),
            'version_scope' => 'latest',
            'latest_version_id' => $version?->id,
            'assigned_tv_count' => (int) ($playlist->assigned_tv_count ?? 0),
            'schedule_count' => (int) ($playlist->schedule_count ?? 0),
            'published_version_id' => $playlist->published_version_id,
            'published_at' => $playlist->published_at?->toIso8601String(),
            'latest_version_number' => $version?->version_number,
            'has_unpublished_changes' => $version !== null && $version->published_at === null,
            'created_by_name' => $playlist->creator?->name,
            'preview_item' => $previewItem,
            'preview_items' => $previewItems,
            'updated_at' => $playlist->updated_at?->toIso8601String(),
            'created_at' => $playlist->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<int>  $mediaIds
     * @return array<string, array<string, mixed>>
     */
    private function mediaMap(int $workspaceId, array $mediaIds): array
    {
        return MediaMap::build($workspaceId, $mediaIds);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function screensForPublishing(Request $request): array
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        if ($workspace === null || ! $user->can('create', Deployment::class)) {
            return [];
        }

        return Screen::query()
            ->forWorkspace($workspace)
            ->orderBy('name')
            ->get(['id', 'name', 'orientation', 'operational_status'])
            ->map(fn (Screen $screen) => [
                'id' => $screen->id,
                'name' => $screen->name,
                'orientation' => $screen->orientation,
                'operational_status' => $screen->operational_status->value,
            ])
            ->values()
            ->all();
    }

    private function ensureWorkspace(Request $request, Playlist $playlist): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $playlist->workspace_id === (int) $workspace->id,
            404,
        );
    }
}
