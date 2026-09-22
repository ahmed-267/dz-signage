<?php

namespace App\Http\Controllers\App;

use App\Actions\Deployments\PublishDesignToScreens;
use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Models\ScreenDesign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeploymentController extends Controller
{
    public function publishToScreens(
        Request $request,
        ScreenDesign $screenDesign,
        PublishDesignToScreens $action,
    ): RedirectResponse {
        $workspace = $request->user()?->currentWorkspace;
        abort_unless(
            $workspace && (int) $screenDesign->workspace_id === (int) $workspace->id,
            404,
        );

        $this->authorize('create', Deployment::class);

        $data = $request->validate([
            'screen_ids' => ['required', 'array', 'min:1'],
            'screen_ids.*' => ['integer'],
        ]);

        $action->handle(
            $request->user(),
            $workspace,
            $screenDesign,
            $data['screen_ids'],
        );

        return redirect()->back()->with('success', 'Design published to selected screens.');
    }
}
