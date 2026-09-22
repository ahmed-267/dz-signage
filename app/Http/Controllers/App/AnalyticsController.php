<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Screen;
use App\Support\Analytics\AnalyticsQuery;
use App\Support\Billing\BillingEntitlement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    private const TABS = ['overview', 'tvs', 'content', 'publishing', 'errors'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        abort_unless($workspace !== null, 403);
        abort_unless($user->roleIn($workspace)?->canViewAnalytics() ?? false, 403);

        $rangeKey = (string) $request->string('range', '7d');
        $resolved = AnalyticsQuery::resolveRange(
            $workspace,
            $rangeKey,
            $request->query('from'),
            $request->query('to'),
        );

        $screenId = $request->integer('screen_id') ?: null;
        if ($screenId !== null) {
            $belongs = Screen::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($screenId)
                ->exists();
            if (! $belongs) {
                abort(404);
            }
        }

        $locationId = $request->integer('location_id') ?: null;
        if ($locationId !== null) {
            $belongs = Location::query()
                ->where('workspace_id', $workspace->id)
                ->whereKey($locationId)
                ->exists();
            if (! $belongs) {
                abort(404);
            }
        }

        $tab = (string) $request->string('tab', 'overview');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'overview';
        }

        $dashboard = AnalyticsQuery::dashboard(
            $workspace,
            $resolved['from'],
            $resolved['to'],
            $screenId,
            $locationId,
        );

        $screenOptions = Screen::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Screen $s) => ['id' => $s->id, 'name' => $s->name])
            ->values();

        $locationOptions = Location::query()
            ->where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
            ])
            ->values();

        return Inertia::render('app/analytics/index', [
            'tab' => $tab,
            'filters' => [
                'range' => in_array($rangeKey, ['7d', '30d', 'this_month', 'prev_month', 'custom'], true)
                    ? $rangeKey
                    : '7d',
                'from' => $resolved['from']->toDateString(),
                'to' => $resolved['to']->toDateString(),
                'screen_id' => $screenId,
                'location_id' => $locationId,
                'label' => $resolved['label'],
                'timezone' => $resolved['timezone'],
            ],
            'screens' => $screenOptions,
            'locations' => $locationOptions,
            'dashboard' => $dashboard,
            'has_advanced_analytics' => BillingEntitlement::hasFeature(
                $workspace,
                'advanced_analytics',
            ),
        ]);
    }
}
