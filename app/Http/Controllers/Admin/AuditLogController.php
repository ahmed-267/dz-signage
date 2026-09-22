<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\ListPagination;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $q = trim((string) $request->input('q', ''));
        $action = trim((string) $request->input('action', ''));
        $entityType = trim((string) $request->input('entity_type', ''));
        $workspaceId = $request->input('workspace_id');
        $sort = ListPagination::sort(
            $request,
            ['created', 'action', 'entity_type'],
            'created',
            'desc',
        );

        $query = AuditLog::query()
            ->with(['actor:id,name,email', 'workspace:id,name']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('action', 'ilike', $term)
                    ->orWhere('entity_type', 'ilike', $term)
                    ->orWhereHas('actor', fn ($a) => $a->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
            });
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        if ($workspaceId !== null && $workspaceId !== '' && ctype_digit((string) $workspaceId)) {
            $query->where('workspace_id', (int) $workspaceId);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'action' => $query->orderBy('action', $direction)->orderByDesc('id'),
            'entity_type' => $query->orderBy('entity_type', $direction)->orderByDesc('id'),
            default => $query->orderBy('created_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 50);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'subject_type' => $log->entity_type,
                'subject_id' => $log->entity_id,
                'subject' => $log->entity_type.($log->entity_id ? '#'.$log->entity_id : ''),
                'actor_name' => $log->actor?->name,
                'actor_email' => $log->actor?->email,
                'workspace_name' => $log->workspace?->name,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        );

        $actionOptions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit(100)
            ->pluck('action')
            ->map(fn (string $a) => ['value' => $a, 'label' => $a])
            ->values()
            ->all();

        return Inertia::render('admin/audit-log/index', [
            'entries' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'action' => $action,
                'entity_type' => $entityType,
                'workspace_id' => $workspaceId !== null && $workspaceId !== '' ? (int) $workspaceId : null,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'actions' => $actionOptions,
        ]);
    }
}
