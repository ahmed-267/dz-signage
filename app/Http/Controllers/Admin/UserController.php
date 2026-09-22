<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ListPagination;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $platformRole = (string) $request->input('platform_role', 'all');
        $workspaceId = (int) $request->input('workspace_id', 0);
        $perPage = ListPagination::perPage($request, 20);
        $sort = ListPagination::sort(
            $request,
            ['name', 'email', 'workspaces', 'role', 'created'],
            'created',
            'desc',
        );

        $query = User::query()
            ->withCount('workspaceMemberships')
            ->with(['workspaceMemberships.workspace:id,name']);

        if ($q !== '') {
            $term = '%'.$q.'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term);
            });
        }

        if ($workspaceId > 0) {
            $query->whereHas(
                'workspaceMemberships',
                fn ($membership) => $membership->where('workspace_id', $workspaceId),
            );
        }

        if ($platformRole === 'none') {
            $query->whereNull('platform_role')->where('is_admin', false);
        } elseif ($platformRole !== 'all' && in_array($platformRole, array_column(PlatformRole::cases(), 'value'), true)) {
            $query->where('platform_role', $platformRole);
        }

        $direction = $sort['direction'];
        $query = match ($sort['column']) {
            'name' => $query->orderBy('name', $direction)->orderByDesc('id'),
            'email' => $query->orderBy('email', $direction)->orderByDesc('id'),
            'workspaces' => $query->orderBy('workspace_memberships_count', $direction)->orderBy('name')->orderByDesc('id'),
            'role' => $query->orderBy('platform_role', $direction)->orderBy('name')->orderByDesc('id'),
            default => $query->orderBy('created_at', $direction)->orderByDesc('id'),
        };

        $paginator = $query
            ->paginate($perPage)
            ->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()
                ->map(fn (User $user) => $this->userPayload($user))
                ->values(),
        );

        return Inertia::render('admin/users/index', [
            'users' => ListPagination::inertia($paginator),
            'filters' => [
                'q' => $q,
                'platform_role' => $platformRole,
                'workspace_id' => $workspaceId > 0 ? $workspaceId : null,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
                'per_page' => $perPage,
            ],
            'workspaces' => Workspace::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Workspace $workspace) => [
                    'value' => (string) $workspace->id,
                    'label' => $workspace->name,
                ])
                ->values(),
            'platform_roles' => array_map(
                fn (PlatformRole $role) => ['value' => $role->value, 'label' => $role->label()],
                PlatformRole::cases(),
            ),
            'permissions' => PlatformPermissions::for($request->user()),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $user->loadCount('workspaceMemberships');
        $user->load([
            'workspaceMemberships.workspace:id,name,slug',
        ]);

        $actor = $request->user();

        return Inertia::render('admin/users/show', [
            'user' => array_merge($this->userPayload($user), [
                'is_self' => $actor?->id === $user->id,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ]),
            'memberships' => $user->workspaceMemberships->map(fn ($membership) => [
                'workspace_id' => $membership->workspace?->id,
                'workspace_name' => $membership->workspace?->name,
                'role' => $membership->role->label(),
                'joined_at' => $membership->created_at?->toIso8601String(),
            ])->values(),
            'recent_audit' => AuditLog::query()
                ->where('entity_type', 'user')
                ->where('entity_id', $user->id)
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'subject' => $log->entity_type,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])
                ->values(),
            'can_manage_role' => $actor ? PlatformPermissions::canManagePlatformRoles($actor) : false,
            'role_options' => array_map(
                fn (PlatformRole $role) => ['value' => $role->value, 'label' => $role->label()],
                PlatformRole::cases(),
            ),
            'permissions' => $actor ? PlatformPermissions::for($actor) : [],
            'platform_roles' => array_map(
                fn (PlatformRole $role) => ['value' => $role->value, 'label' => $role->label()],
                PlatformRole::cases(),
            ),
        ]);
    }

    public function updatePlatformRole(Request $request, User $user): RedirectResponse
    {
        abort_unless(PlatformPermissions::canManagePlatformRoles($request->user()), 403);

        $data = $request->validate([
            'platform_role' => ['nullable', 'string', Rule::in(array_column(PlatformRole::cases(), 'value'))],
        ]);

        $newRole = isset($data['platform_role']) && $data['platform_role'] !== ''
            ? PlatformRole::from($data['platform_role'])
            : null;

        $currentRole = $user->platformRole();
        $actor = $request->user();

        if ($currentRole === PlatformRole::SuperAdmin && $newRole !== PlatformRole::SuperAdmin) {
            $superAdminCount = User::query()
                ->where(function ($q): void {
                    $q->where('platform_role', PlatformRole::SuperAdmin->value)
                        ->orWhere('is_admin', true);
                })
                ->count();

            if ($superAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'platform_role' => 'RMSignage must have at least one Super Admin. Create another Super Admin before removing this role.',
                ]);
            }

            if ($actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'platform_role' => 'RMSignage must have at least one Super Admin. Create another Super Admin before removing this role.',
                ]);
            }
        }

        $previous = $currentRole?->value;

        $user->assignPlatformRole($newRole);

        AuditLogger::record(
            $actor,
            'user.platform_role_updated',
            'user',
            $user->id,
            null,
            [
                'previous_role' => $previous,
                'new_role' => $newRole?->value,
                'target_email' => $user->email,
            ],
        );

        return back()->with('success', 'Platform role updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $memberships = $user->relationLoaded('workspaceMemberships')
            ? $user->workspaceMemberships
            : collect();

        $companies = $memberships
            ->map(fn ($membership) => $membership->workspace?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $membershipSummaries = $memberships
            ->map(function ($membership) {
                $workspaceName = $membership->workspace?->name;
                if ($workspaceName === null) {
                    return null;
                }

                return $membership->role->label().' — '.$workspaceName;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'workspace_count' => $user->workspace_memberships_count,
            'companies' => $companies,
            'company' => $companies === [] ? null : implode(', ', $companies),
            'membership_summaries' => $membershipSummaries,
            'is_admin' => $user->isSuperAdmin(),
            'platform_role' => $user->platformRole()?->value,
            'platform_role_label' => $user->platformRole()?->label(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
