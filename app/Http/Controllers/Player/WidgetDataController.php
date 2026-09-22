<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Support\Widgets\WidgetDataCollector;
use App\Support\Widgets\WidgetType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WidgetDataController extends Controller
{
    public function store(Request $request, WidgetDataCollector $collector): JsonResponse
    {
        $data = $request->validate([
            'requests' => ['required', 'array', 'max:50'],
            'requests.*.type' => ['required', 'string', Rule::in(WidgetType::values())],
            'requests.*.config' => ['sometimes', 'nullable', 'array'],
        ]);

        $out = [];

        foreach ($data['requests'] as $requestItem) {
            $type = WidgetType::from((string) $requestItem['type']);
            $config = is_array($requestItem['config'] ?? null) ? $requestItem['config'] : [];
            $key = $collector->dataKey($type, $config);
            if ($key === null || array_key_exists($key, $out)) {
                continue;
            }

            $out[$key] = $collector->fetchPayload($type, $config);
        }

        return response()->json(['data' => $out]);
    }
}
