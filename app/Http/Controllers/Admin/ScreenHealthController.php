<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentStatus;
use App\Enums\ScreenOperationalStatus;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScreenHealthController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = (string) $request->input('filter', 'all');

        $screens = Screen::query()
            ->with([
                'workspace:id,name',
                'location:id,name',
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->with('screenDesign:id,name,orientation')
                    ->latest('id'),
            ])
            ->latest()
            ->limit(200)
            ->get()
            ->map(function (Screen $screen) {
                $device = $screen->devices->first();
                $deployment = $screen->deployments->first();
                $state = ScreenPresence::snapshot($screen, $device, $deployment);

                return [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'workspace_name' => $screen->workspace?->name,
                    'location_name' => $screen->location?->name,
                    'operational_status' => $screen->operational_status->value,
                    'pairing_state' => $state['pairing_state'],
                    'network_state' => $state['network_state'],
                    'health' => $state['health'],
                    'health_label' => $state['health_label'],
                    'content_sync' => $state['content_sync'],
                    'content_sync_label' => $state['content_sync_label'],
                    'current_design_name' => $deployment?->screenDesign?->name,
                    'player_version' => $device?->player_version,
                    'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
                    'heartbeat_age_seconds' => $device?->last_seen_at !== null
                        ? (int) abs($device->last_seen_at->diffInSeconds(now()))
                        : null,
                    'playback_state' => $device?->playback_state,
                    'last_error_code' => $device?->last_error_code,
                    'offline_cache_ready' => (bool) data_get($device?->platform_meta, 'offline.cache_ready', false),
                    'offline_sync_error' => data_get($device?->platform_meta, 'offline.sync_error'),
                    'last_content_sync_at' => data_get($device?->platform_meta, 'offline.last_sync_at'),
                ];
            });

        $counts = [
            'total' => $screens->count(),
            'online' => $screens->where('network_state', 'online')->count(),
            'offline' => $screens->where('network_state', 'offline')->count(),
            'inactive' => $screens->where('operational_status', ScreenOperationalStatus::Inactive->value)->count(),
            'attention' => $screens->where('health', 'attention')->count(),
            'connected' => $screens->where('pairing_state', 'connected')->count(),
            'offline_ready' => $screens->where('offline_cache_ready', true)->count(),
        ];

        $filtered = match ($filter) {
            'online' => $screens->where('network_state', 'online')->values(),
            'offline' => $screens->where('network_state', 'offline')->values(),
            'inactive' => $screens->where('operational_status', 'inactive')->values(),
            'attention' => $screens->where('health', 'attention')->values(),
            'offline_ready' => $screens->where('offline_cache_ready', true)->values(),
            'offline_not_ready' => $screens
                ->where('network_state', 'offline')
                ->where('offline_cache_ready', false)
                ->values(),
            default => $screens->values(),
        };

        return Inertia::render('admin/screen-health/index', [
            'counts' => $counts,
            'screens' => $filtered,
            'filters' => ['filter' => $filter],
            'health_window_seconds' => ScreenPresence::onlineThresholdSeconds(),
            'heartbeat_interval_seconds' => ScreenPresence::heartbeatIntervalSeconds(),
        ]);
    }
}
