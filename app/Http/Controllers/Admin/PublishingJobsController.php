<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentContentType;
use App\Enums\DeploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Support\ListPagination;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform read-only view of Deployment activity. Not a job queue.
 */
class PublishingJobsController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = (string) $request->input('status', 'all');
        $contentType = (string) $request->input('content_type', 'all');
        $workspaceId = $request->input('workspace_id');
        $perPage = ListPagination::perPage($request, 20);

        $query = Deployment::query()
            ->with([
                'workspace:id,name',
                'screen:id,name,workspace_id',
                'screen.devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'screenDesign:id,name',
                'playlist:id,name',
                'screenDesignVersion:id,version_number',
                'playlistVersion:id,version_number',
                'deployer:id,name',
            ]);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->whereHas('workspace', fn ($w) => $w->where('name', 'ilike', $term))
                    ->orWhereHas('screen', fn ($s) => $s->where('name', 'ilike', $term))
                    ->orWhereHas('screenDesign', fn ($d) => $d->where('name', 'ilike', $term))
                    ->orWhereHas('playlist', fn ($p) => $p->where('name', 'ilike', $term));
            });
        }

        if ($status !== 'all' && in_array($status, DeploymentStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($contentType !== 'all' && in_array($contentType, DeploymentContentType::values(), true)) {
            $query->where('content_type', $contentType);
        }

        if ($workspaceId !== null && $workspaceId !== '' && ctype_digit((string) $workspaceId)) {
            $query->where('workspace_id', (int) $workspaceId);
        }

        $paginator = $query
            ->orderByDesc('deployed_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Deployment $deployment) => $this->deploymentPayload($deployment))
                ->values(),
        );

        return Inertia::render('admin/publishing-jobs/index', [
            'deployments' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'content_type' => $contentType,
                'workspace_id' => $workspaceId !== null && $workspaceId !== '' ? (int) $workspaceId : null,
                'per_page' => $perPage,
            ],
            'statuses' => array_map(
                fn (DeploymentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                DeploymentStatus::cases(),
            ),
            'content_types' => array_map(
                fn (DeploymentContentType $t) => ['value' => $t->value, 'label' => $t->label()],
                DeploymentContentType::cases(),
            ),
        ]);
    }

    public function show(Deployment $deployment): Response
    {
        $deployment->load([
            'workspace:id,name',
            'screen:id,name,workspace_id',
            'screen.devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
            'screenDesign:id,name',
            'playlist:id,name',
            'screenDesignVersion:id,version_number',
            'playlistVersion:id,version_number',
            'deployer:id,name,email',
        ]);

        return Inertia::render('admin/publishing-jobs/show', [
            'deployment' => $this->deploymentPayload($deployment),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function deploymentPayload(Deployment $deployment): array
    {
        $device = $deployment->screen?->devices?->first();
        $sync = $deployment->status === DeploymentStatus::Active
            ? ScreenPresence::contentSyncState($deployment, $device)
            : null;

        return [
            'id' => $deployment->id,
            'workspace_id' => $deployment->workspace_id,
            'workspace_name' => $deployment->workspace?->name,
            'screen_name' => $deployment->screen?->name,
            'content_name' => $deployment->contentName(),
            'content_type' => $deployment->content_type->value,
            'content_type_label' => $deployment->content_type->label(),
            'version_number' => $deployment->contentVersionNumber(),
            'status' => $deployment->status->value,
            'status_label' => $deployment->status->label(),
            'sync_state' => $sync?->value,
            'sync_label' => $sync?->label(),
            'deployed_by_name' => $deployment->deployer?->name,
            'deployed_at' => $deployment->deployed_at?->toIso8601String(),
            'superseded_at' => $deployment->superseded_at?->toIso8601String(),
        ];
    }
}
