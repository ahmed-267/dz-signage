<?php

use App\Models\PlaylistItem;
use App\Support\Playlists\PlaylistRuntimeCalculator;

test('item effective seconds multiplies duration by loop count for active items', function () {
    $item = new PlaylistItem([
        'duration_seconds' => 10,
        'loop_count' => 3,
        'is_active' => true,
    ]);

    expect(PlaylistRuntimeCalculator::itemEffectiveSeconds($item))->toBe(30);
});

test('inactive items contribute zero effective seconds', function () {
    $item = new PlaylistItem([
        'duration_seconds' => 10,
        'loop_count' => 5,
        'is_active' => false,
    ]);

    expect(PlaylistRuntimeCalculator::itemEffectiveSeconds($item))->toBe(0);
});

test('loop count below one is treated as one', function () {
    expect(PlaylistRuntimeCalculator::itemEffectiveSeconds([
        'duration_seconds' => 8,
        'loop_count' => 0,
        'is_active' => true,
    ]))->toBe(8);
});

test('total seconds sums active item effective durations only', function () {
    $items = collect([
        new PlaylistItem(['duration_seconds' => 10, 'loop_count' => 2, 'is_active' => true]),
        new PlaylistItem(['duration_seconds' => 5, 'loop_count' => 1, 'is_active' => false]),
        new PlaylistItem(['duration_seconds' => 7, 'loop_count' => 3, 'is_active' => true]),
    ]);

    expect(PlaylistRuntimeCalculator::totalSeconds($items))->toBe(41);
});

test('transitions are not part of runtime math', function () {
    // Documented contract: transition fields must never affect totals.
    expect(PlaylistRuntimeCalculator::itemEffectiveSeconds([
        'duration_seconds' => 12,
        'loop_count' => 1,
        'is_active' => true,
        'transition' => 'fade',
        'transition_speed' => 'slow',
    ]))->toBe(12);
});
