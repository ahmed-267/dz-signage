<?php

namespace App\Http\Controllers\Player;

use App\Actions\Analytics\RecordPlaybackEvents;
use App\Enums\PlaybackEventType;
use App\Http\Controllers\Controller;
use App\Models\Screen;
use App\Models\ScreenDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaybackEventController extends Controller
{
    public function store(Request $request, RecordPlaybackEvents $action): JsonResponse
    {
        /** @var ScreenDevice $device */
        $device = $request->attributes->get('player_device');
        /** @var Screen $screen */
        $screen = $request->attributes->get('player_screen');

        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.(int) config('analytics.max_events_per_request', 20)],
            'events.*.type' => ['required', 'string', Rule::in(PlaybackEventType::values())],
            'events.*.occurred_at' => ['sometimes', 'nullable', 'date'],
            'events.*.deployment_id' => ['sometimes', 'nullable', 'integer'],
            'events.*.screen_design_version_id' => ['sometimes', 'nullable', 'integer'],
            'events.*.playlist_version_id' => ['sometimes', 'nullable', 'integer'],
            'events.*.schedule_id' => ['sometimes', 'nullable', 'integer'],
            'events.*.duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:86400'],
            'events.*.error_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'events.*.idempotency_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'events.*.meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $result = $action->handle($device, $screen, $data['events']);

        return response()->json([
            'ok' => true,
            'accepted' => $result['accepted'],
            'skipped' => $result['skipped'],
        ]);
    }
}
