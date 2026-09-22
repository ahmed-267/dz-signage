<?php

namespace App\Http\Controllers\App;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\SwitchWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Support\CountryCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('app/workspaces/create', [
            'industries' => collect(WorkspaceIndustry::cases())->map(fn ($i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
            'timezones' => timezone_identifiers_list(),
            'countries' => CountryCatalog::options(),
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $createWorkspace->handle($request->user(), $request->validated());

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Workspace created.');
    }

    public function switch(Request $request, Workspace $workspace, SwitchWorkspace $switchWorkspace): RedirectResponse
    {
        $this->authorize('view', $workspace);

        $switchWorkspace->handle($request->user(), $workspace);

        return redirect()
            ->back(fallback: route('app.dashboard'))
            ->with('success', 'Switched workspace.');
    }

    public function edit(): RedirectResponse
    {
        return redirect()->route('app.settings.tab', ['tab' => 'workspace']);
    }

    public function update(UpdateWorkspaceRequest $request, UpdateWorkspace $updateWorkspace): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($workspace !== null, 404);
        $this->authorize('update', $workspace);

        $updateWorkspace->handle($workspace, $request->validated());

        return redirect()
            ->route('app.settings.tab', ['tab' => 'workspace'])
            ->with('success', 'Workspace settings saved.');
    }
}
