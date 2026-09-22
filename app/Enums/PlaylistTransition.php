<?php

namespace App\Enums;

enum PlaylistTransition: string
{
    case None = 'none';
    case Fade = 'fade';
    case SlideLeft = 'slide_left';
    case SlideRight = 'slide_right';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Fade => 'Fade',
            self::SlideLeft => 'Slide Left',
            self::SlideRight => 'Slide Right',
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
