<?php

namespace App\Enums;

enum WorkspaceIndustry: string
{
    case Masjid = 'masjid';
    case Restaurant = 'restaurant';
    case Cafe = 'cafe';
    case Retail = 'retail';
    case Hotel = 'hotel';
    case Gym = 'gym';
    case Corporate = 'corporate';
    case Education = 'education';
    case Healthcare = 'healthcare';
    case RealEstate = 'real_estate';
    case Events = 'events';
    case Hospitality = 'hospitality';
    case Community = 'community';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Masjid => 'Masjid',
            self::Restaurant => 'Restaurant',
            self::Cafe => 'Café',
            self::Retail => 'Retail',
            self::Hotel => 'Hotel',
            self::Gym => 'Gym',
            self::Corporate => 'Corporate',
            self::Education => 'Education',
            self::Healthcare => 'Healthcare',
            self::RealEstate => 'Real Estate',
            self::Events => 'Events',
            self::Hospitality => 'Hospitality',
            self::Community => 'Community',
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
