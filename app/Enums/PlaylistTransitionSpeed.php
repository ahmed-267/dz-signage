<?php

namespace App\Enums;

enum PlaylistTransitionSpeed: string
{
    case Fast = 'fast';
    case Normal = 'normal';
    case Slow = 'slow';

    public function label(): string
    {
        return match ($this) {
            self::Fast => 'Fast',
            self::Normal => 'Normal',
            self::Slow => 'Slow',
        };
    }

    public function milliseconds(): int
    {
        return match ($this) {
            self::Fast => 400,
            self::Normal => 700,
            self::Slow => 1000,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
