<?php

use App\Enums\PlaylistTransitionSpeed;
use App\Support\Playlists\PlaylistDefaults;

test('playlist defaults expose canonical transition speed milliseconds for the frontend', function () {
    $config = PlaylistDefaults::forFrontend();

    expect($config['transition_speed_ms'])->toBe([
        'fast' => PlaylistTransitionSpeed::Fast->milliseconds(),
        'normal' => PlaylistTransitionSpeed::Normal->milliseconds(),
        'slow' => PlaylistTransitionSpeed::Slow->milliseconds(),
    ])
        ->and($config['transition_speed_ms']['fast'])->toBe(400)
        ->and($config['transition_speed_ms']['normal'])->toBe(700)
        ->and($config['transition_speed_ms']['slow'])->toBe(1000);
});

test('supported playlist transitions are only none fade slide_left slide_right', function () {
    $values = array_column(PlaylistDefaults::forFrontend()['transitions'], 'value');

    expect($values)->toBe(['none', 'fade', 'slide_left', 'slide_right']);
});
