<?php

namespace App\Enums;

enum DeploymentContentType: string
{
    case ScreenDesign = 'screen_design';
    case Playlist = 'playlist';

    public function label(): string
    {
        return match ($this) {
            self::ScreenDesign => 'Screen Design',
            self::Playlist => 'Playlist',
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
