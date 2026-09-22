<?php

namespace App\Enums;

enum SupportRequestCategory: string
{
    case General = 'general';
    case Billing = 'billing';
    case Technical = 'technical';
    case Screens = 'screens';
    case Content = 'content';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Billing => 'Billing',
            self::Technical => 'Technical',
            self::Screens => 'TVs',
            self::Content => 'Content',
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
