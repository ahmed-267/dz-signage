<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $role = $workspace ? $request->user()->roleIn($workspace) : null;

        return Inertia::render('app/dashboard', [
            'workspaceSummary' => $workspace ? [
                'name' => $workspace->name,
                'industry' => $workspace->industry->label(),
                'role' => $role?->label(),
                'country' => $workspace->country,
                'timezone' => $workspace->timezone,
            ] : null,
        ]);
    }
}
