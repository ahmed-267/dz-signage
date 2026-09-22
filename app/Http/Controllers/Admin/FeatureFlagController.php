<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeatureFlag;
use App\Support\Platform\AuditLogger;
use App\Support\Platform\PlatformPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeatureFlagController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $flags = FeatureFlag::query()
            ->orderBy('key')
            ->get()
            ->map(fn (FeatureFlag $flag) => [
                'id' => $flag->id,
                'key' => $flag->key,
                'name' => $flag->name,
                'description' => $flag->description,
                'enabled' => $flag->enabled,
                'updated_at' => $flag->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/feature-flags/index', [
            'flags' => $flags,
            'permissions' => PlatformPermissions::for($request->user()),
            'can_manage' => PlatformPermissions::canManageFeatureFlags($request->user()),
        ]);
    }

    public function update(Request $request, FeatureFlag $featureFlag): RedirectResponse
    {
        abort_unless(PlatformPermissions::canManageFeatureFlags($request->user()), 403);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $previous = $featureFlag->enabled;
        $featureFlag->forceFill(['enabled' => $data['enabled']])->save();

        AuditLogger::record(
            $request->user(),
            'feature_flag.updated',
            'feature_flag',
            $featureFlag->id,
            null,
            [
                'key' => $featureFlag->key,
                'previous_enabled' => $previous,
                'enabled' => $featureFlag->enabled,
            ],
        );

        return back()->with('success', 'Feature flag updated.');
    }
}
