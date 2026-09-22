<?php

namespace App\Http\Controllers\App;

use App\Actions\Media\CreateMediaAsset;
use App\Actions\Media\DeleteMediaAsset;
use App\Actions\Media\DuplicateMediaAsset;
use App\Actions\Media\ReplaceMediaAsset;
use App\Actions\Media\UpdateMediaAsset;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\IndexMediaAssetRequest;
use App\Http\Requests\Media\ReplaceMediaAssetRequest;
use App\Http\Requests\Media\StoreMediaAssetRequest;
use App\Http\Requests\Media\UpdateMediaAssetRequest;
use App\Models\MediaAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function index(IndexMediaAssetRequest $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $filters = $request->filters();

        $query = MediaAsset::query()
            ->forWorkspace($workspace)
            ->with(['creator:id,name']);

        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'ilike', $term)
                    ->orWhere('original_filename', 'ilike', $term)
                    ->orWhere('text_content', 'ilike', $term)
                    ->orWhere('url', 'ilike', $term);
            });
        }

        $query = match ($filters['sort']) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'name_desc' => $query->orderByDesc('name')->orderBy('id'),
            'largest' => $query->orderByDesc('size_bytes')->orderBy('id'),
            'smallest' => $query->orderBy('size_bytes')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $paginator = $query->paginate(24)->withQueryString();

        $typeCounts = MediaAsset::query()
            ->forWorkspace($workspace)
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $totalBytes = (int) MediaAsset::query()
            ->forWorkspace($workspace)
            ->sum('size_bytes');

        return Inertia::render('app/media/index', [
            'media' => [
                'data' => $paginator->getCollection()->map(fn (MediaAsset $asset) => $this->toListItem($asset))->values(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url(max($paginator->lastPage(), 1)),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ],
            'filters' => $filters,
            'counts' => [
                'all' => (int) $typeCounts->sum(),
                'image' => (int) ($typeCounts[MediaType::Image->value] ?? 0),
                'video' => (int) ($typeCounts[MediaType::Video->value] ?? 0),
                'text' => (int) ($typeCounts[MediaType::Text->value] ?? 0),
                'logo' => (int) ($typeCounts[MediaType::Logo->value] ?? 0),
                'document' => (int) ($typeCounts[MediaType::Document->value] ?? 0),
                'link' => (int) ($typeCounts[MediaType::Link->value] ?? 0),
            ],
            'total_bytes' => $totalBytes,
            'types' => collect(MediaType::cases())->map(fn (MediaType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
        ]);
    }

    public function store(StoreMediaAssetRequest $request, CreateMediaAsset $action): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 403);

        $action->handle($request->user(), $workspace, [
            'type' => $request->validated('type'),
            'name' => $request->validated('name'),
            'file' => $request->file('file'),
            'text_content' => $request->validated('text_content'),
            'url' => $request->validated('url'),
            'duration_seconds' => $request->validated('duration_seconds'),
            'width' => $request->validated('width'),
            'height' => $request->validated('height'),
        ]);

        return redirect()
            ->route('app.media', $this->preserveListQuery($request))
            ->with('success', 'Media added.');
    }

    public function update(
        UpdateMediaAssetRequest $request,
        MediaAsset $media,
        UpdateMediaAsset $action,
    ): RedirectResponse {
        $this->ensureCurrentWorkspace($request, $media);
        $this->authorize('update', $media);

        $action->handle($request->user(), $media, $request->validated());

        return redirect()
            ->route('app.media', $this->preserveListQuery($request))
            ->with('success', 'Media updated.');
    }

    public function replace(
        ReplaceMediaAssetRequest $request,
        MediaAsset $media,
        ReplaceMediaAsset $action,
    ): RedirectResponse {
        $this->ensureCurrentWorkspace($request, $media);
        $this->authorize('replace', $media);

        $action->handle($request->user(), $media, [
            'file' => $request->file('file'),
            'name' => $request->validated('name'),
            'duration_seconds' => $request->validated('duration_seconds'),
            'width' => $request->validated('width'),
            'height' => $request->validated('height'),
        ]);

        return redirect()
            ->route('app.media', $this->preserveListQuery($request))
            ->with('success', 'Media replaced.');
    }

    public function duplicate(
        Request $request,
        MediaAsset $media,
        DuplicateMediaAsset $action,
    ): RedirectResponse {
        $this->ensureCurrentWorkspace($request, $media);
        $this->authorize('duplicate', $media);

        $action->handle($request->user(), $media);

        return redirect()
            ->route('app.media', $this->preserveListQuery($request))
            ->with('success', 'Media duplicated.');
    }

    public function destroy(
        Request $request,
        MediaAsset $media,
        DeleteMediaAsset $action,
    ): RedirectResponse {
        $this->ensureCurrentWorkspace($request, $media);
        $this->authorize('delete', $media);

        $action->handle($media);

        return redirect()
            ->route('app.media', $this->preserveListQuery($request))
            ->with('success', 'Media deleted.');
    }

    public function download(Request $request, MediaAsset $media): StreamedResponse
    {
        $this->ensureCurrentWorkspace($request, $media);
        $this->authorize('download', $media);

        abort_unless($media->hasStoredFile(), 404);

        $disk = Storage::disk($media->storage_disk);
        abort_unless($disk->exists($media->storage_path), 404, 'File not found.');

        $downloadName = $media->original_filename ?: ($media->name.($media->extension ? '.'.$media->extension : ''));

        return $disk->download($media->storage_path, $downloadName);
    }

    private function ensureCurrentWorkspace(Request $request, MediaAsset $media): void
    {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $media->workspace_id === (int) $workspace->id,
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function preserveListQuery(Request $request): array
    {
        return $request->only(['q', 'type', 'sort', 'view', 'page']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(MediaAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'type' => $asset->type->value,
            'type_label' => $asset->type->label(),
            'name' => $asset->name,
            'original_filename' => $asset->original_filename,
            'mime_type' => $asset->mime_type,
            'extension' => $asset->extension,
            'size_bytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'dimensions' => $asset->dimensionsLabel(),
            'duration_seconds' => $asset->duration_seconds,
            'text_content' => $asset->text_content,
            'url' => $asset->url,
            'preview_url' => $asset->publicUrl(),
            'download_url' => $asset->hasStoredFile()
                ? route('app.media.download', $asset)
                : null,
            'is_file_based' => $asset->type->isFileBased(),
            'is_editable_content' => $asset->type->isEditableContent(),
            'created_by_name' => $asset->creator?->name,
            'created_at' => $asset->created_at?->toIso8601String(),
            'updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }
}
