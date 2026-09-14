<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasWorkspaceMemberships()) {
            return redirect()->route('onboarding.show');
        }

        if (! $user->current_workspace_id || ! $user->belongsToWorkspace((int) $user->current_workspace_id)) {
            $membership = $user->workspaceMemberships()->first();

            if ($membership) {
                $user->forceFill([
                    'current_workspace_id' => $membership->workspace_id,
                ])->save();
            } else {
                return redirect()->route('onboarding.show');
            }
        }

        return $next($request);
    }
}
