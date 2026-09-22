<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Platform\SystemHealthChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemHealthController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isPlatformStaff(), 403);

        $health = SystemHealthChecker::check();

        $overall = $health['overall'];
        $overallLabel = match ($overall) {
            'healthy' => 'Healthy',
            'degraded' => 'Degraded',
            'unavailable' => 'Unavailable',
            default => ucfirst($overall),
        };

        return Inertia::render('admin/system-health/index', [
            'checks' => $health['checks'],
            'overall' => $overall,
            'overall_status' => $overall,
            'overall_status_label' => $overallLabel,
        ]);
    }
}
