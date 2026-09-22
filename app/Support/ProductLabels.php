<?php

namespace App\Support;

/**
 * Customer-facing product labels.
 *
 * Screen = content created in RMSignage (formerly "Screen Design").
 * TV = physical paired playback device (sidebar: Paired TVs).
 * Internal models/routes may still use ScreenDesign / Screen for TVs.
 */
final class ProductLabels
{
    public const DISPLAY_SINGULAR = 'TV';

    public const DISPLAY_PLURAL = 'TVs';

    public const PAIRED_NAV = 'Paired TVs';

    public const SCREEN_DESIGN_SINGULAR = 'Screen';

    public const SCREEN_DESIGN_PLURAL = 'Screens';

    public const CREATE_SCREEN = 'Create Screen';

    public const SCREEN_NAME = 'Screen Name';

    public const PLAYLIST_NAME = 'Playlist Name';

    public const SCHEDULE_NAME = 'Schedule Name';

    public static function display(int $count = 1): string
    {
        return $count === 1 ? self::DISPLAY_SINGULAR : self::DISPLAY_PLURAL;
    }

    public static function connectedLicences(int $used, int $licensed): string
    {
        return "{$used} / {$licensed} ".self::DISPLAY_PLURAL;
    }

    public static function publishTo(): string
    {
        return 'Publish to TV';
    }

    public static function pairAction(): string
    {
        return 'Pair TV';
    }
}
