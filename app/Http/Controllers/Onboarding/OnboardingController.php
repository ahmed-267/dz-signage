<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Workspaces\CreateWorkspace;
use App\Enums\WorkspaceIndustry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('onboarding/workspace', [
            'industries' => collect(WorkspaceIndustry::cases())->map(fn ($i) => [
                'value' => $i->value,
                'label' => $i->label(),
            ])->values(),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $createWorkspace): RedirectResponse
    {
        $createWorkspace->handle($request->user(), $request->validated());

        return redirect()
            ->route('app.dashboard')
            ->with('success', 'Workspace created. Welcome to DZ Signage.');
    }
}
