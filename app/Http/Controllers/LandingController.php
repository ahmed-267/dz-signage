<?php

namespace App\Http\Controllers;

use App\Support\Billing\BillingEntitlement;
use App\Support\Billing\BillingPlanCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Public marketing home. Never exposes secrets or Workspace private data.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $planCatalog = BillingPlanCatalog::marketingPayload();

        // Legacy per-licence price list (Starter amounts) for older clients.
        $prices = [];

        foreach (BillingEntitlement::availablePriceDisplays() as $price) {
            if (($price['formatted'] ?? null) === null) {
                continue;
            }

            $prices[] = [
                'interval' => $price['interval'],
                'formatted' => $price['formatted'],
                'currency' => $price['currency'],
                'unit_amount' => $price['unit_amount'],
            ];
        }

        return Inertia::render('marketing/home', [
            'canRegister' => Features::enabled(Features::registration()),
            'planCatalog' => $planCatalog,
            'pricing' => [
                'configured' => $planCatalog['plans'] !== [] || $prices !== [],
                'prices' => $prices,
                'min_licenses' => BillingEntitlement::minLicenses(),
                'max_licenses' => min(50, BillingEntitlement::maxLicenses()),
            ],
            'seo' => [
                // The app-level Inertia title callback appends the app name.
                'title' => 'Digital Signage Made Simple',
                // Aligned with the hero supporting line; keeps product nouns honest for search.
                'description' => 'Create screen designs and playlists, schedule, and publish to any Screen remotely from RMSignage Workspace. Pair a display in about a minute.',
            ],
        ]);
    }
}
