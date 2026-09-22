<?php

namespace App\Http\Controllers\App;

use App\Actions\ScreenDesigns\BulkDeleteScreenDesigns;
use App\Actions\ScreenDesigns\CreateBlankScreenDesign;
use App\Actions\ScreenDesigns\CreateScreenDesignFromTemplate;
use App\Actions\ScreenDesigns\DeleteScreenDesign;
use App\Actions\ScreenDesigns\DuplicateScreenDesign;
use App\Actions\ScreenDesigns\PruneAbandonedScreenDesigns;
use App\Actions\ScreenDesigns\PublishScreenDesign;
use App\Actions\ScreenDesigns\SaveScreenDesignDraft;
use App\Enums\MediaType;
use App\Enums\ScreenDesignStatus;
use App\Enums\TemplateOrientation;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\MediaAsset;
use App\Models\Screen;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Support\ProductLabels;
use App\Support\Rendering\LayoutSchemaNormalizer;
use App\Support\ScreenDesigns\ScreenDesignContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScreenDesignController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', ScreenDesign::class);

        // Lightweight cleanup of abandoned empty drafts so the library stays usable.
        app(PruneAbandonedScreenDesigns::class)->handle($workspace);

        $q = trim((string) $request->input('q', ''));
        $orientation = (string) $request->input('orientation', 'all');
        $status = (string) $request->input('status', 'all');
        $sort = (string) $request->input('sort', 'updated_desc');

        // Library filters expose All / Draft / Archived only. Published designs
        // remain visible under All; status=published is treated as All.
        $allowedStatusFilters = [
            'all',
            ScreenDesignStatus::Draft->value,
            ScreenDesignStatus::Archived->value,
        ];
        if (! in_array($status, $allowedStatusFilters, true)) {
            $status = 'all';
        }

        $query = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->with([
                'sourceTemplate:id,name',
                'creator:id,name',
                'versions' => fn ($q) => $q->orderByDesc('version_number'),
            ]);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where('name', 'ilike', $term);
        }

        if ($orientation !== 'all' && in_array($orientation, ['landscape', 'portrait'], true)) {
            $query->where('orientation', $orientation);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $query = match ($sort) {
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'created_desc' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByDesc('updated_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate(24)->withQueryString();

        return Inertia::render('app/screen-designs/index', [
            'designs' => [
                'data' => $paginator->getCollection()->map(fn (ScreenDesign $d) => $this->toListItem($d))->values(),
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
                'orientation' => $orientation,
                'status' => $status,
                'sort' => $sort,
            ],
            'can_manage' => $user->can('create', ScreenDesign::class),
            'can_publish_to_screens' => $user->can('create', Deployment::class),
            'screens' => $user->can('create', Deployment::class)
                ? Screen::query()
                    ->forWorkspace($workspace)
                    ->orderBy('name')
                    ->get(['id', 'name', 'orientation', 'operational_status'])
                    ->map(fn (Screen $screen) => [
                        'id' => $screen->id,
                        'name' => $screen->name,
                        'orientation' => $screen->orientation,
                        'operational_status' => $screen->operational_status->value,
                    ])->values()
                : [],
        ]);
    }

    public function storeBlank(Request $request, CreateBlankScreenDesign $action): RedirectResponse
    {
        $this->authorize('create', ScreenDesign::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'orientation' => ['required', Rule::enum(TemplateOrientation::class)],
        ], [], [
            'name' => ProductLabels::SCREEN_NAME,
        ]);

        $orientationEnum = TemplateOrientation::from($data['orientation']);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = ScreenDesignContent::defaultNameForOrientation($orientationEnum->value);
        }

        $design = $action->handle(
            $request->user(),
            $workspace,
            $name,
            $orientationEnum,
        );

        return redirect()
            ->route('app.screen_designs.edit', $design)
            ->with('success', ProductLabels::SCREEN_DESIGN_SINGULAR.' created.');
    }

    public function storeFromTemplate(
        Request $request,
        Template $template,
        CreateScreenDesignFromTemplate $action,
    ): RedirectResponse {
        $this->authorize('useTemplate', $template);
        $this->authorize('create', ScreenDesign::class);

        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $design = $action->handle($request->user(), $workspace, $template);

        return redirect()
            ->route('app.screen_designs.edit', $design)
            ->with('success', 'Screen design created from template.');
    }

    public function edit(Request $request, ScreenDesign $screenDesign): Response
    {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('view', $screenDesign);

        $screenDesign->load(['versions' => fn ($q) => $q->orderByDesc('version_number'), 'sourceTemplate:id,name']);
        $latest = $screenDesign->latestVersion();
        $canEdit = $request->user()->can('update', $screenDesign);

        $mediaIds = ScreenDesign::mediaIdsFromSchema($latest !== null ? $latest->schema : []);
        $mediaAssets = MediaAsset::query()
            ->where('workspace_id', $screenDesign->workspace_id)
            ->whereIn('id', $mediaIds ?: [0])
            ->get()
            ->keyBy('id');

        $picker = MediaAsset::query()
            ->where('workspace_id', $screenDesign->workspace_id)
            ->whereIn('type', [MediaType::Image, MediaType::Video, MediaType::Logo, MediaType::Text, MediaType::Link])
            ->orderByDesc('updated_at')
            ->limit(48)
            ->get();

        return Inertia::render('app/screen-designs/edit', [
            'design' => $this->toEditorPayload($screenDesign, $latest?->schema),
            'can_edit' => $canEdit,
            'media_map' => $mediaAssets->map(fn (MediaAsset $m) => $this->toMediaRef($m))->all(),
            'media_picker' => $picker->map(fn (MediaAsset $m) => $this->toMediaRef($m))->values(),
        ]);
    }

    public function update(
        Request $request,
        ScreenDesign $screenDesign,
        SaveScreenDesignDraft $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('update', $screenDesign);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'schema' => ['sometimes', 'nullable', 'array'],
        ], [], [
            'name' => ProductLabels::SCREEN_NAME,
        ]);

        $action->handle($request->user(), $screenDesign, $data);

        return redirect()
            ->back()
            ->with('success', 'Draft saved.');
    }

    public function publish(
        Request $request,
        ScreenDesign $screenDesign,
        PublishScreenDesign $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('publish', $screenDesign);

        $action->handle($request->user(), $screenDesign);

        return redirect()
            ->back()
            ->with('success', 'Design published. This finalises a version for future playlists — it does not send content to TVs.');
    }

    public function duplicate(
        Request $request,
        ScreenDesign $screenDesign,
        DuplicateScreenDesign $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('duplicate', $screenDesign);

        $copy = $action->handle($request->user(), $screenDesign);

        return redirect()
            ->route('app.screen_designs.edit', $copy)
            ->with('success', 'Screen design duplicated.');
    }

    public function destroy(
        Request $request,
        ScreenDesign $screenDesign,
        DeleteScreenDesign $action,
    ): RedirectResponse {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('delete', $screenDesign);

        $action->handle($screenDesign);

        return redirect()
            ->route('app.screen_designs')
            ->with('success', ProductLabels::SCREEN_DESIGN_SINGULAR.' deleted.');
    }

    public function bulkDestroy(Request $request, BulkDeleteScreenDesigns $action): RedirectResponse
    {
        $this->authorize('create', ScreenDesign::class);
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $inWorkspace = ScreenDesign::query()
            ->forWorkspace($workspace)
            ->whereIn('id', $ids)
            ->exists();
        abort_unless($inWorkspace, 404);

        $result = $action->handle($request->user(), $workspace, $ids);

        return $this->bulkDeleteRedirect(
            $result,
            ProductLabels::SCREEN_DESIGN_SINGULAR,
            ProductLabels::SCREEN_DESIGN_PLURAL,
        );
    }

    public function rename(Request $request, ScreenDesign $screenDesign): RedirectResponse
    {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('update', $screenDesign);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [], [
            'name' => ProductLabels::SCREEN_NAME,
        ]);

        $screenDesign->forceFill([
            'name' => trim($data['name']),
            'updated_by' => $request->user()->id,
        ])->save();

        return redirect()->back()->with('success', ProductLabels::SCREEN_DESIGN_SINGULAR.' renamed.');
    }

    /**
     * @param  array{deleted: list<int>, failed: list<array{id: int, name: string, reason: string}>}  $result
     */
    private function bulkDeleteRedirect(array $result, string $singular, string $plural): RedirectResponse
    {
        $deletedCount = count($result['deleted']);
        $failed = $result['failed'];
        $redirect = redirect()->route('app.screen_designs');

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

    public function preview(Request $request, ScreenDesign $screenDesign): Response|JsonResponse
    {
        $this->ensureWorkspace($request, $screenDesign);
        $this->authorize('view', $screenDesign);

        $version = $screenDesign->latestVersion() ?? $screenDesign->publishedVersion;
        $schema = $version !== null
            ? LayoutSchemaNormalizer::normalize($version->schema)
            : [];
        $mediaIds = ScreenDesign::mediaIdsFromSchema($schema);
        $mediaAssets = MediaAsset::query()
            ->where('workspace_id', $screenDesign->workspace_id)
            ->whereIn('id', $mediaIds ?: [0])
            ->get()
            ->keyBy('id');

        return response()->json([
            'id' => $screenDesign->id,
            'name' => $screenDesign->name,
            'orientation' => $screenDesign->orientation->value,
            'schema' => $schema,
            'media_map' => $mediaAssets->map(fn (MediaAsset $m) => $this->toMediaRef($m))->all(),
            'canvas_width' => $screenDesign->canvas_width,
            'canvas_height' => $screenDesign->canvas_height,
        ]);
    }

    private function ensureWorkspace(Request $request, ScreenDesign $design): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $design->workspace_id === (int) $workspace->id,
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(ScreenDesign $design): array
    {
        $latest = $design->relationLoaded('versions')
            ? $design->versions->sortByDesc('version_number')->first()
            : $design->latestVersion();

        return [
            'id' => $design->id,
            'name' => $design->name,
            'orientation' => $design->orientation->value,
            'orientation_label' => $design->orientation->label(),
            'status' => $design->status->value,
            'status_label' => $design->status->label(),
            'canvas_width' => $design->canvas_width,
            'canvas_height' => $design->canvas_height,
            'source_template_name' => $design->sourceTemplate?->name,
            'schema' => $this->normalizedSchema($latest?->schema),
            'created_by_name' => $design->creator?->name,
            'updated_at' => $design->updated_at?->toIso8601String(),
            'created_at' => $design->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    private function toEditorPayload(ScreenDesign $design, ?array $schema): array
    {
        $latest = $design->versions->first();

        return [
            'id' => $design->id,
            'name' => $design->name,
            'orientation' => $design->orientation->value,
            'status' => $design->status->value,
            'canvas_width' => $design->canvas_width,
            'canvas_height' => $design->canvas_height,
            'source_template_id' => $design->source_template_id,
            'source_template_name' => $design->sourceTemplate?->name,
            'published_version_id' => $design->published_version_id,
            'latest_version_number' => $latest?->version_number,
            'latest_published' => $latest?->published_at !== null,
            'schema' => $this->normalizedSchema($schema),
            'updated_at' => $design->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>|null
     */
    private function normalizedSchema(?array $schema): ?array
    {
        if ($schema === null) {
            return null;
        }

        return LayoutSchemaNormalizer::normalize($schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function toMediaRef(MediaAsset $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'type' => $media->type->value,
            'url' => $media->publicUrl() ?? $media->url,
            'text_content' => $media->text_content,
            'mime_type' => $media->mime_type,
            'width' => $media->width,
            'height' => $media->height,
        ];
    }
}
