<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformErrorCategory;
use App\Http\Controllers\Controller;
use App\Models\PlatformError;
use App\Support\ListPagination;
use App\Support\Platform\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformErrorController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $category = (string) $request->input('category', 'all');
        $resolved = (string) $request->input('resolved', 'unresolved');
        $q = trim((string) $request->input('q', ''));
        $perPage = ListPagination::perPage($request, 20);

        $query = PlatformError::query()
            ->with(['workspace:id,name', 'screen:id,name', 'resolver:id,name']);

        if ($category !== 'all' && in_array($category, PlatformErrorCategory::values(), true)) {
            $query->where('category', $category);
        }

        if ($resolved === 'unresolved') {
            $query->unresolved();
        } elseif ($resolved === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where('message', 'ilike', $term);
        }

        $paginator = $query
            ->latest('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (PlatformError $error) => [
                'id' => $error->id,
                'category' => $error->category->value,
                'category_label' => $error->category->label(),
                'message' => $error->message,
                'workspace_id' => $error->workspace_id,
                'workspace_name' => $error->workspace?->name,
                'screen_id' => $error->screen_id,
                'screen_name' => $error->screen?->name,
                'occurred_at' => $error->occurred_at->toIso8601String(),
                'resolved_at' => $error->resolved_at?->toIso8601String(),
                'resolved' => $error->resolved_at !== null,
                'resolved_by_name' => $error->resolver?->name,
            ]),
        );

        return Inertia::render('admin/errors/index', [
            'errors' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'category' => $category,
                'resolved' => $resolved,
                'per_page' => $perPage,
            ],
            'categories' => array_map(
                fn (PlatformErrorCategory $c) => ['value' => $c->value, 'label' => $c->label()],
                PlatformErrorCategory::cases(),
            ),
        ]);
    }

    public function resolve(Request $request, PlatformError $error): RedirectResponse
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        if ($error->resolved_at === null) {
            $error->forceFill([
                'resolved_at' => now(),
                'resolved_by' => $request->user()->id,
            ])->save();

            AuditLogger::record(
                $request->user(),
                'platform_error.resolved',
                'platform_error',
                $error->id,
                $error->workspace_id,
                ['category' => $error->category->value],
            );
        }

        return back()->with('success', 'Error marked resolved.');
    }
}
