<?php

namespace App\Http\Middleware;

use App\Support\WorkspacePermissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspaceContext = null;

        if ($user && ! $request->is('admin', 'admin/*', 'player', 'player/*')) {
            $memberships = $user->workspaceMemberships()
                ->with('workspace:id,name,slug,industry,logo_path')
                ->get();

            $currentId = $user->current_workspace_id;
            $currentMembership = $memberships->firstWhere('workspace_id', $currentId)
                ?? $memberships->first();

            $currentWorkspace = $currentMembership?->workspace;
            $role = $currentMembership?->role;

            $workspaceContext = [
                'current' => $currentWorkspace ? [
                    'id' => $currentWorkspace->id,
                    'name' => $currentWorkspace->name,
                    'slug' => $currentWorkspace->slug,
                    'industry' => $currentWorkspace->industry->value,
                    'industry_label' => $currentWorkspace->industry->label(),
                    'logo_url' => $currentWorkspace->logoUrl(),
                ] : null,
                'role' => $role?->value,
                'role_label' => $role?->label(),
                'permissions' => $currentWorkspace
                    ? WorkspacePermissions::for($role, $user, $currentWorkspace)
                    : null,
                'available' => $memberships->map(fn ($membership) => [
                    'id' => $membership->workspace->id,
                    'name' => $membership->workspace->name,
                    'logo_url' => $membership->workspace->logoUrl(),
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ])->values(),
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'workspace' => $workspaceContext,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
