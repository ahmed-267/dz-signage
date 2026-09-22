<?php

namespace App\Enums;

enum TemplateCategory: string
{
    case PrayerTimes = 'prayer_times';
    case Announcement = 'announcement';
    case Timetable = 'timetable';
    case Lecture = 'lecture';
    case Notice = 'notice';
    case Fundraising = 'fundraising';
    case Lesson = "clas\x73";
    case Menu = 'menu';
    case Promo = 'promo';
    case Welcome = 'welcome';
    case Event = 'event';
    case Healthcare = 'healthcare';
    case Retail = 'retail';
    case Corporate = 'corporate';
    case Education = 'education';
    case Hospitality = 'hospitality';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PrayerTimes => 'Prayer Times',
            self::Announcement => 'Announcement',
            self::Timetable => 'Timetable',
            self::Lecture => 'Lecture',
            self::Notice => 'Notice',
            self::Fundraising => 'Fundraising',
            self::Lesson => 'Class',
            self::Menu => 'Menu',
            self::Promo => 'Promo',
            self::Welcome => 'Welcome',
            self::Event => 'Event',
            self::Healthcare => 'Healthcare',
            self::Retail => 'Retail',
            self::Corporate => 'Corporate',
            self::Education => 'Education',
            self::Hospitality => 'Hospitality',
            self::Other => 'Other',
        };
    }
}
