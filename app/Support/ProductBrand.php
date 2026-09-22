<?php

namespace App\Support;

/**
 * Canonical product wordmark for customer and admin shells.
 * Prefer this over config('app.name') / platform_settings for UI branding.
 */
final class ProductBrand
{
    public const NAME = 'RMSignage';

    public static function name(): string
    {
        return self::NAME;
    }

    public static function adminName(): string
    {
        return self::NAME.' Admin';
    }
}
