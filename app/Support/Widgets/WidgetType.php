<?php

namespace App\Support\Widgets;

enum WidgetType: string
{
    case Clock = 'clock';
    case Countdown = 'countdown';
    case Weather = 'weather';
    case News = 'news';
    case Calendar = 'calendar';
    case Alert = 'alert';
    case InfoCard = 'info_card';
    case Embed = 'embed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromLegacyName(string $name): ?self
    {
        $key = strtolower(trim($name));
        $key = str_replace(['-', '_'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        return match ($key) {
            'clock' => self::Clock,
            'weather' => self::Weather,
            'countdown', 'countdown timer' => self::Countdown,
            'calendar' => self::Calendar,
            'news', 'news ticker' => self::News,
            'live stream', 'livestream', 'embed' => self::Embed,
            'alert', 'announcement' => self::Alert,
            'information card', 'info card', 'info_card', 'configurable cards' => self::InfoCard,
            default => self::tryFrom(str_replace(' ', '_', $key)),
        };
    }
}
