<?php

namespace App\Support\Rendering;

use App\Enums\TemplateCategory;
use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;
use App\Enums\WorkspaceIndustry;

final class StarterTemplateCatalog
{
    private const ISLAMIC_DARK = '#0B3D2E';

    private const ISLAMIC_GOLD = '#D4AF37';

    private const ISLAMIC_LIGHT = '#ECFDF5';

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            self::def('prayer-times-landscape', 'Prayer Times — Landscape', 'Daily prayer board with mosque heading, salah times, Jumu\'ah section, and announcements.', TemplateCategory::PrayerTimes, WorkspaceIndustry::Masjid, TemplateOrientation::Landscape, TemplateTheme::Islamic, self::prayerTimesLandscape()),
            self::def('prayer-times-portrait', 'Prayer Times — Portrait', 'Portrait prayer times board designed for 9:16 displays.', TemplateCategory::PrayerTimes, WorkspaceIndustry::Masjid, TemplateOrientation::Portrait, TemplateTheme::Islamic, self::prayerTimesPortrait()),
            self::def('jumuah-announcement', 'Jumu\'ah Announcement', 'Friday khutbah and congregation announcement board.', TemplateCategory::Announcement, WorkspaceIndustry::Masjid, TemplateOrientation::Landscape, TemplateTheme::Islamic, self::jumuahAnnouncement()),
            self::def('ramadan-timetable', 'Ramadan Timetable', 'Suhoor, Iftar, and Taraweeh timetable for Ramadan.', TemplateCategory::Timetable, WorkspaceIndustry::Masjid, TemplateOrientation::Portrait, TemplateTheme::Elegant, self::ramadanTimetable()),
            self::def('retail-sale-promotion', 'Retail Sale Promotion', 'Bold retail sale layout with product image and call to action.', TemplateCategory::Promo, WorkspaceIndustry::Retail, TemplateOrientation::Portrait, TemplateTheme::Bold, self::retailSalePromotion()),
            self::def('new-product-launch', 'New Product Launch', 'Highlight a new product with hero image and feature bullets.', TemplateCategory::Promo, WorkspaceIndustry::Retail, TemplateOrientation::Landscape, TemplateTheme::Modern, self::newProductLaunch()),
            self::def('restaurant-digital-menu', 'Restaurant Digital Menu', 'Digital menu with sections, items, prices, and featured dish.', TemplateCategory::Menu, WorkspaceIndustry::Restaurant, TemplateOrientation::Portrait, TemplateTheme::Bold, self::restaurantDigitalMenu()),
            self::def('cafe-promotion', 'Café Promotion', 'Café special offer and seasonal drink promotion.', TemplateCategory::Promo, WorkspaceIndustry::Cafe, TemplateOrientation::Portrait, TemplateTheme::Elegant, self::cafePromotion()),
            self::def('corporate-welcome', 'Corporate Welcome', 'Professional lobby welcome screen for offices.', TemplateCategory::Welcome, WorkspaceIndustry::Corporate, TemplateOrientation::Landscape, TemplateTheme::Corporate, self::corporateWelcome()),
            self::def('corporate-announcement', 'Corporate Announcement', 'Company-wide announcement and update board.', TemplateCategory::Announcement, WorkspaceIndustry::Corporate, TemplateOrientation::Landscape, TemplateTheme::Corporate, self::corporateAnnouncement()),
            self::def('school-announcement', 'School / University Announcement', 'Campus announcement board for schools and universities.', TemplateCategory::Announcement, WorkspaceIndustry::Education, TemplateOrientation::Landscape, TemplateTheme::Clean, self::schoolAnnouncement()),
            self::def('education-timetable', 'Education Timetable', 'Weekly class timetable for schools and training centres.', TemplateCategory::Timetable, WorkspaceIndustry::Education, TemplateOrientation::Landscape, TemplateTheme::Clean, self::educationTimetable()),
            self::def('healthcare-waiting-room', 'Healthcare Waiting Room', 'General waiting room information — no patient data.', TemplateCategory::Healthcare, WorkspaceIndustry::Healthcare, TemplateOrientation::Landscape, TemplateTheme::Light, self::healthcareWaitingRoom()),
            self::def('hotel-welcome', 'Hotel Welcome', 'Elegant hotel welcome and guest services board.', TemplateCategory::Welcome, WorkspaceIndustry::Hotel, TemplateOrientation::Portrait, TemplateTheme::Elegant, self::hotelWelcome()),
            self::def('gym-class-schedule', 'Gym Class Schedule', 'Daily fitness class schedule for gyms and studios.', TemplateCategory::Timetable, WorkspaceIndustry::Gym, TemplateOrientation::Portrait, TemplateTheme::Bold, self::gymClassSchedule()),
            self::def('property-showcase', 'Property Showcase', 'Featured property listing for real estate agencies.', TemplateCategory::Promo, WorkspaceIndustry::RealEstate, TemplateOrientation::Landscape, TemplateTheme::Modern, self::propertyShowcase()),
            self::def('event-welcome', 'Event Welcome', 'Conference and event welcome screen with agenda hints.', TemplateCategory::Event, WorkspaceIndustry::Events, TemplateOrientation::Landscape, TemplateTheme::Dark, self::eventWelcome()),
            self::def('community-announcement', 'Community Announcement', 'Community centre notice and update board.', TemplateCategory::Notice, WorkspaceIndustry::Community, TemplateOrientation::Landscape, TemplateTheme::Clean, self::communityAnnouncement()),
            self::def('general-promotion-landscape', 'General Promotion — Landscape', 'Versatile landscape promotion layout for any business.', TemplateCategory::Promo, WorkspaceIndustry::Other, TemplateOrientation::Landscape, TemplateTheme::Colourful, self::generalPromotionLandscape()),
            self::def('general-promotion-portrait', 'General Promotion — Portrait', 'Versatile portrait promotion layout for any business.', TemplateCategory::Promo, WorkspaceIndustry::Other, TemplateOrientation::Portrait, TemplateTheme::Colourful, self::generalPromotionPortrait()),
            self::def('welcome-screen', 'Welcome Screen', 'Simple welcome screen with logo and greeting.', TemplateCategory::Welcome, WorkspaceIndustry::Other, TemplateOrientation::Landscape, TemplateTheme::Minimal, self::welcomeScreen()),
            self::def('event-countdown-layout', 'Event Countdown Layout', 'Event countdown with date, venue, and sponsor area.', TemplateCategory::Event, WorkspaceIndustry::Events, TemplateOrientation::Landscape, TemplateTheme::Dark, self::eventCountdownLayout()),
            self::def('information-board', 'Information Board', 'Multi-section information board for general notices.', TemplateCategory::Notice, WorkspaceIndustry::Other, TemplateOrientation::Landscape, TemplateTheme::Clean, self::informationBoard()),
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function def(
        string $slug,
        string $name,
        string $description,
        TemplateCategory $category,
        WorkspaceIndustry $industry,
        TemplateOrientation $orientation,
        TemplateTheme $theme,
        array $schema,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'industry' => $industry->value,
            'orientation' => $orientation,
            'theme' => $theme,
            'schema' => $schema,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function prayerRow(
        string $id,
        string $label,
        string $time,
        float $x,
        float $y,
        float $width,
        float $height,
        bool $dark = true,
    ): array {
        $rowFill = $dark ? 'rgba(255,255,255,0.07)' : '#FFFFFF';
        $nameColor = $dark ? '#F8FAFC' : self::ISLAMIC_DARK;
        $timeColor = self::ISLAMIC_GOLD;

        return [
            LayoutSchemaBuilder::panel("{$id}-row", $label, $x, $y, $width, $height, [
                'fill' => $rowFill,
                'borderRadius' => 10,
                'opacity' => 1,
            ]),
            LayoutSchemaBuilder::text("{$id}-name", $label, $x + 28, $y + 10, $width * 0.52, $height - 20, [
                'text' => $label,
                'fontSize' => 26,
                'fontWeight' => 600,
                'color' => $nameColor,
            ]),
            LayoutSchemaBuilder::text("{$id}-time", $time, $x + $width * 0.54, $y + 8, $width * 0.42, $height - 16, [
                'text' => $time,
                'fontSize' => 30,
                'fontWeight' => 700,
                'color' => $timeColor,
                'textAlign' => 'right',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function timetableRow(
        string $id,
        string $label,
        string $value,
        float $x,
        float $y,
        float $width,
        float $height,
        string $labelColor = '#475569',
        string $valueColor = '#0F172A',
    ): array {
        return [
            LayoutSchemaBuilder::panel("{$id}-bg", $label, $x, $y, $width, $height, [
                'fill' => '#F8FAFC',
                'borderRadius' => 8,
                'opacity' => 1,
            ]),
            LayoutSchemaBuilder::text("{$id}-label", $label, $x + 20, $y + 10, $width * 0.45, $height - 20, [
                'text' => $label,
                'fontSize' => 22,
                'fontWeight' => 600,
                'color' => $labelColor,
            ]),
            LayoutSchemaBuilder::text("{$id}-value", $value, $x + $width * 0.48, $y + 10, $width * 0.48, $height - 20, [
                'text' => $value,
                'fontSize' => 22,
                'fontWeight' => 500,
                'color' => $valueColor,
                'textAlign' => 'right',
            ]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function menuItem(
        string $id,
        string $name,
        string $price,
        float $x,
        float $y,
        float $width,
    ): array {
        return [
            LayoutSchemaBuilder::text("{$id}-name", $name, $x, $y, $width * 0.72, 36, [
                'text' => $name,
                'fontSize' => 24,
                'fontWeight' => 600,
                'color' => '#1E293B',
            ]),
            LayoutSchemaBuilder::text("{$id}-price", $price, $x + $width * 0.72, $y, $width * 0.28, 36, [
                'text' => $price,
                'fontSize' => 24,
                'fontWeight' => 700,
                'color' => '#B45309',
                'textAlign' => 'right',
            ]),
            LayoutSchemaBuilder::shape("{$id}-line", 'Divider', $x, $y + 38, $width, 2, [
                'fill' => '#E2E8F0',
                'opacity' => 1,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function prayerTimesLandscape(): array
    {
        $prayers = [
            ['fajr', 'Fajr', '05:12'],
            ['sunrise', 'Sunrise', '06:38'],
            ['dhuhr', 'Dhuhr', '12:45'],
            ['asr', 'Asr', '16:02'],
            ['maghrib', 'Maghrib', '18:28'],
            ['isha', 'Isha', '19:52'],
        ];

        $elements = [
            LayoutSchemaBuilder::shape('ptl-accent-top', 'Accent bar', 0, 0, 1920, 8, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::panel('ptl-header', 'Header panel', 60, 40, 1800, 140, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptl-mosque', 'Al-Noor Masjid', 100, 58, 900, 56, ['text' => 'Al-Noor Masjid', 'fontSize' => 48, 'fontWeight' => 700, 'color' => '#FFFFFF', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::text('ptl-sub', 'Daily Prayer Times', 100, 118, 700, 40, ['text' => 'Daily Prayer Times', 'fontSize' => 24, 'fontWeight' => 400, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::widget('clock', 'Clock', 1100, 55, 480, 100, [
                'timezone' => 'Europe/London',
                'hourFormat' => '24',
                'showSeconds' => false,
                'showDate' => true,
                'showWeekday' => true,
                'dateFormat' => 'long',
            ], ['color' => '#E2E8F0', 'fontSize' => 28, 'textAlign' => 'right', 'fill' => 'transparent', 'borderRadius' => 0]),
            LayoutSchemaBuilder::logoPlaceholder('ptl-logo', 'Logo', 1720, 50, 120, 120, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::panel('ptl-table', 'Prayer table', 60, 210, 1100, 520, ['fill' => 'rgba(255,255,255,0.04)', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptl-col-prayer', 'Prayer', 100, 230, 200, 32, ['text' => 'Prayer', 'fontSize' => 18, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'left']),
            LayoutSchemaBuilder::text('ptl-col-time', 'Time', 900, 230, 200, 32, ['text' => 'Time', 'fontSize' => 18, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'right']),
        ];

        $rowY = 280;
        foreach ($prayers as [$id, $label, $time]) {
            array_push($elements, ...self::prayerRow("ptl-{$id}", $label, $time, 80, $rowY, 1040, 64));
            $rowY += 72;
        }

        array_push($elements,
            LayoutSchemaBuilder::panel('ptl-jumuah', 'Jumu\'ah panel', 1200, 210, 660, 240, ['fill' => 'rgba(212,175,55,0.15)', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptl-jumuah-title', 'Jumu\'ah', 1240, 240, 400, 48, ['text' => 'Jumu\'ah', 'fontSize' => 36, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('ptl-jumuah-khutbah', 'Khutbah: 12:30 PM', 1240, 300, 560, 36, ['text' => 'Khutbah: 12:30 PM', 'fontSize' => 24, 'fontWeight' => 500, 'color' => '#F8FAFC']),
            LayoutSchemaBuilder::text('ptl-jumuah-prayer', 'Prayer: 1:00 PM', 1240, 348, 560, 36, ['text' => 'Prayer: 1:00 PM', 'fontSize' => 24, 'fontWeight' => 500, 'color' => '#F8FAFC']),
            LayoutSchemaBuilder::text('ptl-jumuah-note', 'Please arrive early and maintain silence.', 1240, 396, 560, 40, ['text' => 'Please arrive early and maintain silence.', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#CBD5E1']),
            LayoutSchemaBuilder::panel('ptl-announce', 'Announcement panel', 1200, 480, 660, 250, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptl-announce-title', 'Announcements', 1240, 510, 400, 40, ['text' => 'Announcements', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ptl-announce-body', 'Community Iftar this Saturday after Maghrib. All welcome.', 1240, 560, 580, 140, ['text' => 'Community Iftar this Saturday after Maghrib. All welcome.', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::shape('ptl-corner', 'Corner accent', 0, 1020, 320, 60, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 0.35]),
            LayoutSchemaBuilder::text('ptl-footer', 'Assalamu Alaikum — Welcome to our masjid', 80, 980, 800, 36, ['text' => 'Assalamu Alaikum — Welcome to our masjid', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#94A3B8']),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Islamic, $elements, self::ISLAMIC_DARK);
    }

    /**
     * @return array<string, mixed>
     */
    private static function prayerTimesPortrait(): array
    {
        $prayers = [
            ['fajr', 'Fajr', '05:12'],
            ['sunrise', 'Sunrise', '06:38'],
            ['dhuhr', 'Dhuhr', '12:45'],
            ['asr', 'Asr', '16:02'],
            ['maghrib', 'Maghrib', '18:28'],
            ['isha', 'Isha', '19:52'],
        ];

        $elements = [
            LayoutSchemaBuilder::shape('ptp-accent', 'Top accent', 0, 0, 1080, 10, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::logoPlaceholder('ptp-logo', 'Logo', 440, 40, 200, 120, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('ptp-mosque', 'Al-Noor Masjid', 60, 180, 960, 64, ['text' => 'Al-Noor Masjid', 'fontSize' => 44, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::text('ptp-sub', 'Daily Prayer Times', 60, 250, 960, 40, ['text' => 'Daily Prayer Times', 'fontSize' => 24, 'fontWeight' => 400, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::panel('ptp-date-panel', 'Date panel', 60, 310, 960, 100, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptp-date', 'Monday, 14 September 2026', 80, 328, 920, 36, ['text' => 'Monday, 14 September 2026', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#F8FAFC', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ptp-hijri', '21 Rabi al-Awwal 1448 AH', 80, 368, 920, 32, ['text' => '21 Rabi al-Awwal 1448 AH', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
        ];

        $rowY = 440;
        foreach ($prayers as [$id, $label, $time]) {
            array_push($elements, ...self::prayerRow("ptp-{$id}", $label, $time, 60, $rowY, 960, 80));
            $rowY += 92;
        }

        array_push($elements,
            LayoutSchemaBuilder::panel('ptp-jumuah', 'Jumu\'ah panel', 60, $rowY + 20, 960, 180, ['fill' => 'rgba(212,175,55,0.18)', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptp-jumuah-title', 'Jumu\'ah', 100, $rowY + 50, 400, 48, ['text' => 'Jumu\'ah', 'fontSize' => 34, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('ptp-jumuah-khutbah', 'Khutbah: 12:30 PM', 100, $rowY + 100, 880, 36, ['text' => 'Khutbah: 12:30 PM', 'fontSize' => 24, 'fontWeight' => 500, 'color' => '#F8FAFC']),
            LayoutSchemaBuilder::text('ptp-jumuah-prayer', 'Prayer: 1:00 PM', 100, $rowY + 140, 880, 36, ['text' => 'Prayer: 1:00 PM', 'fontSize' => 24, 'fontWeight' => 500, 'color' => '#F8FAFC']),
            LayoutSchemaBuilder::panel('ptp-announce', 'Announcement panel', 60, 1680, 960, 180, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ptp-announce-title', 'Announcements', 100, 1710, 400, 40, ['text' => 'Announcements', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ptp-announce-body', 'Quran class every Sunday after Dhuhr. Sisters\' programme Wednesday 7 PM.', 100, 1760, 880, 80, ['text' => 'Quran class every Sunday after Dhuhr. Sisters\' programme Wednesday 7 PM.', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#E2E8F0', 'lineHeight' => 1.4]),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Islamic, $elements, self::ISLAMIC_DARK);
    }

    /**
     * @return array<string, mixed>
     */
    private static function jumuahAnnouncement(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('jum-accent', 'Accent bar', 0, 0, 1920, 12, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 1]),
            LayoutSchemaBuilder::panel('jum-main', 'Main panel', 80, 80, 1760, 720, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 24, 'opacity' => 1]),
            LayoutSchemaBuilder::text('jum-title', 'Jumu\'ah Announcement', 140, 120, 1200, 72, ['text' => 'Jumu\'ah Announcement', 'fontSize' => 56, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('jum-date', 'Friday, 18 September 2026', 140, 200, 800, 40, ['text' => 'Friday, 18 September 2026', 'fontSize' => 24, 'fontWeight' => 400, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::logoPlaceholder('jum-logo', 'Logo', 1680, 100, 120, 120, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::panel('jum-times', 'Times panel', 140, 280, 720, 220, ['fill' => 'rgba(212,175,55,0.12)', 'borderRadius' => 16, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::text('jum-khutbah-label', 'Khutbah', 180, 310, 200, 36, ['text' => 'Khutbah', 'fontSize' => 22, 'fontWeight' => 600, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('jum-khutbah-time', '12:30 PM', 500, 310, 300, 48, ['text' => '12:30 PM', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'right']),
            LayoutSchemaBuilder::text('jum-prayer-label', 'Prayer', 180, 380, 200, 36, ['text' => 'Prayer', 'fontSize' => 22, 'fontWeight' => 600, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('jum-prayer-time', '1:00 PM', 500, 380, 300, 48, ['text' => '1:00 PM', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'right']),
            LayoutSchemaBuilder::text('jum-speaker-label', 'Khateeb', 180, 450, 200, 36, ['text' => 'Khateeb', 'fontSize' => 22, 'fontWeight' => 600, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('jum-speaker', 'Imam Abdullah Rahman', 180, 490, 620, 40, ['text' => 'Imam Abdullah Rahman', 'fontSize' => 26, 'fontWeight' => 500, 'color' => '#F8FAFC']),
            LayoutSchemaBuilder::panel('jum-topic', 'Topic panel', 920, 280, 840, 220, ['fill' => 'rgba(255,255,255,0.04)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('jum-topic-label', 'This Week\'s Topic', 960, 310, 400, 36, ['text' => 'This Week\'s Topic', 'fontSize' => 22, 'fontWeight' => 600, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('jum-topic-body', 'Patience and Gratitude in Daily Life', 960, 360, 760, 100, ['text' => 'Patience and Gratitude in Daily Life', 'fontSize' => 32, 'fontWeight' => 600, 'color' => '#FFFFFF', 'lineHeight' => 1.3, 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::panel('jum-announce', 'Announcement panel', 140, 540, 1620, 220, ['fill' => 'rgba(255,255,255,0.04)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('jum-announce-title', 'Community Notice', 180, 570, 400, 40, ['text' => 'Community Notice', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('jum-announce-body', 'Parking is available at the rear entrance. Please switch phones to silent before entering the prayer hall.', 180, 620, 1500, 100, ['text' => 'Parking is available at the rear entrance. Please switch phones to silent before entering the prayer hall.', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::text('jum-footer', 'Assalamu Alaikum', 140, 860, 600, 36, ['text' => 'Assalamu Alaikum', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#94A3B8']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Islamic, $elements, self::ISLAMIC_DARK);
    }

    /**
     * @return array<string, mixed>
     */
    private static function ramadanTimetable(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ram-accent', 'Accent', 0, 0, 1080, 10, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ram-logo', 'Logo', 440, 36, 200, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('ram-title', 'Ramadan Timetable', 60, 150, 960, 56, ['text' => 'Ramadan Timetable', 'fontSize' => 42, 'fontWeight' => 700, 'color' => '#1E293B', 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('ram-sub', '1448 AH — Day 14', 60, 210, 960, 36, ['text' => '1448 AH — Day 14', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ram-suhoor', 'Suhoor panel', 60, 280, 960, 160, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ram-suhoor-title', 'Suhoor Ends / Fajr', 100, 300, 500, 40, ['text' => 'Suhoor Ends / Fajr', 'fontSize' => 26, 'fontWeight' => 700, 'color' => self::ISLAMIC_DARK]),
            LayoutSchemaBuilder::text('ram-suhoor-time', '04:45 AM', 600, 300, 380, 48, ['text' => '04:45 AM', 'fontSize' => 36, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'right']),
            LayoutSchemaBuilder::text('ram-suhoor-note', 'Last chance for pre-dawn meal', 100, 360, 880, 32, ['text' => 'Last chance for pre-dawn meal', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('ram-iftar', 'Iftar panel', 60, 470, 960, 160, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ram-iftar-title', 'Iftar / Maghrib', 100, 490, 500, 40, ['text' => 'Iftar / Maghrib', 'fontSize' => 26, 'fontWeight' => 700, 'color' => self::ISLAMIC_DARK]),
            LayoutSchemaBuilder::text('ram-iftar-time', '06:28 PM', 600, 490, 380, 48, ['text' => '06:28 PM', 'fontSize' => 36, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'right']),
            LayoutSchemaBuilder::text('ram-iftar-note', 'Community iftar in the hall after Maghrib', 100, 550, 880, 32, ['text' => 'Community iftar in the hall after Maghrib', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('ram-taraweeh', 'Taraweeh panel', 60, 660, 960, 160, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ram-taraweeh-title', 'Taraweeh', 100, 680, 500, 40, ['text' => 'Taraweeh', 'fontSize' => 26, 'fontWeight' => 700, 'color' => self::ISLAMIC_DARK]),
            LayoutSchemaBuilder::text('ram-taraweeh-time', '8:00 PM', 600, 680, 380, 48, ['text' => '8:00 PM', 'fontSize' => 36, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD, 'textAlign' => 'right']),
            LayoutSchemaBuilder::text('ram-taraweeh-note', 'Following Isha prayer — all welcome', 100, 740, 880, 32, ['text' => 'Following Isha prayer — all welcome', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
        ];

        array_push($elements, ...self::timetableRow('ram-dhuhr', 'Dhuhr', '12:45 PM', 60, 860, 960, 64));
        array_push($elements, ...self::timetableRow('ram-asr', 'Asr', '4:02 PM', 60, 940, 960, 64));
        array_push($elements, ...self::timetableRow('ram-isha', 'Isha', '7:52 PM', 60, 1020, 960, 64));

        array_push($elements,
            LayoutSchemaBuilder::panel('ram-message', 'Message panel', 60, 1120, 960, 280, ['fill' => self::ISLAMIC_DARK, 'borderRadius' => 16, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::text('ram-message-title', 'Ramadan Message', 100, 1150, 500, 40, ['text' => 'Ramadan Message', 'fontSize' => 26, 'fontWeight' => 700, 'color' => self::ISLAMIC_GOLD]),
            LayoutSchemaBuilder::text('ram-message-body', 'May this blessed month bring peace and reflection. Zakat and sadaqah boxes are at the entrance.', 100, 1200, 880, 160, ['text' => 'May this blessed month bring peace and reflection. Zakat and sadaqah boxes are at the entrance.', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#F8FAFC', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::shape('ram-footer-accent', 'Footer accent', 0, 1880, 1080, 40, ['fill' => self::ISLAMIC_GOLD, 'opacity' => 0.25]),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Elegant, $elements, self::ISLAMIC_LIGHT);
    }

    /**
     * @return array<string, mixed>
     */
    private static function retailSalePromotion(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('rsp-bg-stripe', 'Background stripe', 0, 0, 1080, 400, ['fill' => '#DC2626', 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::text('rsp-sale', 'SALE', 60, 80, 960, 120, ['text' => 'SALE', 'fontSize' => 96, 'fontWeight' => 900, 'color' => '#FFFFFF', 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('rsp-discount', 'UP TO 50% OFF', 60, 220, 960, 64, ['text' => 'UP TO 50% OFF', 'fontSize' => 48, 'fontWeight' => 700, 'color' => '#FEF08A', 'textAlign' => 'center']),
            LayoutSchemaBuilder::logoPlaceholder('rsp-logo', 'Logo', 60, 420, 160, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('rsp-store', 'Your Store Name', 240, 440, 780, 48, ['text' => 'Your Store Name', 'fontSize' => 32, 'fontWeight' => 600, 'color' => '#1E293B', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::imagePlaceholder('rsp-product', 'Replace image', 60, 560, 960, 720, ['borderRadius' => 20]),
            LayoutSchemaBuilder::panel('rsp-cta-panel', 'CTA panel', 60, 1320, 960, 200, ['fill' => '#1E293B', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('rsp-cta', 'Shop Now — Limited Time Only', 100, 1360, 880, 56, ['text' => 'Shop Now — Limited Time Only', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('rsp-dates', 'Valid until 30 September 2026', 100, 1430, 880, 36, ['text' => 'Valid until 30 September 2026', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('rsp-badge', 'Badge', 820, 420, 200, 200, ['fill' => '#FBBF24', 'borderRadius' => 100, 'opacity' => 1]),
            LayoutSchemaBuilder::text('rsp-badge-text', '50%', 850, 480, 140, 80, ['text' => '50%', 'fontSize' => 48, 'fontWeight' => 900, 'color' => '#1E293B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('rsp-tagline', 'Selected items in store & online', 60, 1560, 960, 40, ['text' => 'Selected items in store & online', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('rsp-terms', 'Terms and conditions apply', 60, 1610, 960, 32, ['text' => 'Terms and conditions apply', 'fontSize' => 16, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Bold, $elements, '#FFF7ED');
    }

    /**
     * @return array<string, mixed>
     */
    private static function newProductLaunch(): array
    {
        $elements = [
            LayoutSchemaBuilder::panel('npl-hero', 'Hero panel', 0, 0, 1200, 1080, ['fill' => '#0F172A', 'borderRadius' => 0, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::text('npl-badge', 'NEW ARRIVAL', 80, 80, 400, 40, ['text' => 'NEW ARRIVAL', 'fontSize' => 20, 'fontWeight' => 700, 'color' => '#38BDF8', 'textAlign' => 'left']),
            LayoutSchemaBuilder::text('npl-title', 'Introducing the Pro Series X', 80, 140, 700, 120, ['text' => 'Introducing the Pro Series X', 'fontSize' => 52, 'fontWeight' => 800, 'color' => '#FFFFFF', 'lineHeight' => 1.15, 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('npl-sub', 'Next-generation performance, redesigned for you.', 80, 280, 640, 80, ['text' => 'Next-generation performance, redesigned for you.', 'fontSize' => 24, 'fontWeight' => 400, 'color' => '#94A3B8', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::imagePlaceholder('npl-product', 'Your image', 80, 400, 640, 560, ['borderRadius' => 16]),
            LayoutSchemaBuilder::panel('npl-features', 'Features panel', 780, 80, 1060, 880, ['fill' => '#FFFFFF', 'borderRadius' => 24, 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('npl-logo', 'Logo', 820, 120, 140, 80, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('npl-feat-title', 'Key Features', 820, 220, 900, 48, ['text' => 'Key Features', 'fontSize' => 32, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::shape('npl-dot-1', 'Bullet', 820, 300, 12, 12, ['fill' => '#38BDF8', 'borderRadius' => 6, 'opacity' => 1]),
            LayoutSchemaBuilder::text('npl-feat-1', 'Ultra-fast processor with 12-hour battery', 850, 290, 900, 40, ['text' => 'Ultra-fast processor with 12-hour battery', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#334155']),
            LayoutSchemaBuilder::shape('npl-dot-2', 'Bullet', 820, 360, 12, 12, ['fill' => '#38BDF8', 'borderRadius' => 6, 'opacity' => 1]),
            LayoutSchemaBuilder::text('npl-feat-2', 'Premium build with sustainable materials', 850, 350, 900, 40, ['text' => 'Premium build with sustainable materials', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#334155']),
            LayoutSchemaBuilder::shape('npl-dot-3', 'Bullet', 820, 420, 12, 12, ['fill' => '#38BDF8', 'borderRadius' => 6, 'opacity' => 1]),
            LayoutSchemaBuilder::text('npl-feat-3', 'Available in three colours from £299', 850, 410, 900, 40, ['text' => 'Available in three colours from £299', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#334155']),
            LayoutSchemaBuilder::panel('npl-cta', 'CTA panel', 820, 780, 980, 120, ['fill' => '#2563EB', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('npl-cta-text', 'Discover More In Store Today', 860, 815, 900, 48, ['text' => 'Discover More In Store Today', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('npl-price', 'From £299', 820, 680, 400, 56, ['text' => 'From £299', 'fontSize' => 40, 'fontWeight' => 800, 'color' => '#2563EB']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Modern, $elements, '#EEF2FF');
    }

    /**
     * @return array<string, mixed>
     */
    private static function restaurantDigitalMenu(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('rdm-header-bg', 'Header background', 0, 0, 1080, 220, ['fill' => '#1E293B', 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::logoPlaceholder('rdm-logo', 'Logo', 60, 40, 140, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('rdm-title', 'The Garden Kitchen', 220, 50, 800, 56, ['text' => 'The Garden Kitchen', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#FFFFFF', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::text('rdm-sub', 'Fresh · Local · Seasonal', 220, 110, 600, 36, ['text' => 'Fresh · Local · Seasonal', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#94A3B8', 'brandBinding' => 'brand.tagline']),
            LayoutSchemaBuilder::text('rdm-section-starters', 'Starters', 60, 250, 400, 44, ['text' => 'Starters', 'fontSize' => 30, 'fontWeight' => 700, 'color' => '#B45309']),
        ];

        array_push($elements, ...self::menuItem('rdm-s1', 'Soup of the Day', '£6.50', 60, 300, 460));
        array_push($elements, ...self::menuItem('rdm-s2', 'Garlic Bread', '£4.00', 60, 350, 460));
        array_push($elements, ...self::menuItem('rdm-s3', 'House Salad', '£5.50', 60, 400, 460));

        array_push($elements,
            LayoutSchemaBuilder::text('rdm-section-mains', 'Mains', 560, 250, 400, 44, ['text' => 'Mains', 'fontSize' => 30, 'fontWeight' => 700, 'color' => '#B45309']),
        );
        array_push($elements, ...self::menuItem('rdm-m1', 'Grilled Salmon', '£16.95', 560, 300, 460));
        array_push($elements, ...self::menuItem('rdm-m2', 'Beef Burger & Fries', '£14.50', 560, 350, 460));
        array_push($elements, ...self::menuItem('rdm-m3', 'Vegetable Risotto', '£12.95', 560, 400, 460));

        array_push($elements,
            LayoutSchemaBuilder::panel('rdm-featured', 'Featured panel', 60, 500, 960, 340, ['fill' => '#FEF3C7', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('rdm-featured-label', 'Chef\'s Special', 100, 530, 400, 40, ['text' => 'Chef\'s Special', 'fontSize' => 24, 'fontWeight' => 700, 'color' => '#92400E']),
            LayoutSchemaBuilder::imagePlaceholder('rdm-featured-img', 'Replace image', 100, 580, 280, 220, ['borderRadius' => 12]),
            LayoutSchemaBuilder::text('rdm-featured-name', 'Slow-Roasted Lamb Shank', 420, 580, 560, 48, ['text' => 'Slow-Roasted Lamb Shank', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#1E293B']),
            LayoutSchemaBuilder::text('rdm-featured-desc', 'Served with rosemary potatoes and seasonal vegetables.', 420, 640, 560, 80, ['text' => 'Served with rosemary potatoes and seasonal vegetables.', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::text('rdm-featured-price', '£19.95', 420, 740, 200, 48, ['text' => '£19.95', 'fontSize' => 32, 'fontWeight' => 800, 'color' => '#B45309']),
            LayoutSchemaBuilder::text('rdm-section-drinks', 'Drinks', 60, 880, 400, 44, ['text' => 'Drinks', 'fontSize' => 30, 'fontWeight' => 700, 'color' => '#B45309']),
        );
        array_push($elements, ...self::menuItem('rdm-d1', 'Fresh Orange Juice', '£3.50', 60, 930, 460));
        array_push($elements, ...self::menuItem('rdm-d2', 'Espresso', '£2.50', 60, 980, 460));
        array_push($elements, ...self::menuItem('rdm-d3', 'Sparkling Water', '£2.00', 560, 930, 460));
        array_push($elements, ...self::menuItem('rdm-d4', 'House Wine (glass)', '£5.95', 560, 980, 460));

        array_push($elements,
            LayoutSchemaBuilder::panel('rdm-footer', 'Footer panel', 0, 1780, 1080, 140, ['fill' => '#1E293B', 'borderRadius' => 0, 'opacity' => 1]),
            LayoutSchemaBuilder::text('rdm-footer-text', 'Ask your server about allergens · WiFi: GardenGuest', 60, 1820, 960, 40, ['text' => 'Ask your server about allergens · WiFi: GardenGuest', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Bold, $elements, '#FFFBEB');
    }

    /**
     * @return array<string, mixed>
     */
    private static function cafePromotion(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('cafe-bg', 'Background shape', 0, 0, 1080, 600, ['fill' => '#78350F', 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::logoPlaceholder('cafe-logo', 'Logo', 440, 40, 200, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('cafe-title', 'Seasonal Special', 60, 160, 960, 64, ['text' => 'Seasonal Special', 'fontSize' => 44, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('cafe-drink', 'Pumpkin Spice Latte', 60, 240, 960, 72, ['text' => 'Pumpkin Spice Latte', 'fontSize' => 52, 'fontWeight' => 800, 'color' => '#FDE68A', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('cafe-price', '£4.50', 60, 330, 960, 56, ['text' => '£4.50', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::imagePlaceholder('cafe-image', 'Replace image', 140, 620, 800, 720, ['borderRadius' => 24]),
            LayoutSchemaBuilder::panel('cafe-offer', 'Offer panel', 60, 1380, 960, 180, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('cafe-offer-title', 'Pair with a Pastry — Save 15%', 100, 1420, 880, 48, ['text' => 'Pair with a Pastry — Save 15%', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#78350F', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('cafe-offer-sub', 'Available all day · Dine in or takeaway', 100, 1480, 880, 36, ['text' => 'Available all day · Dine in or takeaway', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('cafe-accent', 'Accent dots', 480, 1620, 120, 120, ['fill' => '#FDE68A', 'borderRadius' => 60, 'opacity' => 0.5]),
            LayoutSchemaBuilder::text('cafe-footer', 'The Corner Café — Open 7am–6pm', 60, 1720, 960, 36, ['text' => 'The Corner Café — Open 7am–6pm', 'fontSize' => 20, 'fontWeight' => 500, 'color' => '#92400E', 'textAlign' => 'center', 'brandBinding' => 'brand.business_name']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Elegant, $elements, '#FAF7F2');
    }

    /**
     * @return array<string, mixed>
     */
    private static function corporateWelcome(): array
    {
        $elements = [
            LayoutSchemaBuilder::panel('cw-main', 'Main panel', 80, 120, 1760, 840, ['fill' => '#FFFFFF', 'borderRadius' => 24, 'opacity' => 0.95]),
            LayoutSchemaBuilder::logoPlaceholder('cw-logo', 'Logo', 140, 180, 200, 120, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('cw-welcome', 'Welcome', 380, 180, 800, 80, ['text' => 'Welcome', 'fontSize' => 64, 'fontWeight' => 700, 'color' => '#0F172A', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('cw-company', 'Acme Corporation', 380, 270, 800, 48, ['text' => 'Acme Corporation', 'fontSize' => 32, 'fontWeight' => 500, 'color' => '#475569', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::shape('cw-divider', 'Divider', 140, 380, 1640, 2, ['fill' => '#E2E8F0', 'opacity' => 1]),
            LayoutSchemaBuilder::text('cw-message', 'We\'re glad you\'re here. Please check in at reception and collect your visitor badge.', 140, 420, 1200, 80, ['text' => 'We\'re glad you\'re here. Please check in at reception and collect your visitor badge.', 'fontSize' => 26, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::panel('cw-info-1', 'Reception panel', 140, 540, 500, 160, ['fill' => '#F1F5F9', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('cw-info-1-title', 'Reception', 180, 570, 300, 36, ['text' => 'Reception', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('cw-info-1-body', 'Ground Floor · Ext. 100', 180, 620, 420, 36, ['text' => 'Ground Floor · Ext. 100', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('cw-info-2', 'WiFi panel', 680, 540, 500, 160, ['fill' => '#F1F5F9', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('cw-info-2-title', 'Guest WiFi', 720, 570, 300, 36, ['text' => 'Guest WiFi', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('cw-info-2-body', 'Network: AcmeGuest', 720, 620, 420, 36, ['text' => 'Network: AcmeGuest', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('cw-info-3', 'Hours panel', 1220, 540, 520, 160, ['fill' => '#F1F5F9', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('cw-info-3-title', 'Office Hours', 1260, 570, 300, 36, ['text' => 'Office Hours', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('cw-info-3-body', 'Mon–Fri · 8:30 AM – 5:30 PM', 1260, 620, 440, 36, ['text' => 'Mon–Fri · 8:30 AM – 5:30 PM', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::imagePlaceholder('cw-image', 'Your image', 1400, 180, 420, 320, ['borderRadius' => 16]),
            LayoutSchemaBuilder::widget('clock', 'Clock', 140, 760, 420, 100, [
                'timezone' => 'Europe/London',
                'hourFormat' => '24',
                'showSeconds' => false,
                'showDate' => true,
                'showWeekday' => true,
                'dateFormat' => 'long',
            ], ['color' => '#0F172A', 'fontSize' => 22, 'textAlign' => 'left', 'fill' => 'transparent', 'borderRadius' => 0]),
            LayoutSchemaBuilder::widget('info_card', 'Visitors today', 680, 740, 420, 140, [
                'heading' => 'Visitors Today',
                'subheading' => '',
                'body' => 'Lobby check-ins',
                'value' => '124',
                'footer' => '',
                'layout' => 'stack',
                'mediaAssetId' => null,
            ], ['color' => '#0F172A', 'fill' => '#EFF6FF', 'borderRadius' => 12, 'fontSize' => 18]),
            LayoutSchemaBuilder::shape('cw-accent', 'Accent bar', 0, 0, 1920, 8, ['fill' => '#2563EB', 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Corporate, $elements, '#F1F5F9');
    }

    /**
     * @return array<string, mixed>
     */
    private static function corporateAnnouncement(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ca-accent', 'Accent bar', 0, 0, 1920, 10, ['fill' => '#2563EB', 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::logoPlaceholder('ca-logo', 'Logo', 80, 60, 160, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('ca-title', 'Company Announcement', 280, 70, 1200, 72, ['text' => 'Company Announcement', 'fontSize' => 52, 'fontWeight' => 700, 'color' => '#0F172A', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('ca-date', '14 September 2026', 280, 150, 400, 36, ['text' => '14 September 2026', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('ca-main', 'Main content panel', 80, 220, 1200, 720, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca-headline', 'Office Relocation — Building B', 120, 260, 1100, 56, ['text' => 'Office Relocation — Building B', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ca-body', 'From 1 October, the Finance and HR teams will move to Building B, Floor 3. IT support desks remain on Floor 1. Please update your desk location in the internal directory.', 120, 340, 1100, 200, ['text' => 'From 1 October, the Finance and HR teams will move to Building B, Floor 3. IT support desks remain on Floor 1. Please update your desk location in the internal directory.', 'fontSize' => 24, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::panel('ca-highlight', 'Highlight panel', 120, 580, 1100, 120, ['fill' => '#EFF6FF', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca-highlight-text', 'Moving day support: helpdesk@acme.com · Ext. 4500', 150, 620, 1040, 40, ['text' => 'Moving day support: helpdesk@acme.com · Ext. 4500', 'fontSize' => 22, 'fontWeight' => 600, 'color' => '#2563EB']),
            LayoutSchemaBuilder::panel('ca-sidebar', 'Sidebar panel', 1320, 220, 520, 720, ['fill' => '#F8FAFC', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca-sidebar-title', 'Quick Links', 1360, 260, 400, 40, ['text' => 'Quick Links', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ca-link-1', '• Staff handbook', 1360, 320, 440, 36, ['text' => '• Staff handbook', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ca-link-2', '• Health & safety', 1360, 370, 440, 36, ['text' => '• Health & safety', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ca-link-3', '• Room bookings', 1360, 420, 440, 36, ['text' => '• Room bookings', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::imagePlaceholder('ca-image', 'Replace image', 1360, 520, 440, 360, ['borderRadius' => 12]),
            LayoutSchemaBuilder::text('ca-footer', 'Internal use only — Acme Corporation', 80, 980, 600, 32, ['text' => 'Internal use only — Acme Corporation', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'brandBinding' => 'brand.business_name']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Corporate, $elements, '#F1F5F9');
    }

    /**
     * @return array<string, mixed>
     */
    private static function schoolAnnouncement(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('sa-header', 'Header band', 0, 0, 1920, 160, ['fill' => '#1D4ED8', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('sa-logo', 'Logo', 80, 30, 120, 100),
            LayoutSchemaBuilder::text('sa-school', 'Riverside Academy', 220, 40, 800, 56, ['text' => 'Riverside Academy', 'fontSize' => 42, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('sa-tagline', 'Excellence in Education', 220, 100, 600, 36, ['text' => 'Excellence in Education', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#BFDBFE']),
            LayoutSchemaBuilder::panel('sa-main', 'Main panel', 80, 200, 1200, 760, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('sa-title', 'Important Announcement', 120, 240, 800, 56, ['text' => 'Important Announcement', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('sa-date', 'Week commencing 14 September', 120, 300, 600, 36, ['text' => 'Week commencing 14 September', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::text('sa-body', 'Parent-teacher meetings will take place on Thursday 18 September from 4:00 PM to 7:00 PM. Please book your slot via the parent portal. School finishes at 3:15 PM on this day.', 120, 360, 1100, 180, ['text' => 'Parent-teacher meetings will take place on Thursday 18 September from 4:00 PM to 7:00 PM. Please book your slot via the parent portal. School finishes at 3:15 PM on this day.', 'fontSize' => 26, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::panel('sa-notice', 'Notice panel', 120, 580, 1100, 140, ['fill' => '#FEF3C7', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('sa-notice-text', 'Reminder: PE kits required on Tuesday and Friday.', 150, 630, 1040, 40, ['text' => 'Reminder: PE kits required on Tuesday and Friday.', 'fontSize' => 24, 'fontWeight' => 600, 'color' => '#92400E']),
            LayoutSchemaBuilder::panel('sa-sidebar', 'Events panel', 1320, 200, 520, 760, ['fill' => '#F8FAFC', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('sa-events-title', 'Upcoming Events', 1360, 240, 400, 40, ['text' => 'Upcoming Events', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('sa-event-1', 'Mon — Year 7 Assembly', 1360, 300, 440, 36, ['text' => 'Mon — Year 7 Assembly', 'fontSize' => 20, 'fontWeight' => 500, 'color' => '#475569']),
            LayoutSchemaBuilder::text('sa-event-2', 'Wed — Science Fair', 1360, 350, 440, 36, ['text' => 'Wed — Science Fair', 'fontSize' => 20, 'fontWeight' => 500, 'color' => '#475569']),
            LayoutSchemaBuilder::text('sa-event-3', 'Fri — Sports Day', 1360, 400, 440, 36, ['text' => 'Fri — Sports Day', 'fontSize' => 20, 'fontWeight' => 500, 'color' => '#475569']),
            LayoutSchemaBuilder::imagePlaceholder('sa-image', 'Replace image', 1360, 480, 440, 420, ['borderRadius' => 12]),
            LayoutSchemaBuilder::text('sa-footer', 'www.riverside-academy.edu', 80, 1000, 500, 32, ['text' => 'www.riverside-academy.edu', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Clean, $elements, '#FFFFFF');
    }

    /**
     * @return array<string, mixed>
     */
    private static function educationTimetable(): array
    {
        $rows = [
            ['mon-1', 'Monday 09:00', 'Mathematics — Room 12', 200],
            ['mon-2', 'Monday 11:00', 'English Literature — Room 8', 272],
            ['tue-1', 'Tuesday 09:00', 'Science Lab — Room 15', 344],
            ['tue-2', 'Tuesday 14:00', 'History — Room 6', 416],
            ['wed-1', 'Wednesday 10:00', 'Art & Design — Studio 2', 488],
            ['wed-2', 'Wednesday 13:00', 'Physical Education — Sports Hall', 560],
            ['thu-1', 'Thursday 09:00', 'Computing — IT Suite', 632],
            ['thu-2', 'Thursday 11:00', 'Languages — Room 10', 704],
        ];

        $elements = [
            LayoutSchemaBuilder::logoPlaceholder('et-logo', 'Logo', 80, 50, 120, 80),
            LayoutSchemaBuilder::text('et-title', 'Class Timetable', 220, 50, 800, 56, ['text' => 'Class Timetable', 'fontSize' => 44, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('et-sub', 'Year 10 · Autumn Term 2026', 220, 110, 600, 36, ['text' => 'Year 10 · Autumn Term 2026', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('et-table', 'Timetable panel', 80, 180, 1760, 760, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('et-col-day', 'Day / Time', 120, 210, 300, 32, ['text' => 'Day / Time', 'fontSize' => 18, 'fontWeight' => 700, 'color' => '#1D4ED8']),
            LayoutSchemaBuilder::text('et-col-class', 'Class / Location', 500, 210, 400, 32, ['text' => 'Class / Location', 'fontSize' => 18, 'fontWeight' => 700, 'color' => '#1D4ED8']),
        ];

        foreach ($rows as [$id, $label, $value, $y]) {
            array_push($elements, ...self::timetableRow("et-{$id}", $label, $value, 120, $y, 1680, 56));
        }

        array_push($elements,
            LayoutSchemaBuilder::panel('et-note', 'Note panel', 80, 980, 1200, 60, ['fill' => '#EFF6FF', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('et-note-text', 'Timetable subject to change. Check notice boards for updates.', 120, 998, 1100, 32, ['text' => 'Timetable subject to change. Check notice boards for updates.', 'fontSize' => 18, 'fontWeight' => 500, 'color' => '#2563EB']),
            LayoutSchemaBuilder::imagePlaceholder('et-image', 'Replace image', 1320, 980, 520, 60, ['borderRadius' => 8]),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Clean, $elements, '#F8FAFC');
    }

    /**
     * @return array<string, mixed>
     */
    private static function healthcareWaitingRoom(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('hw-header', 'Header band', 0, 0, 1920, 140, ['fill' => '#0EA5E9', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('hw-logo', 'Logo', 80, 20, 120, 100),
            LayoutSchemaBuilder::text('hw-clinic', 'Greenfield Medical Centre', 220, 30, 900, 52, ['text' => 'Greenfield Medical Centre', 'fontSize' => 38, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('hw-sub', 'Patient Information', 220, 85, 500, 36, ['text' => 'Patient Information', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E0F2FE']),
            LayoutSchemaBuilder::panel('hw-welcome', 'Welcome panel', 80, 180, 880, 400, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hw-welcome-title', 'Thank You for Waiting', 120, 220, 800, 48, ['text' => 'Thank You for Waiting', 'fontSize' => 34, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('hw-welcome-body', 'Please take a seat. You will be called when the clinician is ready. If your condition worsens, please inform reception immediately.', 120, 290, 800, 140, ['text' => 'Please take a seat. You will be called when the clinician is ready. If your condition worsens, please inform reception immediately.', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::panel('hw-hours', 'Hours panel', 80, 620, 880, 200, ['fill' => '#F0FDFA', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hw-hours-title', 'Opening Hours', 120, 650, 400, 40, ['text' => 'Opening Hours', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F766E']),
            LayoutSchemaBuilder::text('hw-hours-body', 'Mon–Fri: 8:00 AM – 6:30 PM\nSat: 9:00 AM – 1:00 PM\nSun: Closed', 120, 700, 800, 100, ['text' => "Mon–Fri: 8:00 AM – 6:30 PM\nSat: 9:00 AM – 1:00 PM\nSun: Closed", 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::panel('hw-services', 'Services panel', 1000, 180, 840, 400, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hw-services-title', 'Services Available', 1040, 220, 500, 40, ['text' => 'Services Available', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('hw-svc-1', '• GP appointments', 1040, 280, 400, 36, ['text' => '• GP appointments', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('hw-svc-2', '• Nurse clinics', 1040, 330, 400, 36, ['text' => '• Nurse clinics', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('hw-svc-3', '• Pharmacy (next door)', 1040, 380, 500, 36, ['text' => '• Pharmacy (next door)', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('hw-svc-4', '• Online booking: greenfield.nhs.uk', 1040, 430, 700, 36, ['text' => '• Online booking: greenfield.nhs.uk', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::imagePlaceholder('hw-image', 'Replace image', 1000, 620, 840, 200, ['borderRadius' => 16]),
            LayoutSchemaBuilder::panel('hw-notice', 'Notice panel', 80, 860, 1760, 140, ['fill' => '#EFF6FF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hw-notice-title', 'Health Reminder', 120, 890, 300, 36, ['text' => 'Health Reminder', 'fontSize' => 24, 'fontWeight' => 700, 'color' => '#2563EB']),
            LayoutSchemaBuilder::text('hw-notice-body', 'Flu vaccination clinics available — ask at reception. Hand sanitiser stations are located throughout the building.', 120, 940, 1680, 40, ['text' => 'Flu vaccination clinics available — ask at reception. Hand sanitiser stations are located throughout the building.', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#334155']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Light, $elements, '#FCFCFC');
    }

    /**
     * @return array<string, mixed>
     */
    private static function hotelWelcome(): array
    {
        $elements = [
            LayoutSchemaBuilder::imagePlaceholder('hwl-hero', 'Replace image', 0, 0, 1080, 800, ['borderRadius' => 0]),
            LayoutSchemaBuilder::shape('hwl-overlay', 'Overlay', 0, 500, 1080, 300, ['fill' => 'rgba(15,23,42,0.65)', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('hwl-logo', 'Logo', 440, 40, 200, 100, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('hwl-welcome', 'Welcome', 60, 540, 960, 72, ['text' => 'Welcome', 'fontSize' => 56, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('hwl-guest', 'Dear Guest', 60, 620, 960, 48, ['text' => 'Dear Guest', 'fontSize' => 32, 'fontWeight' => 400, 'color' => '#E2E8F0', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('hwl-info', 'Info panel', 60, 840, 960, 480, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hwl-hotel', 'The Grand Horizon Hotel', 100, 880, 880, 48, ['text' => 'The Grand Horizon Hotel', 'fontSize' => 32, 'fontWeight' => 700, 'color' => '#1E293B', 'textAlign' => 'center', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::text('hwl-checkin', 'Check-in: 3:00 PM · Check-out: 11:00 AM', 100, 940, 880, 36, ['text' => 'Check-in: 3:00 PM · Check-out: 11:00 AM', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('hwl-svc-1', 'Dining panel', 100, 1000, 420, 140, ['fill' => '#FAF7F2', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hwl-svc-1-title', 'Dining', 130, 1020, 200, 32, ['text' => 'Dining', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#92400E']),
            LayoutSchemaBuilder::text('hwl-svc-1-body', 'Restaurant · 7 AM – 10 PM', 130, 1060, 360, 32, ['text' => 'Restaurant · 7 AM – 10 PM', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('hwl-svc-2', 'Spa panel', 560, 1000, 420, 140, ['fill' => '#FAF7F2', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('hwl-svc-2-title', 'Spa & Wellness', 590, 1020, 300, 32, ['text' => 'Spa & Wellness', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#92400E']),
            LayoutSchemaBuilder::text('hwl-svc-2-body', 'Open daily · Book at reception', 590, 1060, 360, 32, ['text' => 'Open daily · Book at reception', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('hwl-svc-3', 'Concierge panel', 100, 1160, 880, 120, ['fill' => '#1E293B', 'borderRadius' => 12, 'opacity' => 1, 'brandBinding' => 'brand.primary_color']),
            LayoutSchemaBuilder::text('hwl-concierge', 'Concierge · Dial 0 from your room', 130, 1200, 820, 40, ['text' => 'Concierge · Dial 0 from your room', 'fontSize' => 22, 'fontWeight' => 600, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('hwl-wifi', 'WiFi: GrandHorizon · Password: welcome2026', 60, 1380, 960, 36, ['text' => 'WiFi: GrandHorizon · Password: welcome2026', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::widget('clock', 'Clock', 60, 1440, 460, 120, [
                'timezone' => 'Europe/London',
                'hourFormat' => '12',
                'showSeconds' => false,
                'showDate' => true,
                'showWeekday' => true,
                'dateFormat' => 'long',
            ], ['color' => '#1E293B', 'fontSize' => 24, 'textAlign' => 'center', 'fill' => '#FFFFFF', 'borderRadius' => 16]),
            LayoutSchemaBuilder::widget('weather', 'Weather', 560, 1440, 460, 120, [
                'location' => 'Nottingham, United Kingdom',
                'units' => 'c',
                'showTemp' => true,
                'showCondition' => true,
                'showHighLow' => false,
                'layout' => 'stack',
            ], ['color' => '#1E293B', 'fontSize' => 20, 'textAlign' => 'center', 'fill' => '#FFFFFF', 'borderRadius' => 16]),
            LayoutSchemaBuilder::text('hwl-footer', 'We hope you enjoy your stay', 60, 1680, 960, 36, ['text' => 'We hope you enjoy your stay', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#92400E', 'textAlign' => 'center']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Elegant, $elements, '#FAF7F2');
    }

    /**
     * @return array<string, mixed>
     */
    private static function gymClassSchedule(): array
    {
        $classes = [
            ['c1', '6:30 AM', 'HIIT Blast', 'Studio A', 340],
            ['c2', '8:00 AM', 'Yoga Flow', 'Studio B', 420],
            ['c3', '12:00 PM', 'Spin Cycle', 'Studio A', 500],
            ['c4', '5:30 PM', 'BoxFit', 'Main Floor', 580],
            ['c5', '6:45 PM', 'Pilates', 'Studio B', 660],
            ['c6', '7:30 PM', 'Stretch & Recover', 'Studio A', 740],
        ];

        $elements = [
            LayoutSchemaBuilder::shape('gcs-header', 'Header', 0, 0, 1080, 280, ['fill' => '#111827', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('gcs-logo', 'Logo', 440, 30, 200, 90),
            LayoutSchemaBuilder::text('gcs-title', 'Today\'s Classes', 60, 130, 960, 56, ['text' => 'Today\'s Classes', 'fontSize' => 44, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('gcs-date', 'Monday, 14 September', 60, 200, 960, 36, ['text' => 'Monday, 14 September', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#9CA3AF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('gcs-table', 'Schedule panel', 60, 310, 960, 520, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('gcs-col-time', 'Time', 100, 340, 160, 32, ['text' => 'Time', 'fontSize' => 18, 'fontWeight' => 700, 'color' => '#EF4444']),
            LayoutSchemaBuilder::text('gcs-col-class', 'Class', 280, 340, 300, 32, ['text' => 'Class', 'fontSize' => 18, 'fontWeight' => 700, 'color' => '#EF4444']),
            LayoutSchemaBuilder::text('gcs-col-room', 'Location', 680, 340, 200, 32, ['text' => 'Location', 'fontSize' => 18, 'fontWeight' => 700, 'color' => '#EF4444', 'textAlign' => 'right']),
        ];

        foreach ($classes as [$id, $time, $name, $room, $y]) {
            array_push($elements,
                LayoutSchemaBuilder::panel("gcs-{$id}-row", $name, 80, $y, 920, 64, ['fill' => '#F9FAFB', 'borderRadius' => 10, 'opacity' => 1]),
                LayoutSchemaBuilder::text("gcs-{$id}-time", $time, 100, $y + 14, 160, 36, ['text' => $time, 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#111827']),
                LayoutSchemaBuilder::text("gcs-{$id}-name", $name, 280, $y + 14, 360, 36, ['text' => $name, 'fontSize' => 22, 'fontWeight' => 600, 'color' => '#374151']),
                LayoutSchemaBuilder::text("gcs-{$id}-room", $room, 680, $y + 14, 300, 36, ['text' => $room, 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#6B7280', 'textAlign' => 'right']),
            );
        }

        array_push($elements,
            LayoutSchemaBuilder::imagePlaceholder('gcs-image', 'Replace image', 60, 860, 960, 400, ['borderRadius' => 16]),
            LayoutSchemaBuilder::panel('gcs-cta', 'CTA panel', 60, 1300, 960, 160, ['fill' => '#EF4444', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('gcs-cta-text', 'Book your spot at the front desk', 100, 1350, 880, 48, ['text' => 'Book your spot at the front desk', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('gcs-footer', 'IronPeak Fitness · Open 5 AM – 11 PM', 60, 1500, 960, 36, ['text' => 'IronPeak Fitness · Open 5 AM – 11 PM', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#6B7280', 'textAlign' => 'center']),
        );

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Bold, $elements, '#F3F4F6');
    }

    /**
     * @return array<string, mixed>
     */
    private static function propertyShowcase(): array
    {
        $elements = [
            LayoutSchemaBuilder::imagePlaceholder('ps-hero', 'Replace image', 0, 0, 1200, 1080, ['borderRadius' => 0]),
            LayoutSchemaBuilder::shape('ps-overlay', 'Overlay', 0, 0, 1200, 1080, ['fill' => 'rgba(15,23,42,0.35)', 'opacity' => 1]),
            LayoutSchemaBuilder::panel('ps-info', 'Info panel', 1280, 80, 580, 920, ['fill' => '#FFFFFF', 'borderRadius' => 24, 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ps-logo', 'Logo', 1320, 120, 160, 80),
            LayoutSchemaBuilder::text('ps-badge', 'FOR SALE', 1520, 120, 280, 40, ['text' => 'FOR SALE', 'fontSize' => 20, 'fontWeight' => 700, 'color' => '#2563EB']),
            LayoutSchemaBuilder::text('ps-title', 'Modern Family Home', 1320, 220, 500, 56, ['text' => 'Modern Family Home', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ps-address', '42 Oak Lane, Riverside', 1320, 290, 500, 36, ['text' => '42 Oak Lane, Riverside', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::text('ps-price', '£425,000', 1320, 360, 400, 64, ['text' => '£425,000', 'fontSize' => 48, 'fontWeight' => 800, 'color' => '#2563EB']),
            LayoutSchemaBuilder::shape('ps-divider', 'Divider', 1320, 450, 500, 2, ['fill' => '#E2E8F0', 'opacity' => 1]),
            LayoutSchemaBuilder::panel('ps-stat-1', 'Beds panel', 1320, 480, 150, 100, ['fill' => '#F8FAFC', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ps-beds', '4 Beds', 1340, 510, 120, 40, ['text' => '4 Beds', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#0F172A', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ps-stat-2', 'Baths panel', 1490, 480, 150, 100, ['fill' => '#F8FAFC', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ps-baths', '2 Baths', 1510, 510, 120, 40, ['text' => '2 Baths', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#0F172A', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ps-stat-3', 'Sqft panel', 1660, 480, 160, 100, ['fill' => '#F8FAFC', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ps-sqft', '1,850 sq ft', 1670, 510, 140, 40, ['text' => '1,850 sq ft', 'fontSize' => 20, 'fontWeight' => 700, 'color' => '#0F172A', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ps-desc', 'Spacious open-plan living, south-facing garden, and off-street parking. Excellent schools nearby.', 1320, 610, 500, 120, ['text' => 'Spacious open-plan living, south-facing garden, and off-street parking. Excellent schools nearby.', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::panel('ps-cta', 'CTA panel', 1320, 780, 500, 100, ['fill' => '#2563EB', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ps-cta-text', 'Book a Viewing — 01234 567890', 1350, 810, 440, 40, ['text' => 'Book a Viewing — 01234 567890', 'fontSize' => 22, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ps-hero-title', 'Featured Property', 80, 80, 600, 48, ['text' => 'Featured Property', 'fontSize' => 32, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ps-agent', 'Premier Estates', 80, 980, 400, 36, ['text' => 'Premier Estates', 'fontSize' => 24, 'fontWeight' => 600, 'color' => '#FFFFFF']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Modern, $elements, '#EEF2FF');
    }

    /**
     * @return array<string, mixed>
     */
    private static function eventWelcome(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ew-bg-accent', 'Background accent', 0, 0, 1920, 1080, ['fill' => '#0F172A', 'opacity' => 1]),
            LayoutSchemaBuilder::shape('ew-gradient-bar', 'Accent bar', 0, 0, 1920, 6, ['fill' => '#A855F7', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ew-logo', 'Logo', 80, 60, 160, 100),
            LayoutSchemaBuilder::text('ew-welcome', 'Welcome', 80, 200, 1200, 100, ['text' => 'Welcome', 'fontSize' => 80, 'fontWeight' => 800, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ew-event', 'Annual Tech Summit 2026', 80, 320, 1200, 64, ['text' => 'Annual Tech Summit 2026', 'fontSize' => 44, 'fontWeight' => 600, 'color' => '#C084FC']),
            LayoutSchemaBuilder::text('ew-date', '14–16 September · Grand Convention Centre', 80, 400, 1000, 40, ['text' => '14–16 September · Grand Convention Centre', 'fontSize' => 24, 'fontWeight' => 400, 'color' => '#94A3B8']),
            LayoutSchemaBuilder::panel('ew-agenda', 'Agenda panel', 80, 500, 800, 480, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ew-agenda-title', 'Today\'s Highlights', 120, 540, 400, 40, ['text' => 'Today\'s Highlights', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ew-agenda-1', '09:00 — Opening Keynote · Hall A', 120, 600, 700, 36, ['text' => '09:00 — Opening Keynote · Hall A', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0']),
            LayoutSchemaBuilder::text('ew-agenda-2', '11:30 — Workshop Sessions · Rooms 1–4', 120, 650, 700, 36, ['text' => '11:30 — Workshop Sessions · Rooms 1–4', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0']),
            LayoutSchemaBuilder::text('ew-agenda-3', '14:00 — Panel Discussion · Main Stage', 120, 700, 700, 36, ['text' => '14:00 — Panel Discussion · Main Stage', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0']),
            LayoutSchemaBuilder::text('ew-agenda-4', '17:00 — Networking Reception · Lounge', 120, 750, 700, 36, ['text' => '17:00 — Networking Reception · Lounge', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#E2E8F0']),
            LayoutSchemaBuilder::text('ew-wifi', 'WiFi: TechSummit2026 · Password: innovate', 120, 820, 700, 36, ['text' => 'WiFi: TechSummit2026 · Password: innovate', 'fontSize' => 20, 'fontWeight' => 500, 'color' => '#A855F7']),
            LayoutSchemaBuilder::widget('countdown', 'Event countdown', 960, 200, 880, 260, [
                'title' => 'Summit Starts In',
                'targetAt' => '2026-09-14T09:00:00+01:00',
                'timezone' => 'Europe/London',
                'showDays' => true,
                'showHours' => true,
                'showMinutes' => true,
                'showSeconds' => true,
                'completionMessage' => 'Welcome — the summit has begun',
            ], ['color' => '#FFFFFF', 'fontSize' => 28, 'textAlign' => 'center', 'fill' => 'rgba(168,85,247,0.2)', 'borderRadius' => 20]),
            LayoutSchemaBuilder::imagePlaceholder('ew-image', 'Replace image', 960, 500, 880, 480, ['borderRadius' => 20]),
            LayoutSchemaBuilder::text('ew-footer', 'Please wear your badge at all times', 80, 1020, 600, 32, ['text' => 'Please wear your badge at all times', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Dark, $elements, '#0F172A');
    }

    /**
     * @return array<string, mixed>
     */
    private static function communityAnnouncement(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ca2-header', 'Header', 0, 0, 1920, 120, ['fill' => '#059669', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ca2-logo', 'Logo', 80, 10, 120, 100),
            LayoutSchemaBuilder::text('ca2-org', 'Riverside Community Centre', 220, 30, 900, 48, ['text' => 'Riverside Community Centre', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ca2-sub', 'Serving our neighbourhood since 1985', 220, 80, 700, 32, ['text' => 'Serving our neighbourhood since 1985', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#A7F3D0']),
            LayoutSchemaBuilder::panel('ca2-main', 'Main panel', 80, 160, 1200, 800, ['fill' => '#FFFFFF', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca2-title', 'Community Announcement', 120, 200, 800, 56, ['text' => 'Community Announcement', 'fontSize' => 40, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ca2-headline', 'Summer Fair — Saturday 20 September', 120, 280, 1100, 48, ['text' => 'Summer Fair — Saturday 20 September', 'fontSize' => 32, 'fontWeight' => 600, 'color' => '#059669']),
            LayoutSchemaBuilder::text('ca2-body', 'Join us for food stalls, live music, children\'s activities, and a raffle. Gates open at 11 AM. Free entry for all residents. Volunteers still needed — sign up at reception.', 120, 350, 1100, 160, ['text' => 'Join us for food stalls, live music, children\'s activities, and a raffle. Gates open at 11 AM. Free entry for all residents. Volunteers still needed — sign up at reception.', 'fontSize' => 24, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::panel('ca2-contact', 'Contact panel', 120, 560, 1100, 120, ['fill' => '#ECFDF5', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca2-contact-text', 'Enquiries: info@riverside-cc.org · 01234 567890', 150, 600, 1040, 40, ['text' => 'Enquiries: info@riverside-cc.org · 01234 567890', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#047857']),
            LayoutSchemaBuilder::imagePlaceholder('ca2-image', 'Replace image', 120, 720, 500, 200, ['borderRadius' => 12]),
            LayoutSchemaBuilder::panel('ca2-sidebar', 'Sidebar panel', 1320, 160, 520, 800, ['fill' => '#F8FAFC', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ca2-hours-title', 'Centre Hours', 1360, 200, 300, 36, ['text' => 'Centre Hours', 'fontSize' => 24, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ca2-hours', 'Mon–Fri: 9 AM – 8 PM\nSat: 10 AM – 4 PM\nSun: Closed', 1360, 250, 440, 100, ['text' => "Mon–Fri: 9 AM – 8 PM\nSat: 10 AM – 4 PM\nSun: Closed", 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::text('ca2-programs-title', 'Regular Programmes', 1360, 380, 400, 36, ['text' => 'Regular Programmes', 'fontSize' => 24, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ca2-prog-1', '• Youth club — Wednesdays', 1360, 430, 440, 32, ['text' => '• Youth club — Wednesdays', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ca2-prog-2', '• Seniors\' lunch — Fridays', 1360, 470, 440, 32, ['text' => '• Seniors\' lunch — Fridays', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ca2-prog-3', '• ESOL classes — Tuesdays', 1360, 510, 440, 32, ['text' => '• ESOL classes — Tuesdays', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#475569']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Clean, $elements, '#FFFFFF');
    }

    /**
     * @return array<string, mixed>
     */
    private static function generalPromotionLandscape(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('gpl-bg-left', 'Left block', 0, 0, 960, 1080, ['fill' => '#F97316', 'opacity' => 1]),
            LayoutSchemaBuilder::shape('gpl-bg-right', 'Right block', 960, 0, 960, 1080, ['fill' => '#FFF7ED', 'opacity' => 1]),
            LayoutSchemaBuilder::text('gpl-headline', 'Special Offer', 80, 120, 800, 72, ['text' => 'Special Offer', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('gpl-discount', 'Save 30% This Week', 80, 220, 800, 64, ['text' => 'Save 30% This Week', 'fontSize' => 44, 'fontWeight' => 700, 'color' => '#FEF3C7']),
            LayoutSchemaBuilder::text('gpl-desc', 'On selected products and services. Visit us in store or online.', 80, 320, 800, 100, ['text' => 'On selected products and services. Visit us in store or online.', 'fontSize' => 24, 'fontWeight' => 400, 'color' => '#FFFFFF', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::logoPlaceholder('gpl-logo', 'Logo', 80, 480, 180, 120),
            LayoutSchemaBuilder::panel('gpl-cta', 'CTA panel', 80, 680, 800, 120, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('gpl-cta-text', 'Shop Now — Offer Ends Sunday', 120, 720, 720, 48, ['text' => 'Shop Now — Offer Ends Sunday', 'fontSize' => 28, 'fontWeight' => 700, 'color' => '#EA580C', 'textAlign' => 'center']),
            LayoutSchemaBuilder::imagePlaceholder('gpl-image', 'Replace image', 1040, 120, 760, 840, ['borderRadius' => 20]),
            LayoutSchemaBuilder::text('gpl-brand', 'Your Business Name', 1040, 980, 760, 48, ['text' => 'Your Business Name', 'fontSize' => 28, 'fontWeight' => 600, 'color' => '#1E293B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('gpl-badge', 'Badge', 80, 860, 160, 160, ['fill' => '#FBBF24', 'borderRadius' => 80, 'opacity' => 1]),
            LayoutSchemaBuilder::text('gpl-badge-text', '30%', 100, 910, 120, 60, ['text' => '30%', 'fontSize' => 40, 'fontWeight' => 900, 'color' => '#1E293B', 'textAlign' => 'center']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Colourful, $elements, '#FFF7ED');
    }

    /**
     * @return array<string, mixed>
     */
    private static function generalPromotionPortrait(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('gpp-header', 'Header', 0, 0, 1080, 500, ['fill' => '#8B5CF6', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('gpp-logo', 'Logo', 440, 40, 200, 100),
            LayoutSchemaBuilder::text('gpp-headline', 'Limited Time Deal', 60, 160, 960, 72, ['text' => 'Limited Time Deal', 'fontSize' => 48, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('gpp-discount', 'Buy One Get One Free', 60, 260, 960, 56, ['text' => 'Buy One Get One Free', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#EDE9FE', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('gpp-valid', 'Valid until 30 September', 60, 340, 960, 36, ['text' => 'Valid until 30 September', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#DDD6FE', 'textAlign' => 'center']),
            LayoutSchemaBuilder::imagePlaceholder('gpp-image', 'Replace image', 60, 540, 960, 720, ['borderRadius' => 20]),
            LayoutSchemaBuilder::panel('gpp-cta', 'CTA panel', 60, 1300, 960, 160, ['fill' => '#7C3AED', 'borderRadius' => 20, 'opacity' => 1]),
            LayoutSchemaBuilder::text('gpp-cta-text', 'Visit Us Today', 100, 1350, 880, 56, ['text' => 'Visit Us Today', 'fontSize' => 32, 'fontWeight' => 700, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('gpp-footer', 'Terms apply · Your Business Name', 60, 1500, 960, 36, ['text' => 'Terms apply · Your Business Name', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('gpp-accent', 'Accent circle', 820, 420, 200, 200, ['fill' => '#FBBF24', 'borderRadius' => 100, 'opacity' => 0.8]),
            LayoutSchemaBuilder::text('gpp-accent-text', 'FREE', 860, 480, 120, 48, ['text' => 'FREE', 'fontSize' => 32, 'fontWeight' => 900, 'color' => '#1E293B', 'textAlign' => 'center']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Portrait, TemplateTheme::Colourful, $elements, '#FAF5FF');
    }

    /**
     * @return array<string, mixed>
     */
    private static function welcomeScreen(): array
    {
        $elements = [
            LayoutSchemaBuilder::imagePlaceholder('ws-bg-image', 'Your image', 0, 0, 1920, 1080, ['opacity' => 0.15]),
            LayoutSchemaBuilder::panel('ws-main', 'Main panel', 360, 200, 1200, 680, ['fill' => '#FFFFFF', 'borderRadius' => 32, 'opacity' => 0.95]),
            LayoutSchemaBuilder::logoPlaceholder('ws-logo', 'Logo', 840, 260, 240, 160, ['brandBinding' => 'brand.logo']),
            LayoutSchemaBuilder::text('ws-welcome', 'Welcome', 420, 460, 1080, 80, ['text' => 'Welcome', 'fontSize' => 72, 'fontWeight' => 700, 'color' => '#0F172A', 'textAlign' => 'center', 'brandBinding' => 'brand.heading_font']),
            LayoutSchemaBuilder::text('ws-sub', 'We\'re delighted to see you', 420, 560, 1080, 48, ['text' => 'We\'re delighted to see you', 'fontSize' => 28, 'fontWeight' => 400, 'color' => '#64748B', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('ws-line', 'Divider', 720, 640, 480, 2, ['fill' => '#E2E8F0', 'opacity' => 1]),
            LayoutSchemaBuilder::text('ws-org', 'Your Organisation', 420, 680, 1080, 48, ['text' => 'Your Organisation', 'fontSize' => 32, 'fontWeight' => 600, 'color' => '#334155', 'textAlign' => 'center', 'brandBinding' => 'brand.business_name']),
            LayoutSchemaBuilder::text('ws-date', 'Monday, 14 September 2026', 420, 760, 1080, 36, ['text' => 'Monday, 14 September 2026', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::shape('ws-accent-tl', 'Corner accent', 0, 0, 200, 200, ['fill' => '#E2E8F0', 'opacity' => 0.5]),
            LayoutSchemaBuilder::shape('ws-accent-br', 'Corner accent', 1720, 880, 200, 200, ['fill' => '#E2E8F0', 'opacity' => 0.5]),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Minimal, $elements, '#F8F9FA');
    }

    /**
     * @return array<string, mixed>
     */
    private static function eventCountdownLayout(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ecl-bg', 'Background', 0, 0, 1920, 1080, ['fill' => '#0F172A', 'opacity' => 1]),
            LayoutSchemaBuilder::shape('ecl-accent', 'Accent line', 0, 0, 1920, 8, ['fill' => '#F59E0B', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ecl-logo', 'Logo', 80, 60, 160, 100),
            LayoutSchemaBuilder::text('ecl-event', 'Product Launch Event', 80, 200, 1000, 72, ['text' => 'Product Launch Event', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ecl-date', '25 October 2026 · 6:00 PM', 80, 290, 800, 40, ['text' => '25 October 2026 · 6:00 PM', 'fontSize' => 26, 'fontWeight' => 400, 'color' => '#94A3B8']),
            LayoutSchemaBuilder::text('ecl-venue', 'The Innovation Hub, London', 80, 340, 800, 36, ['text' => 'The Innovation Hub, London', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#64748B']),
            LayoutSchemaBuilder::panel('ecl-countdown', 'Countdown panel', 80, 420, 1000, 280, ['fill' => 'rgba(255,255,255,0.06)', 'borderRadius' => 24, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-countdown-label', 'Event Starts In', 120, 450, 400, 40, ['text' => 'Event Starts In', 'fontSize' => 24, 'fontWeight' => 600, 'color' => '#F59E0B']),
            LayoutSchemaBuilder::panel('ecl-days', 'Days panel', 120, 510, 200, 140, ['fill' => 'rgba(245,158,11,0.15)', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-days-num', '41', 160, 530, 120, 64, ['text' => '41', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ecl-days-label', 'Days', 160, 600, 120, 32, ['text' => 'Days', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ecl-hours', 'Hours panel', 360, 510, 200, 140, ['fill' => 'rgba(245,158,11,0.15)', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-hours-num', '08', 400, 530, 120, 64, ['text' => '08', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ecl-hours-label', 'Hours', 400, 600, 120, 32, ['text' => 'Hours', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ecl-mins', 'Minutes panel', 600, 510, 200, 140, ['fill' => 'rgba(245,158,11,0.15)', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-mins-num', '32', 640, 530, 120, 64, ['text' => '32', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ecl-mins-label', 'Minutes', 640, 600, 120, 32, ['text' => 'Minutes', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::panel('ecl-secs', 'Seconds panel', 840, 510, 200, 140, ['fill' => 'rgba(245,158,11,0.15)', 'borderRadius' => 12, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-secs-num', '15', 880, 530, 120, 64, ['text' => '15', 'fontSize' => 56, 'fontWeight' => 800, 'color' => '#FFFFFF', 'textAlign' => 'center']),
            LayoutSchemaBuilder::text('ecl-secs-label', 'Seconds', 880, 600, 120, 32, ['text' => 'Seconds', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8', 'textAlign' => 'center']),
            LayoutSchemaBuilder::imagePlaceholder('ecl-image', 'Replace image', 1120, 200, 720, 500, ['borderRadius' => 20]),
            LayoutSchemaBuilder::panel('ecl-sponsors', 'Sponsors panel', 1120, 740, 720, 240, ['fill' => 'rgba(255,255,255,0.04)', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ecl-sponsors-title', 'Presented By', 1160, 770, 300, 32, ['text' => 'Presented By', 'fontSize' => 20, 'fontWeight' => 600, 'color' => '#94A3B8']),
            LayoutSchemaBuilder::logoPlaceholder('ecl-sponsor-logo', 'Logo', 1160, 820, 200, 120),
            LayoutSchemaBuilder::text('ecl-rsvp', 'RSVP: events@example.com', 80, 760, 600, 36, ['text' => 'RSVP: events@example.com', 'fontSize' => 22, 'fontWeight' => 500, 'color' => '#F59E0B']),
            LayoutSchemaBuilder::text('ecl-footer', 'Doors open 30 minutes before start', 80, 1020, 600, 32, ['text' => 'Doors open 30 minutes before start', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#64748B']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Dark, $elements, '#0F172A');
    }

    /**
     * @return array<string, mixed>
     */
    private static function informationBoard(): array
    {
        $elements = [
            LayoutSchemaBuilder::shape('ib-header', 'Header band', 0, 0, 1920, 100, ['fill' => '#334155', 'opacity' => 1]),
            LayoutSchemaBuilder::logoPlaceholder('ib-logo', 'Logo', 80, 10, 100, 80),
            LayoutSchemaBuilder::text('ib-title', 'Information Board', 200, 25, 600, 52, ['text' => 'Information Board', 'fontSize' => 36, 'fontWeight' => 700, 'color' => '#FFFFFF']),
            LayoutSchemaBuilder::text('ib-date', 'Updated: 14 September 2026', 1400, 35, 440, 32, ['text' => 'Updated: 14 September 2026', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#CBD5E1', 'textAlign' => 'right']),
            LayoutSchemaBuilder::panel('ib-section-1', 'Notice 1 panel', 80, 140, 560, 420, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ib-s1-title', 'General Notice', 110, 170, 400, 40, ['text' => 'General Notice', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ib-s1-body', 'Building maintenance scheduled for Saturday 9 AM – 12 PM. Please use the side entrance.', 110, 230, 500, 140, ['text' => 'Building maintenance scheduled for Saturday 9 AM – 12 PM. Please use the side entrance.', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::shape('ib-s1-accent', 'Accent', 110, 390, 80, 4, ['fill' => '#3B82F6', 'opacity' => 1]),
            LayoutSchemaBuilder::panel('ib-section-2', 'Notice 2 panel', 680, 140, 560, 420, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ib-s2-title', 'Opening Hours', 710, 170, 400, 40, ['text' => 'Opening Hours', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ib-s2-body', "Mon–Fri: 8:00 AM – 6:00 PM\nSat: 9:00 AM – 1:00 PM\nSun: Closed", 710, 230, 500, 120, ['text' => "Mon–Fri: 8:00 AM – 6:00 PM\nSat: 9:00 AM – 1:00 PM\nSun: Closed", 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::shape('ib-s2-accent', 'Accent', 710, 390, 80, 4, ['fill' => '#10B981', 'opacity' => 1]),
            LayoutSchemaBuilder::panel('ib-section-3', 'Notice 3 panel', 1280, 140, 560, 420, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ib-s3-title', 'Contact', 1310, 170, 400, 40, ['text' => 'Contact', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ib-s3-body', "Reception: Ext. 100\nEmail: info@example.com\nEmergency: 999", 1310, 230, 500, 120, ['text' => "Reception: Ext. 100\nEmail: info@example.com\nEmergency: 999", 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569', 'lineHeight' => 1.5]),
            LayoutSchemaBuilder::shape('ib-s3-accent', 'Accent', 1310, 390, 80, 4, ['fill' => '#F59E0B', 'opacity' => 1]),
            LayoutSchemaBuilder::panel('ib-section-4', 'Featured panel', 80, 600, 880, 400, ['fill' => '#EFF6FF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ib-s4-title', 'Featured Update', 110, 630, 400, 40, ['text' => 'Featured Update', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#2563EB']),
            LayoutSchemaBuilder::text('ib-s4-body', 'New parking arrangements take effect from 1 October. Permits available from reception. Blue badge holders unaffected.', 110, 690, 820, 120, ['text' => 'New parking arrangements take effect from 1 October. Permits available from reception. Blue badge holders unaffected.', 'fontSize' => 22, 'fontWeight' => 400, 'color' => '#334155', 'lineHeight' => 1.4]),
            LayoutSchemaBuilder::imagePlaceholder('ib-s4-image', 'Replace image', 110, 840, 400, 140, ['borderRadius' => 8]),
            LayoutSchemaBuilder::panel('ib-section-5', 'Events panel', 1000, 600, 840, 400, ['fill' => '#FFFFFF', 'borderRadius' => 16, 'opacity' => 1]),
            LayoutSchemaBuilder::text('ib-s5-title', 'Upcoming Events', 1030, 630, 400, 40, ['text' => 'Upcoming Events', 'fontSize' => 26, 'fontWeight' => 700, 'color' => '#0F172A']),
            LayoutSchemaBuilder::text('ib-s5-1', '• Team meeting — Mon 10 AM', 1030, 690, 760, 32, ['text' => '• Team meeting — Mon 10 AM', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ib-s5-2', '• Training session — Wed 2 PM', 1030, 730, 760, 32, ['text' => '• Training session — Wed 2 PM', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ib-s5-3', '• Social event — Fri 5 PM', 1030, 770, 760, 32, ['text' => '• Social event — Fri 5 PM', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ib-s5-4', '• Health & safety briefing — Next week', 1030, 810, 760, 32, ['text' => '• Health & safety briefing — Next week', 'fontSize' => 20, 'fontWeight' => 400, 'color' => '#475569']),
            LayoutSchemaBuilder::text('ib-footer', 'Please check this board regularly for updates', 80, 1040, 800, 32, ['text' => 'Please check this board regularly for updates', 'fontSize' => 18, 'fontWeight' => 400, 'color' => '#94A3B8']),
        ];

        return LayoutSchemaBuilder::make(TemplateOrientation::Landscape, TemplateTheme::Clean, $elements, '#F8FAFC');
    }
}
