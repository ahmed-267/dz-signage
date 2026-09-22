<?php

namespace App\Http\Controllers\Player;

use App\Enums\ScreenContentSource;
use App\Enums\ScreenOperationalStatus;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Support\Billing\BillingEntitlement;
use App\Support\Player\ManifestContentBuilder;
use App\Support\Screens\ScreenContentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManifestController extends Controller
{
    public function __construct(
        private readonly ScreenContentResolver $resolver,
        private readonly ManifestContentBuilder $content,
    ) {}

    /**
     * Cheap poll: players compare `version` with what they are showing and
     * refetch the manifest when it changes. The version comes from the
     * resolver, so a schedule window changeover is picked up too.
     */
    public function check(Request $request): JsonResponse
    {
        $screen = $this->screen($request);

        if (! $this->isEntitled($screen)) {
            // Version changes so the Player refetches and clears its content.
            return response()->json([
                'deployment_id' => null,
                'version' => 'billing_required',
                'screen_active' => $screen->operational_status === ScreenOperationalStatus::Active,
                'content_source' => ScreenContentSource::None->value,
                'schedule_id' => null,
                'valid_until' => null,
                'status' => 'billing_required',
            ]);
        }

        $content = $this->resolver->resolve($screen);

        return response()->json([
            'deployment_id' => $content->deployment?->id,
            'version' => $content->versionLabel(),
            'screen_active' => $screen->operational_status === ScreenOperationalStatus::Active,
            'content_source' => $content->source->value,
            'schedule_id' => $content->schedule?->id,
            'valid_until' => $content->windowEndsAt()?->toIso8601String(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $screen = $this->screen($request);

        if (! $this->isEntitled($screen)) {
            return response()->json($this->content->emptyPayload($screen, 'billing_required'));
        }

        if ($screen->operational_status === ScreenOperationalStatus::Inactive) {
            return response()->json($this->content->emptyPayload($screen, 'inactive'));
        }

        $resolved = $this->resolver->resolve($screen);

        if ($resolved->isSchedule()) {
            return response()->json($this->content->schedulePayload($screen, $resolved));
        }

        $deployment = $resolved->deployment;

        if ($deployment === null) {
            return response()->json($this->content->emptyPayload($screen, 'no_content'));
        }

        return response()->json($this->content->deploymentPayload($screen, $deployment));
    }

    /**
     * A Workspace without Screen licences receives no live content.
     */
    private function isEntitled(Screen $screen): bool
    {
        $workspace = $screen->workspace;

        return $workspace === null || BillingEntitlement::canReceiveLiveContent($workspace);
    }

    private function screen(Request $request): Screen
    {
        /** @var Screen $screen */
        $screen = $request->attributes->get('player_screen');

        return $screen;
    }
}
