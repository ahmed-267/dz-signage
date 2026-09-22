<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Templates\ArchiveTemplate;
use App\Actions\Templates\CreateTemplate;
use App\Actions\Templates\DeleteTemplate;
use App\Actions\Templates\PublishTemplate;
use App\Actions\Templates\SaveTemplateDraft;
use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\StorePlatformTemplateRequest;
use App\Http\Requests\Templates\UpdatePlatformTemplateRequest;
use App\Models\MediaAsset;
use App\Models\ScreenDesign;
use App\Models\Template;
use App\Support\ListPagination;
use App\Support\Rendering\LayoutSchemaNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('createPlatform', Template::class);

        $perPage = ListPagination::perPage($request, 20);

        $paginator = Template::query()
            ->platform()
            ->with(['creator:id,name', 'publishedVersion', 'latestVersionRecord'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $mediaIds = [];

        $paginator->through(function (Template $template) use (&$mediaIds) {
            $version = $template->publishedVersion ?? $template->latestVersionRecord;
            $schema = $version !== null
                ? LayoutSchemaNormalizer::normalize($version->schema)
                : null;

            if (is_array($schema)) {
                $mediaIds = [...$mediaIds, ...ScreenDesign::mediaIdsFromSchema($schema)];
            }

            return [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category->value,
                'category_label' => $template->category->label(),
                'industry' => $template->industry,
                'orientation' => $template->orientation->value,
                'theme' => $template->theme->value,
                'theme_label' => $template->theme->label(),
                'status' => $template->status->value,
                'status_label' => $template->status->label(),
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'schema' => $schema,
                'created_by_name' => $template->creator?->name,
                'updated_at' => $template->updated_at?->toIso8601String(),
            ];
        });

        $mediaIds = array_values(array_unique(array_filter($mediaIds)));
        $mediaMap = $mediaIds === []
            ? []
            : MediaAsset::query()
                ->whereIn('id', $mediaIds)
                ->get()
                ->keyBy('id')
                ->map(fn (MediaAsset $media) => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'type' => $media->type->value,
                    'url' => $media->publicUrl() ?? $media->url,
                    'text_content' => $media->text_content,
                    'mime_type' => $media->mime_type,
                    'width' => $media->width,
                    'height' => $media->height,
                ])
                ->all();

        return Inertia::render('admin/templates/index', [
            'templates' => ListPagination::inertia($paginator),
            'media_map' => $mediaMap,
            'filters' => [
                'per_page' => $perPage,
            ],
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
            ])->values(),
            'industries' => collect(WorkspaceIndustry::cases())->map(fn (WorkspaceIndustry $i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
        ]);
    }

    public function store(StorePlatformTemplateRequest $request, CreateTemplate $action): RedirectResponse
    {
        $template = $action->handle($request->user(), null, [
            'name' => $request->validated('name'),
            'orientation' => $request->validated('orientation'),
            'theme' => $request->validated('theme'),
            'category' => $request->validated('category') ?? TemplateCategory::Other->value,
            'industry' => $request->validated('industry'),
            'description' => $request->validated('description'),
        ]);

        return redirect()
            ->route('admin.templates.builder', $template)
            ->with('success', 'Platform template created.');
    }

    public function edit(Template $template): Response
    {
        abort_unless($template->isPlatform(), 404);
        $this->authorize('update', $template);

        $template->load(['versions' => fn ($q) => $q->orderByDesc('version_number'), 'creator:id,name']);
        $latest = $template->latestVersion();

        return Inertia::render('admin/templates/edit', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category->value,
                'industry' => $template->industry,
                'orientation' => $template->orientation->value,
                'theme' => $template->theme->value,
                'status' => $template->status->value,
                'canvas_width' => $template->canvas_width,
                'canvas_height' => $template->canvas_height,
                'published_version_id' => $template->published_version_id,
                'latest_version_number' => $latest?->version_number,
                'latest_published' => $latest?->published_at !== null,
                'schema' => $latest !== null
                    ? LayoutSchemaNormalizer::normalize($latest->schema)
                    : null,
            ],
            'categories' => collect(TemplateCategory::cases())->map(fn (TemplateCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values(),
            'themes' => collect(TemplateTheme::cases())->map(fn (TemplateTheme $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ])->values(),
            'industries' => collect(WorkspaceIndustry::cases())->map(fn (WorkspaceIndustry $i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
        ]);
    }

    public function update(
        UpdatePlatformTemplateRequest $request,
        Template $template,
        SaveTemplateDraft $action,
    ): RedirectResponse {
        abort_unless($template->isPlatform(), 404);
        $this->authorize('update', $template);

        $action->handle($request->user(), $template, $request->validated());

        return redirect()
            ->route('admin.templates.builder', $template)
            ->with('success', 'Platform template saved.');
    }

    public function publish(
        Request $request,
        Template $template,
        PublishTemplate $action,
    ): RedirectResponse {
        abort_unless($template->isPlatform(), 404);
        $this->authorize('publish', $template);

        $action->handle($request->user(), $template);

        return redirect()
            ->back()
            ->with('success', 'Platform template published.');
    }

    public function archive(
        Request $request,
        Template $template,
        ArchiveTemplate $action,
    ): RedirectResponse {
        abort_unless($template->isPlatform(), 404);
        $this->authorize('archive', $template);

        $action->handle($request->user(), $template);

        return redirect()
            ->route('admin.templates')
            ->with('success', 'Platform template archived.');
    }

    public function destroy(
        Request $request,
        Template $template,
        DeleteTemplate $action,
    ): RedirectResponse {
        abort_unless($template->isPlatform(), 404);
        $this->authorize('delete', $template);

        $action->handle($template);

        return redirect()
            ->route('admin.templates')
            ->with('success', 'Platform template deleted.');
    }
}
