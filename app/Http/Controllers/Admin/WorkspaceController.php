<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Workspaces\SoftDeleteWorkspace;
use App\Enums\DeploymentStatus;
use App\Enums\ScreenHealthStatus;
use App\Enums\ScreenOperationalStatus;
use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Screen;
use App\Models\Workspace;
use App\Support\Billing\BillingEntitlement;
use App\Support\ListPagination;
use App\Support\Platform\PlatformPermissions;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $industry = (string) $request->input('industry', 'all');
        $sort = ListPagination::sort(
            $request,
            ['name', 'industry', 'members', 'screens', 'created'],
            'created',
            'desc',
        );

        $onlineCutoff = ScreenPresence::onlineCutoff();

        $query = Workspace::query()
            ->withCount('members')
            ->withCount('screens')
            ->withCount([
                'screens as active_screens_count' => fn ($rel) => $rel
                    ->where('operational_status', ScreenOperationalStatus::Active->value),
            ])
            ->withCount([
                'screens as online_screens_count' => fn ($rel) => $rel->whereHas(
                    'devices',
                    fn ($d) => $d->whereNull('revoked_at')->where('last_seen_at', '>=', $onlineCutoff),
                ),
            ])
            ->with(['ownerMembership.user:id,name,email']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'ilike', $term)
                    ->orWhere('slug', 'ilike', $term)
                    ->orWhereHas('ownerMembership.user', function ($user) use ($term): void {
                        $user->where('name', 'ilike', $term)
                            ->orWhere('email', 'ilike', $term);
                    });
            });
        }

        if ($industry !== 'all' && in_array($industry, WorkspaceIndustry::values(), true)) {
            $query->where('industry', $industry);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'name' => $query->orderBy('name', $direction)->orderByDesc('id'),
            'industry' => $query->orderBy('industry', $direction)->orderBy('name')->orderByDesc('id'),
            'members' => $query->orderBy('members_count', $direction)->orderBy('name')->orderByDesc('id'),
            'screens' => $query->orderBy('screens_count', $direction)->orderBy('name')->orderByDesc('id'),
            default => $query->orderBy('created_at', $direction)->orderByDesc('id'),
        };

        $perPage = ListPagination::perPage($request, 20);

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'industry' => $workspace->industry->label(),
                'industry_value' => $workspace->industry->value,
                'owner' => $workspace->ownerMembership?->user?->name,
                'owner_email' => $workspace->ownerMembership?->user?->email,
                'member_count' => (int) ($workspace->members_count ?? 0),
                'screen_count' => (int) ($workspace->screens_count ?? 0),
                'active_screen_count' => (int) ($workspace->active_screens_count ?? 0),
                'online_screen_count' => (int) ($workspace->online_screens_count ?? 0),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ]),
        );

        return Inertia::render('admin/workspaces/index', [
            'workspaces' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'industry' => $industry,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'industries' => array_map(
                fn (WorkspaceIndustry $i) => ['value' => $i->value, 'label' => $i->label()],
                WorkspaceIndustry::cases(),
            ),
        ]);
    }

    /**
     * Read-only billing snapshot with a link into the subscription detail.
     *
     * @return array<string, mixed>
     */
    private function billingSnippet(Workspace $workspace): array
    {
        $subscription = BillingEntitlement::subscription($workspace);

        return [
            ...BillingEntitlement::summary($workspace),
            'subscription_url' => $subscription === null
                ? null
                : route('admin.subscriptions.show', $subscription),
            'subscriptions_url' => route('admin.subscriptions'),
        ];
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $workspace->load([
            'members.user:id,name,email',
            'ownerMembership.user:id,name,email',
        ]);

        $workspace->loadCount([
            'screens',
            'mediaAssets',
            'playlists',
            'schedules',
            'screenDesigns',
            'deployments',
        ]);

        $screens = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->with([
                'devices' => fn ($rel) => $rel->whereNull('revoked_at')->orderByDesc('paired_at'),
                'deployments' => fn ($rel) => $rel
                    ->where('status', DeploymentStatus::Active)
                    ->latest('id'),
            ])
            ->limit(500)
            ->get();

        $online = 0;
        $attention = 0;

        foreach ($screens as $screen) {
            $device = $screen->devices->first();
            $deployment = $screen->deployments->first();

            if (ScreenPresence::networkState($device) === 'online') {
                $online++;
            }

            if (ScreenPresence::health($screen, $device, $deployment) === ScreenHealthStatus::Attention) {
                $attention++;
            }
        }

        $offline = max(0, $screens->count() - $online);

        $recentPublishing = Deployment::query()
            ->where('workspace_id', $workspace->id)
            ->with(['screen:id,name', 'screenDesign:id,name', 'playlist:id,name'])
            ->latest('deployed_at')
            ->limit(10)
            ->get()
            ->map(fn (Deployment $d) => [
                'id' => $d->id,
                'screen_name' => $d->screen?->name,
                'content_name' => $d->contentName(),
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'deployed_at' => $d->deployed_at?->toIso8601String(),
            ])
            ->values();

        $recentActivity = AuditLog::query()
            ->where('workspace_id', $workspace->id)
            ->with('actor:id,name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_name' => $log->actor?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/workspaces/show', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'industry' => $workspace->industry->label(),
                'country' => $workspace->country,
                'timezone' => $workspace->timezone,
                'owner' => [
                    'id' => $workspace->ownerMembership?->user?->id,
                    'name' => $workspace->ownerMembership?->user?->name,
                    'email' => $workspace->ownerMembership?->user?->email,
                ],
                'members' => $workspace->members->map(fn ($member) => [
                    'id' => $member->user?->id,
                    'name' => $member->user?->name,
                    'email' => $member->user?->email,
                    'role' => $member->role->label(),
                    'joined_at' => $member->created_at?->toIso8601String(),
                ])->values(),
                'screens_summary' => [
                    'total' => $workspace->screens_count,
                    'online' => $online,
                    'offline' => $offline,
                    'attention' => $attention,
                ],
                'content_counts' => [
                    'media' => $workspace->media_assets_count,
                    'screen_designs' => $workspace->screen_designs_count,
                    'playlists' => $workspace->playlists_count,
                    'schedules' => $workspace->schedules_count,
                ],
                'counts' => [
                    'screens' => $workspace->screens_count,
                    'media' => $workspace->media_assets_count,
                    'playlists' => $workspace->playlists_count,
                    'schedules' => $workspace->schedules_count,
                    'screen_designs' => $workspace->screen_designs_count,
                    'deployments' => $workspace->deployments_count,
                ],
                'recent_publishing' => $recentPublishing,
                'recent_activity' => $recentActivity,
                'billing' => $this->billingSnippet($workspace),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
            'recent_deployments' => $recentPublishing,
            'recent_audit' => $recentActivity,
            'billing' => $this->billingSnippet($workspace),
            'can_delete' => PlatformPermissions::canDeleteWorkspace($request->user()),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, SoftDeleteWorkspace $action): RedirectResponse
    {
        $validated = $request->validate([
            'confirm_name' => ['required', 'string', 'max:255'],
        ]);

        $action->handle($request->user(), $workspace, $validated['confirm_name']);

        return redirect()
            ->route('admin.workspaces')
            ->with('success', 'Business removed. Access is revoked and an active subscription was cancelled when one existed.');
    }
}
