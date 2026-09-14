<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(): Response
    {
        $workspaces = Workspace::query()
            ->withCount('members')
            ->with(['ownerMembership.user:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'industry' => $workspace->industry->label(),
                'owner' => $workspace->ownerMembership?->user?->name,
                'owner_email' => $workspace->ownerMembership?->user?->email,
                'member_count' => $workspace->members_count,
                'created_at' => $workspace->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/workspaces/index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function show(Workspace $workspace): Response
    {
        $workspace->load([
            'members.user:id,name,email',
            'ownerMembership.user:id,name,email',
        ]);

        return Inertia::render('admin/workspaces/show', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'industry' => $workspace->industry->label(),
                'country' => $workspace->country,
                'timezone' => $workspace->timezone,
                'owner' => [
                    'name' => $workspace->ownerMembership?->user?->name,
                    'email' => $workspace->ownerMembership?->user?->email,
                ],
                'members' => $workspace->members->map(fn ($member) => [
                    'name' => $member->user?->name,
                    'email' => $member->user?->email,
                    'role' => $member->role->label(),
                    'joined_at' => $member->created_at?->toIso8601String(),
                ])->values(),
                'created_at' => $workspace->created_at?->toIso8601String(),
            ],
        ]);
    }
}
