<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentStatus;
use App\Enums\ScreenHealthStatus;
use App\Enums\SupportRequestStatus;
use App\Enums\TemplateStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\PlatformError;
use App\Models\Screen;
use App\Models\SupportRequest;
use App\Models\Template;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $onlineCutoff = ScreenPresence::onlineCutoff();

        $screensTotal = Screen::query()->count();
        $screensOnline = Screen::query()
            ->whereHas('devices', function ($q) use ($onlineCutoff): void {
                $q->whereNull('revoked_at')
                    ->where('last_seen_at', '>=', $onlineCutoff);
            })
            ->count();
        $screensOffline = max(0, $screensTotal - $screensOnline);

        $screensAttention = $this->attentionScreens(10);
        $attentionCount = Screen::query()
            ->with([
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->latest('id'),
            ])
            ->latest('id')
            ->limit(500)
            ->get()
            ->filter(function (Screen $screen) {
                $device = $screen->devices->first();
                $deployment = $screen->deployments->first();

                return ScreenPresence::health($screen, $device, $deployment) === ScreenHealthStatus::Attention;
            })
            ->count();

        return Inertia::render('admin/dashboard', [
            'metrics' => [
                'workspaces' => Workspace::query()->count(),
                'users' => User::query()->count(),
                'screens' => $screensTotal,
                'screens_online' => $screensOnline,
                'screens_offline' => $screensOffline,
                'screens_attention' => $attentionCount,
                'templates' => Template::query()->platform()->count(),
                'published_templates' => Template::query()
                    ->platform()
                    ->where('status', TemplateStatus::Published)
                    ->count(),
                'deployments_active' => Deployment::query()
                    ->where('status', DeploymentStatus::Active)
                    ->count(),
                'deployments_failed_7d' => Deployment::query()
                    ->where('status', DeploymentStatus::Failed)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'open_support' => SupportRequest::query()
                    ->whereIn('status', [
                        SupportRequestStatus::Open->value,
                        SupportRequestStatus::InProgress->value,
                    ])
                    ->count(),
                'unresolved_errors' => PlatformError::query()->unresolved()->count(),
            ],
            'screens_needing_attention' => $screensAttention,
            'recent_publishing' => Deployment::query()
                ->with(['workspace:id,name', 'screen:id,name', 'screenDesign:id,name', 'playlist:id,name'])
                ->latest('deployed_at')
                ->limit(10)
                ->get()
                ->map(fn (Deployment $d) => [
                    'id' => $d->id,
                    'workspace_name' => $d->workspace?->name,
                    'screen_name' => $d->screen?->name,
                    'content_name' => $d->contentName(),
                    'status' => $d->status->value,
                    'status_label' => $d->status->label(),
                    'deployed_at' => $d->deployed_at?->toIso8601String(),
                ])
                ->values(),
            'recent_workspaces' => Workspace::query()
                ->latest()
                ->limit(10)
                ->get(['id', 'name', 'industry', 'created_at'])
                ->map(fn (Workspace $w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'industry' => $w->industry->label(),
                    'created_at' => $w->created_at?->toIso8601String(),
                ])
                ->values(),
            'recent_audit' => AuditLog::query()
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'actor_name' => $log->actor?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values(),
            'platform_role' => $request->user()->platformRole()?->value,
            'platform_role_label' => $request->user()->platformRole()?->label(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attentionScreens(int $limit): array
    {
        $rows = Screen::query()
            ->with([
                'workspace:id,name',
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->latest('id'),
            ])
            ->latest('id')
            ->limit(500)
            ->get()
            ->filter(function (Screen $screen) {
                $device = $screen->devices->first();
                $deployment = $screen->deployments->first();

                return ScreenPresence::health($screen, $device, $deployment) === ScreenHealthStatus::Attention;
            })
            ->take($limit)
            ->map(function (Screen $screen) {
                $device = $screen->devices->first();
                $deployment = $screen->deployments->first();
                $health = ScreenPresence::health($screen, $device, $deployment);

                return [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'workspace_name' => $screen->workspace?->name,
                    'health' => $health->value,
                    'health_label' => $health->label(),
                    'last_seen_at' => $device?->last_seen_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return array_values($rows);
    }
}
