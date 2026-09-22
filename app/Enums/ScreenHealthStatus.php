<?php

namespace App\Enums;

enum ScreenHealthStatus: string
{
    case Healthy = 'healthy';
    case Attention = 'attention';
    case Offline = 'offline';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Attention => 'Attention',
            self::Offline => 'Offline',
        };
    }
}
