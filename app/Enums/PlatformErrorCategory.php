<?php

namespace App\Enums;

enum PlatformErrorCategory: string
{
    case PlayerRender = 'player_render';
    case WidgetData = 'widget_data';
    case Publishing = 'publishing';
    case ManifestSync = 'manifest_sync';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PlayerRender => 'Player Render',
            self::WidgetData => 'Widget Data',
            self::Publishing => 'Publishing',
            self::ManifestSync => 'Manifest Sync',
            self::Other => 'Other',
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
