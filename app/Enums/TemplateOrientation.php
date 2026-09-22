<?php

namespace App\Enums;

enum TemplateOrientation: string
{
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    public function label(): string
    {
        return match ($this) {
            self::Landscape => 'Landscape',
            self::Portrait => 'Portrait',
        };
    }

    public function canvasWidth(): int
    {
        return match ($this) {
            self::Landscape => 1920,
            self::Portrait => 1080,
        };
    }

    public function canvasHeight(): int
    {
        return match ($this) {
            self::Landscape => 1080,
            self::Portrait => 1920,
        };
    }
}
