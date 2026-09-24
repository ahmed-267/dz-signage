<?php

namespace App\Support\Playlists;

use App\Enums\PlaylistTransition;
use App\Enums\PlaylistTransitionSpeed;

/**
 * Single source of truth for `config/playlists.php` values.
 */
class PlaylistDefaults
{
    public static function durationSeconds(): int
    {
        return (int) config('playlists.default_duration_seconds', 10);
    }

    public static function minDurationSeconds(): int
    {
        return max(1, (int) config('playlists.min_duration_seconds', 1));
    }

    public static function maxDurationSeconds(): int
    {
        return (int) config('playlists.max_duration_seconds', 3600);
    }

    public static function loopCount(): int
    {
        return max(1, (int) config('playlists.default_loop_count', 1));
    }

    public static function minLoopCount(): int
    {
        return max(1, (int) config('playlists.min_loop_count', 1));
    }

    public static function maxLoopCount(): int
    {
        return max(self::minLoopCount(), (int) config('playlists.max_loop_count', 99));
    }

    public static function isValidLoopCount(int $loops): bool
    {
        return $loops >= self::minLoopCount()
            && $loops <= self::maxLoopCount();
    }

    public static function transition(): PlaylistTransition
    {
        return PlaylistTransition::tryFrom((string) config('playlists.default_transition', 'fade'))
            ?? PlaylistTransition::Fade;
    }

    public static function transitionSpeed(): PlaylistTransitionSpeed
    {
        return PlaylistTransitionSpeed::tryFrom((string) config('playlists.default_transition_speed', 'normal'))
            ?? PlaylistTransitionSpeed::Normal;
    }

    public static function isValidDuration(int $seconds): bool
    {
        return $seconds >= self::minDurationSeconds()
            && $seconds <= self::maxDurationSeconds();
    }

    /**
     * @return array<string, mixed>
     */
    public static function forFrontend(): array
    {
        return [
            'default_duration_seconds' => self::durationSeconds(),
            'min_duration_seconds' => self::minDurationSeconds(),
            'max_duration_seconds' => self::maxDurationSeconds(),
            'default_loop_count' => self::loopCount(),
            'min_loop_count' => self::minLoopCount(),
            'max_loop_count' => self::maxLoopCount(),
            'default_transition' => self::transition()->value,
            'default_transition_speed' => self::transitionSpeed()->value,
            'transition_speed_ms' => [
                'fast' => PlaylistTransitionSpeed::Fast->milliseconds(),
                'normal' => PlaylistTransitionSpeed::Normal->milliseconds(),
                'slow' => PlaylistTransitionSpeed::Slow->milliseconds(),
            ],
            'transitions' => array_map(
                fn (PlaylistTransition $t) => ['value' => $t->value, 'label' => $t->label()],
                PlaylistTransition::cases(),
            ),
            'transition_speeds' => array_map(
                fn (PlaylistTransitionSpeed $s) => ['value' => $s->value, 'label' => $s->label()],
                PlaylistTransitionSpeed::cases(),
            ),
        ];
    }
}
