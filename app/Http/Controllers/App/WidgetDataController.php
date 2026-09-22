<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\Widgets\Calendar\IcsCalendarService;
use App\Support\Widgets\EmbedUrlValidator;
use App\Support\Widgets\Rss\RssFeedService;
use App\Support\Widgets\SafeRemoteUrl;
use App\Support\Widgets\Weather\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WidgetDataController extends Controller
{
    public function store(
        Request $request,
        WeatherService $weather,
        RssFeedService $rss,
        IcsCalendarService $calendar,
    ): JsonResponse {
        abort_unless($request->user()?->currentWorkspace !== null, 403);

        $data = $request->validate([
            'weather' => ['sometimes', 'nullable', 'array'],
            'weather.location' => ['required_with:weather', 'string', 'max:200'],
            'weather.units' => ['sometimes', 'nullable', 'string', Rule::in(['c', 'f'])],
            'news' => ['sometimes', 'nullable', 'array'],
            'news.feedUrl' => ['required_with:news', 'string', 'max:2048'],
            'news.maxItems' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'calendar' => ['sometimes', 'nullable', 'array'],
            'calendar.feedUrl' => ['required_with:calendar', 'string', 'max:2048'],
            'calendar.maxEvents' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'calendar.timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'embed' => ['sometimes', 'nullable', 'array'],
            'embed.url' => ['required_with:embed', 'string', 'max:2048'],
        ]);

        $result = [];

        if (isset($data['weather']) && is_array($data['weather'])) {
            $result['weather'] = $weather->fetch(
                (string) $data['weather']['location'],
                (string) ($data['weather']['units'] ?? 'c'),
            );
        }

        if (isset($data['news']) && is_array($data['news'])) {
            try {
                SafeRemoteUrl::assertSafe((string) $data['news']['feedUrl']);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'news.feedUrl' => $e->errors()['url'] ?? ['The feed URL is not allowed.'],
                ]);
            }

            $result['news'] = $rss->fetch(
                (string) $data['news']['feedUrl'],
                (int) ($data['news']['maxItems'] ?? 5),
            );
        }

        if (isset($data['calendar']) && is_array($data['calendar'])) {
            try {
                SafeRemoteUrl::assertSafe((string) $data['calendar']['feedUrl']);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'calendar.feedUrl' => $e->errors()['url'] ?? ['The feed URL is not allowed.'],
                ]);
            }

            $result['calendar'] = $calendar->fetch(
                (string) $data['calendar']['feedUrl'],
                (int) ($data['calendar']['maxEvents'] ?? 5),
                isset($data['calendar']['timezone']) ? (string) $data['calendar']['timezone'] : null,
            );
        }

        if (isset($data['embed']) && is_array($data['embed'])) {
            $resolved = EmbedUrlValidator::resolve((string) $data['embed']['url'], [
                'check_embeddable' => true,
                'check_framing' => true,
            ]);
            $result['embed'] = [
                ...$resolved,
                'url' => $resolved['play_url'] ?? $resolved['source_url'],
            ];
        }

        return response()->json(['data' => $result]);
    }
}
