<?php

namespace App\Http\Controllers\App;

use App\Actions\Templates\ToggleTemplateFavourite;
use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateStatus;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\IndexTemplateRequest;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Models\TemplateFavourite;
use App\Support\Rendering\LayoutSchemaNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer Template Library — browse, preview, favourite published platform templates.
 * Customers never create or edit master Templates.
 */
class TemplateController extends Controller
{
    public function index(IndexTemplateRequest $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        $this->authorize('viewAny', Template::class);

        $filters = $request->filters();

        $publishedPlatform = fn ($query) => $query
            ->platform()
            ->published()
            ->whereNotNull('published_version_id');

        $query = Template::query()
            ->with(['creator:id,name', 'publishedVersion'])
            ->withExists([
                'favourites as is_favourited' => fn ($q) => $q->where('user_id', $user->id),
            ]);

        $query = match ($filters['tab']) {
            'favourites' => $query->whereIn(
                'id',
                TemplateFavourite::query()->where('user_id', $user->id)->select('template_id'),
            )->tap($publishedPlatform),
            default => $query->tap($publishedPlatform),
        };

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('name', 'ilike', $term)
                    ->orWhere('description', 'ilike', $term);
            });
        }

        if ($filters['industry'] !== 'all') {
            $query->where('industry', $filters['industry']);
        }

        if ($filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if ($filters['orientation'] !== 'all') {
            $query->where('orientation', $filters['orientation']);
        }

        if ($filters['theme'] !== 'all') {
            $query->where('theme', $filters['theme']);
        }

        $paginator = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $publishedCount = Template::query()->platform()->published()->count();
        $favouritesCount = TemplateFavourite::query()
            ->where('user_id', $user->id)
            ->whereHas('template', fn ($q) => $q->platform()->published())
            ->count();

        return Inertia::render('app/templates/index', [
            'templates' => [
                'data' => $paginator->getCollection()->map(fn (Template $template) => $this->toListItem($template))->values(),
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
                'all' => $publishedCount,
                'dz' => $publishedCount,
                'favourites' => $favouritesCount,
            ],
            'use_template_available' => $user->can('create', ScreenDesign::class),
            'categories' => collect(TemplateCategory::cases())->map(fn (TemplateCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'orientations' => collect(TemplateOrientation::cases())->map(fn (TemplateOrientation $o) => [
                'value' => $o->value,
                'label' => $o->label(),
            ])->values(),
            'themes' => collect(TemplateTheme::cases())->map(fn (TemplateTheme $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'background' => $t->defaultBackground(),
            ])->values(),
            'industries' => collect(WorkspaceIndustry::cases())->map(fn (WorkspaceIndustry $i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
        ]);
    }

    public function toggleFavourite(
        Request $request,
        Template $template,
        ToggleTemplateFavourite $action,
    ): RedirectResponse {
        abort_unless($template->isPlatform() && $template->status === TemplateStatus::Published, 404);
        $this->authorize('favourite', $template);

        $result = $action->handle($request->user(), $template);

        return redirect()
            ->back()
            ->with('success', $result['favourited'] ? 'Added to favourites.' : 'Removed from favourites.');
    }

    public function preview(Request $request, Template $template): JsonResponse
    {
        abort_unless($template->isPlatform() && $template->status === TemplateStatus::Published, 404);
        $this->authorize('view', $template);

        $version = $template->publishedVersion
            ?? $template->versions()->orderByDesc('version_number')->first();

        return response()->json([
            'id' => $template->id,
            'name' => $template->name,
            'orientation' => $template->orientation->value,
            'theme' => $template->theme->value,
            'schema' => $this->normalizedSchema($version?->schema),
            'version_number' => $version?->version_number,
            'canvas_width' => $template->canvas_width,
            'canvas_height' => $template->canvas_height,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toListItem(Template $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'category' => $template->category->value,
            'category_label' => $template->category->label(),
            'industry' => $template->industry,
            'orientation' => $template->orientation->value,
            'orientation_label' => $template->orientation->label(),
            'theme' => $template->theme->value,
            'theme_label' => $template->theme->label(),
            'status' => $template->status->value,
            'status_label' => $template->status->label(),
            'canvas_width' => $template->canvas_width,
            'canvas_height' => $template->canvas_height,
            'is_platform' => true,
            'is_favourited' => (bool) ($template->is_favourited ?? false),
            'thumbnail_path' => $template->thumbnail_path,
            'schema' => $this->normalizedSchema($template->publishedVersion?->schema),
            'created_by_name' => $template->creator?->name,
            'created_at' => $template->created_at?->toIso8601String(),
            'updated_at' => $template->updated_at?->toIso8601String(),
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
}
