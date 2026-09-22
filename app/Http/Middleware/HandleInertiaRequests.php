<?php

namespace App\Http\Middleware;

use App\Models\BrandKit;
use App\Support\Ai\AiAvailability;
use App\Support\ProductBrand;
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
            'name' => ProductBrand::name(),
            'auth' => [
                'user' => $user,
            ],
            'workspace' => $workspaceContext,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Public AI capability status only — never provider secrets/models keys.
            'ai' => function () use ($user, $request) {
                if (! $user || $request->is('admin', 'admin/*', 'player', 'player/*')) {
                    return null;
                }

                $workspace = $user->currentWorkspace;
                if ($workspace && ! $user->belongsToWorkspace($workspace)) {
                    $workspace = null;
                }

                return AiAvailability::status($workspace);
            },
            // Shared Brand Kit snapshot for AI / Screen Design — never create here.
            'brandKit' => function () use ($user, $request) {
                if (! $user || $request->is('admin', 'admin/*', 'player', 'player/*')) {
                    return null;
                }

                $workspace = $user->currentWorkspace;
                if (! $workspace || ! $user->belongsToWorkspace($workspace)) {
                    return null;
                }

                /** @var BrandKit|null $kit */
                $kit = $workspace->brandKit()->with('logo')->first();
                if (! $kit) {
                    return null;
                }

                return [
                    'colors' => $kit->brandColors(),
                    'fonts' => [
                        'heading' => $kit->heading_font,
                        'body' => $kit->body_font,
                        'heading_stack' => BrandKit::fontStack($kit->heading_font),
                        'body_stack' => BrandKit::fontStack($kit->body_font),
                    ],
                    'logo_url' => $kit->logoUrl(),
                    'logo_media_asset_id' => $kit->logo_media_asset_id,
                    'primary_color' => $kit->primary_color,
                    'secondary_color' => $kit->secondary_color,
                    'accent_color' => $kit->accent_color,
                    'background_color' => $kit->background_color,
                    'text_color' => $kit->text_color,
                    'name' => $kit->name,
                    'tagline' => $kit->tagline,
                ];
            },
        ];
    }
}
