<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Models\ScreenHeartbeat;
use App\Models\Workspace;
use App\Support\ListPagination;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreenController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $filter = (string) $request->input('filter', 'all');
        $sort = ListPagination::sort(
            $request,
            ['name', 'workspace', 'created'],
            'created',
            'desc',
        );

        $query = Screen::query()
            ->with([
                'workspace:id,name',
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->with('screenDesign:id,name,orientation')
                    ->latest('id'),
            ]);

        if ($q !== '') {
            $query->where(function ($inner) use ($q): void {
                $inner->where('name', 'ilike', '%'.$q.'%')
                    ->orWhereHas('workspace', fn ($w) => $w->where('name', 'ilike', '%'.$q.'%'));
            });
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'name' => $query->orderBy('name', $direction)->orderByDesc('id'),
            'workspace' => $query->orderBy(
                Workspace::query()->select('name')->whereColumn('workspaces.id', 'screens.workspace_id'),
                $direction,
            )->orderByDesc('id'),
            default => $query->orderBy('created_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 20);

        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (Screen $screen) => $this->toRow($screen))
                ->when($filter !== 'all', function ($collection) use ($filter) {
                    return $collection->filter(function (array $row) use ($filter) {
                        return match ($filter) {
                            'online' => $row['network_state'] === 'online',
                            'offline' => $row['network_state'] === 'offline',
                            'active' => $row['operational_status'] === 'active',
                            'inactive' => $row['operational_status'] === 'inactive',
                            'connected' => $row['pairing_state'] === 'connected',
                            'disconnected' => $row['pairing_state'] === 'disconnected',
                            'attention' => $row['health'] === 'attention',
                            default => true,
                        };
                    })->values();
                })
        );

        return Inertia::render('admin/screens/index', [
            'screens' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'filter' => $filter,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Screen $screen): Response
    {
        $screen->load([
            'workspace:id,name',
            'devices' => fn ($q) => $q->orderByDesc('paired_at'),
            'deployments' => fn ($q) => $q
                ->with(['screenDesign:id,name,orientation', 'screenDesignVersion:id,version_number'])
                ->latest('id')
                ->limit(8),
        ]);

        $device = $screen->devices->first(fn (ScreenDevice $d) => $d->revoked_at === null);
        $active = $screen->deployments->first();
        $state = ScreenPresence::snapshot($screen, $device, $active);

        $heartbeats = ScreenHeartbeat::query()
            ->where('screen_id', $screen->id)
            ->orderByDesc('recorded_at')
            ->limit(20)
            ->get()
            ->map(fn (ScreenHeartbeat $hb) => [
                'id' => $hb->id,
                'recorded_at' => $hb->recorded_at->toIso8601String(),
                'playback_state' => $hb->playback_state,
                'error_code' => $hb->error_code,
                'deployment_id' => $hb->deployment_id,
                'player_version' => $hb->player_version,
                'orientation' => $hb->orientation,
            ])
            ->values();

        return Inertia::render('admin/screens/show', [
            'screen' => [
                'id' => $screen->id,
                'name' => $screen->name,
                'workspace_name' => $screen->workspace?->name,
                'orientation' => $screen->orientation,
                'operational_status' => $screen->operational_status->value,
                'pairing_state' => $state['pairing_state'],
                'network_state' => $state['network_state'],
                'health' => $state['health'],
                'health_label' => $state['health_label'],
                'content_sync' => $state['content_sync'],
                'content_sync_label' => $state['content_sync_label'],
                'orientation_mismatch' => $state['orientation_mismatch'],
                'current_design_name' => $active?->screenDesign?->name,
                'current_version_number' => $active?->screenDesignVersion?->version_number,
                'deployed_at' => $active?->deployed_at?->toIso8601String(),
                'paired_at' => $device?->paired_at?->toIso8601String(),
                'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
                'created_at' => $screen->created_at?->toIso8601String(),
                'device' => $device === null ? null : [
                    'device_identifier' => $device->device_identifier,
                    'player_version' => $device->player_version,
                    'viewport_width' => $device->viewport_width,
                    'viewport_height' => $device->viewport_height,
                    'reported_orientation' => $device->reported_orientation,
                    'playback_state' => $device->playback_state,
                    'last_error_code' => $device->last_error_code,
                    'reported_deployment_id' => $device->reported_deployment_id,
                    // Never expose token hash.
                ],
                'recent_heartbeats' => $heartbeats,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Screen $screen): array
    {
        $device = $screen->devices->first();
        $deployment = $screen->deployments->first();
        $state = ScreenPresence::snapshot($screen, $device, $deployment);

        return [
            'id' => $screen->id,
            'name' => $screen->name,
            'workspace_name' => $screen->workspace?->name,
            'operational_status' => $screen->operational_status->value,
            'pairing_state' => $state['pairing_state'],
            'network_state' => $state['network_state'],
            'health' => $state['health'],
            'health_label' => $state['health_label'],
            'current_design_name' => $deployment?->screenDesign?->name,
            'paired_at' => $device?->paired_at?->toIso8601String(),
            'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
            'created_at' => $screen->created_at?->toIso8601String(),
        ];
    }
}
