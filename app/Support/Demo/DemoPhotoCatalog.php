<?php

namespace App\Support\Demo;

/**
 * Curated real Unsplash photographs for local demo seeding and marketing.
 * Files live under resources/demo/photos/ (committed); never hotlinked at runtime.
 *
 * @see docs/DEMO_MEDIA_SOURCES.md
 */
final class DemoPhotoCatalog
{
    /**
     * Absolute directory containing committed demo JPEGs.
     */
    public static function directory(): string
    {
        return resource_path('demo/photos');
    }

    /**
     * @return list<array{
     *     file: string,
     *     name: string,
     *     slug: string,
     *     source_url: string,
     *     subject: string,
     *     marketing_key?: string,
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'file' => 'cafe-iced-coffee.jpg',
                'name' => 'Iced Latte.jpg',
                'slug' => 'iced-latte',
                'source_url' => 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735',
                'subject' => 'coffee',
                'marketing_key' => 'cafe',
            ],
            [
                'file' => 'espresso-machine.jpg',
                'name' => 'Latte Art Cups.jpg',
                'slug' => 'latte-art-cups',
                'source_url' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085',
                'subject' => 'coffee',
            ],
            [
                'file' => 'cafe-interior.jpg',
                'name' => 'Café Interior.jpg',
                'slug' => 'cafe-interior',
                'source_url' => 'https://images.unsplash.com/photo-1554118811-1e0d58224f24',
                'subject' => 'cafe',
                'marketing_key' => 'cafe_interior',
            ],
            [
                'file' => 'pastry-display.jpg',
                'name' => 'Pastry Display.jpg',
                'slug' => 'pastry-display',
                'source_url' => 'https://images.unsplash.com/photo-1509440159596-0249088772ff',
                'subject' => 'food',
            ],
            [
                'file' => 'restaurant-special.jpg',
                'name' => 'Plated Lunch Special.jpg',
                'slug' => 'plated-lunch-special',
                'source_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836',
                'subject' => 'food',
                'marketing_key' => 'restaurant',
            ],
            [
                'file' => 'retail-sale.jpg',
                'name' => 'Retail Fashion Floor.jpg',
                'slug' => 'retail-fashion-floor',
                'source_url' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8',
                'subject' => 'retail',
                'marketing_key' => 'retail',
            ],
            [
                'file' => 'fashion-rack.jpg',
                'name' => 'Fashion Clothing Rack.jpg',
                'slug' => 'fashion-clothing-rack',
                'source_url' => 'https://images.unsplash.com/photo-1558769132-cb1aea458c5e',
                'subject' => 'fashion',
            ],
            [
                'file' => 'masjid-prayer.jpg',
                'name' => 'Masjid Interior.jpg',
                'slug' => 'masjid-interior',
                'source_url' => 'https://images.unsplash.com/photo-1564769625905-50e93615e769',
                'subject' => 'masjid',
                'marketing_key' => 'masjid',
            ],
            [
                'file' => 'corporate-welcome.jpg',
                'name' => 'Corporate Office.jpg',
                'slug' => 'corporate-office',
                'source_url' => 'https://images.unsplash.com/photo-1497366216548-37526070297c',
                'subject' => 'corporate',
                'marketing_key' => 'corporate',
            ],
            [
                'file' => 'office-meeting.jpg',
                'name' => 'Office Meeting Space.jpg',
                'slug' => 'office-meeting-space',
                'source_url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72',
                'subject' => 'corporate',
            ],
            [
                'file' => 'hotel-lobby.jpg',
                'name' => 'Hotel Lobby.jpg',
                'slug' => 'hotel-lobby',
                'source_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945',
                'subject' => 'hotel',
                'marketing_key' => 'hotel',
            ],
            [
                'file' => 'gym-classes.jpg',
                'name' => 'Gym Floor.jpg',
                'slug' => 'gym-floor',
                'source_url' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48',
                'subject' => 'gym',
                'marketing_key' => 'gym',
            ],
            [
                'file' => 'education-campus.jpg',
                'name' => 'Education Campus.jpg',
                'slug' => 'education-campus',
                'source_url' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7',
                'subject' => 'education',
                'marketing_key' => 'education',
            ],
            [
                'file' => 'healthcare-clinic.jpg',
                'name' => 'Healthcare Clinic.jpg',
                'slug' => 'healthcare-clinic',
                'source_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d',
                'subject' => 'healthcare',
                'marketing_key' => 'healthcare',
            ],
            [
                'file' => 'property-home.jpg',
                'name' => 'Property Exterior.jpg',
                'slug' => 'property-exterior',
                'source_url' => 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6',
                'subject' => 'property',
                'marketing_key' => 'property',
            ],
            [
                'file' => 'events-stage.jpg',
                'name' => 'Conference Stage.jpg',
                'slug' => 'conference-stage',
                'source_url' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87',
                'subject' => 'events',
                'marketing_key' => 'events',
            ],
            [
                'file' => 'landscape-hills.jpg',
                'name' => 'Mountain Landscape.jpg',
                'slug' => 'mountain-landscape',
                'source_url' => 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4',
                'subject' => 'landscape',
                'marketing_key' => 'landscape',
            ],
            [
                'file' => 'cat-pet.jpg',
                'name' => 'Cat Portrait.jpg',
                'slug' => 'cat-portrait',
                'source_url' => 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba',
                'subject' => 'pet',
            ],
        ];
    }

    /**
     * Marketing landing subset keyed by MarketingImageKey.
     *
     * @return array<string, array{file: string, source_url: string, alt: string, width: int, height: int}>
     */
    public static function marketing(): array
    {
        $alts = [
            'cafe' => 'Café iced latte promotional photograph',
            'cafe_interior' => 'Café interior photograph',
            'retail' => 'Retail fashion store photograph',
            'corporate' => 'Corporate office interior photograph',
            'hotel' => 'Hotel lobby photograph',
            'masjid' => 'Masjid interior photograph',
            'gym' => 'Gym fitness floor photograph',
            'restaurant' => 'Restaurant plated food photograph',
            'events' => 'Conference stage photograph',
            'education' => 'Education campus photograph',
            'healthcare' => 'Healthcare clinic photograph',
            'property' => 'Residential property exterior photograph',
            'landscape' => 'Mountain landscape photograph',
        ];

        $out = [];
        foreach (self::all() as $photo) {
            $key = $photo['marketing_key'] ?? null;
            if ($key === null) {
                continue;
            }

            $path = self::path($photo['file']);
            $size = is_file($path) ? @getimagesize($path) : false;

            $out[$key] = [
                'file' => $photo['file'],
                'source_url' => $photo['source_url'],
                'alt' => $alts[$key] ?? $photo['name'],
                'width' => is_array($size) ? (int) $size[0] : 1600,
                'height' => is_array($size) ? (int) $size[1] : 1067,
            ];
        }

        return $out;
    }

    public static function path(string $file): string
    {
        return self::directory().DIRECTORY_SEPARATOR.$file;
    }

    public static function assertPresent(): void
    {
        foreach (self::all() as $photo) {
            $path = self::path($photo['file']);
            if (! is_file($path) || filesize($path) < 1000) {
                throw new \RuntimeException(
                    "Missing demo photo: {$photo['file']}. See docs/DEMO_MEDIA_SOURCES.md.",
                );
            }
        }
    }
}
