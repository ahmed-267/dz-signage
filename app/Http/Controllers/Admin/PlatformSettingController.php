<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Platform\PlatformPermissions;
use App\Support\Platform\PlatformSettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlatformSettingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        return Inertia::render('admin/settings/index', [
            'settings' => PlatformSettingsStore::allForAdmin(),
            'permissions' => PlatformPermissions::for($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(PlatformPermissions::canManagePlatformSettings($request->user()), 403);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(PlatformSettingsStore::keys())],
            'value' => ['required'],
        ]);

        PlatformSettingsStore::set($data['key'], $data['value'], $request->user());

        return back()->with('success', 'Platform setting updated.');
    }
}
