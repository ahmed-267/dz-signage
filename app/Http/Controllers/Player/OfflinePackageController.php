<?php

namespace App\Http\Controllers\Player;

use App\Enums\PlatformErrorCategory;
use App\Http\Controllers\Controller;
use App\Models\PlatformError;
use App\Models\Screen;
use App\Support\Billing\BillingEntitlement;
use App\Support\Player\PlayerOfflinePackageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class OfflinePackageController extends Controller
{
    public function show(Request $request, PlayerOfflinePackageBuilder $builder): JsonResponse
    {
        /** @var Screen $screen */
        $screen = $request->attributes->get('player_screen');

        $workspace = $screen->workspace;

        if ($workspace !== null && ! BillingEntitlement::canReceiveLiveContent($workspace)) {
            return response()->json([
                'error' => 'billing_required',
                'message' => 'An active subscription is required for offline packages.',
            ], 402);
        }

        try {
            return response()->json($builder->build($screen));
        } catch (Throwable $e) {
            PlatformError::record(
                PlatformErrorCategory::Other,
                'Offline package build failed',
                $screen->workspace_id,
                $screen->id,
                null,
                ['error' => mb_substr($e->getMessage(), 0, 200)],
            );

            throw $e;
        }
    }
}
