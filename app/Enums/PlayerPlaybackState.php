<?php

namespace App\Enums;

enum PlayerPlaybackState: string
{
    case Ready = 'ready';
    case NoContent = 'no_content';
    case Rendering = 'rendering';
    case Inactive = 'inactive';
    case Error = 'error';
    case Pairing = 'pairing';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::NoContent => 'No content',
            self::Rendering => 'Rendering',
            self::Inactive => 'Inactive',
            self::Error => 'Error',
            self::Pairing => 'Pairing',
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
