<?php

namespace App\Http\Controllers\Player;

use App\Actions\Screens\RecordScreenHeartbeat;
use App\Enums\PlayerErrorCode;
use App\Enums\PlayerPlaybackState;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\ScreenDevice;
use App\Support\Screens\ScreenPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HeartbeatController extends Controller
{
    public function store(Request $request, RecordScreenHeartbeat $action): JsonResponse
    {
        /** @var ScreenDevice $device */
        $device = $request->attributes->get('player_device');
        /** @var Screen $screen */
        $screen = $request->attributes->get('player_screen');

        $data = $request->validate([
            'player_version' => ['sometimes', 'nullable', 'string', 'max:64'],
            'viewport_width' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'viewport_height' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'orientation' => ['sometimes', 'nullable', 'string', Rule::in(['landscape', 'portrait'])],
            'deployment_id' => ['sometimes', 'nullable', 'integer'],
            'screen_design_version_id' => ['sometimes', 'nullable', 'integer'],
            'playback_state' => ['sometimes', 'nullable', 'string', Rule::in(PlayerPlaybackState::values())],
            'error_code' => ['sometimes', 'nullable', 'string', Rule::in(PlayerErrorCode::values())],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $data['user_agent'] = mb_substr((string) $request->userAgent(), 0, 512);

        $heartbeat = $action->handle($device, $screen, $data);
        $active = $screen->activeDeployment();

        return response()->json([
            'ok' => true,
            'recorded_at' => $heartbeat->recorded_at->toIso8601String(),
            'server_deployment_id' => $active?->id,
            'screen_active' => $screen->operational_status->value === 'active',
            'heartbeat_interval_seconds' => ScreenPresence::heartbeatIntervalSeconds(),
            'player_version' => config('screens.player_version'),
        ]);
    }
}
