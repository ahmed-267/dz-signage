<?php

namespace App\Support\Ai;

use App\Support\Ai\Dto\DesignPlanResult;

/**
 * Deterministic high-quality layout archetypes for landscape 1920×1080 and portrait 1080×1920.
 */
final class DesignArchetypes
{
    public const HERO_PRODUCT = 'hero_product';

    public const SPLIT_LAYOUT = 'split_layout';

    public const EDITORIAL = 'editorial';

    public const INFORMATION_BOARD = 'information_board';

    public const EVENT = 'event';

    public const MENU = 'menu';

    /** Full-bleed photo with restrained overlay copy (hospitality / retail / real estate). */
    public const FULL_BLEED = 'full_bleed';

    /** Welcome / lobby board (hotel, office, healthcare). */
    public const WELCOME = 'welcome';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return config('ai.design.archetypes', [
            self::HERO_PRODUCT,
            self::SPLIT_LAYOUT,
            self::EDITORIAL,
            self::INFORMATION_BOARD,
            self::EVENT,
            self::MENU,
            self::FULL_BLEED,
            self::WELCOME,
        ]);
    }

    public static function selectFor(CreativeBrief $brief): string
    {
        $map = config('ai.design.purpose_to_archetype', []);
        $primary = $map[$brief->purpose] ?? self::HERO_PRODUCT;

        return in_array($primary, self::all(), true) ? $primary : self::HERO_PRODUCT;
    }

    /**
     * Ordered alternate archetypes for a brief (primary first).
     *
     * @return list<string>
     */
    public static function variantsFor(CreativeBrief $brief, int $count = 3): array
    {
        $primary = self::selectFor($brief);
        $ordered = [$primary];

        $fallbacks = match ($brief->purpose) {
            'promotion' => [self::SPLIT_LAYOUT, self::FULL_BLEED, self::EDITORIAL],
            'menu' => [self::MENU, self::SPLIT_LAYOUT, self::INFORMATION_BOARD],
            'event' => [self::EVENT, self::HERO_PRODUCT, self::FULL_BLEED],
            'welcome' => [self::WELCOME, self::SPLIT_LAYOUT, self::FULL_BLEED],
            'announcement' => [self::INFORMATION_BOARD, self::EDITORIAL, self::SPLIT_LAYOUT],
            'information' => [self::EDITORIAL, self::INFORMATION_BOARD, self::WELCOME],
            default => [self::HERO_PRODUCT, self::SPLIT_LAYOUT, self::FULL_BLEED],
        };

        foreach ($fallbacks as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        foreach (self::all() as $key) {
            if (! in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }

        return array_slice($ordered, 0, max(1, min($count, 3)));
    }

    public static function label(string $archetype): string
    {
        return match ($archetype) {
            self::HERO_PRODUCT => 'Hero Product',
            self::SPLIT_LAYOUT => 'Split Layout',
            self::EDITORIAL => 'Editorial',
            self::INFORMATION_BOARD => 'Information Board',
            self::EVENT => 'Event Poster',
            self::MENU => 'Menu Board',
            self::FULL_BLEED => 'Full-Bleed Photo',
            self::WELCOME => 'Welcome Lobby',
            default => 'Design',
        };
    }

    public static function plan(CreativeBrief $brief, ?string $archetype = null): DesignPlanResult
    {
        $archetype ??= self::selectFor($brief);
        $portrait = $brief->orientation === 'portrait';

        $elements = match ($archetype) {
            self::SPLIT_LAYOUT => $portrait ? self::splitPortrait($brief) : self::splitLandscape($brief),
            self::EDITORIAL => $portrait ? self::editorialPortrait($brief) : self::editorialLandscape($brief),
            self::INFORMATION_BOARD => $portrait ? self::infoPortrait($brief) : self::infoLandscape($brief),
            self::EVENT => $portrait ? self::eventPortrait($brief) : self::eventLandscape($brief),
            self::MENU => $portrait ? self::menuPortrait($brief) : self::menuLandscape($brief),
            self::FULL_BLEED => $portrait ? self::fullBleedPortrait($brief) : self::fullBleedLandscape($brief),
            self::WELCOME => $portrait ? self::welcomePortrait($brief) : self::welcomeLandscape($brief),
            default => $portrait ? self::heroPortrait($brief) : self::heroLandscape($brief),
        };

        return new DesignPlanResult(
            elements: $elements,
            background: ['type' => 'color', 'value' => $brief->backgroundColor()],
            suggestedName: (string) ($brief->meta['suggested_name'] ?? 'AI Design'),
            model: 'archetype-'.$archetype,
            archetype: $archetype,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function heroLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::shape('Accent bar', 0, 0, 20, 1080, $accent, 2),
            self::shape('Hero frame', 1120, 120, 720, 840, $primary, 3),
            self::image('Hero', 1140, 140, 680, 800, 4),
            self::logo(64, 64, 140, 72, 5),
            self::text('Headline', $brief->headline, 64, 260, 980, 180, $text, 88, '700', 'left', 6),
            self::text('Subheadline', $brief->subheadline, 64, 460, 900, 72, $text, 34, '500', 'left', 7),
            self::shape('CTA panel', 64, 600, 340, 80, $accent, 8),
            self::text('CTA', $brief->cta, 64, 615, 340, 52, $bg, 36, '700', 'center', 9),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function heroPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::image('Hero', 64, 64, 952, 740, 2),
            self::logo(64, 848, 128, 64, 3),
            self::text('Headline', $brief->headline, 64, 940, 952, 200, $text, 76, '700', 'left', 4),
            self::text('Subheadline', $brief->subheadline, 64, 1160, 952, 90, $text, 30, '500', 'left', 5),
            self::shape('CTA panel', 64, 1320, 420, 88, $accent, 6),
            self::text('CTA', $brief->cta, 64, 1335, 420, 60, $bg, 36, '700', 'center', 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function splitLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Left panel', 0, 0, 960, 1080, $primary, 1),
            self::shape('Right panel', 960, 0, 960, 1080, $bg, 1),
            self::logo(80, 80, 140, 70, 2),
            self::text('Headline', $brief->headline, 80, 320, 800, 220, $text, 84, '700', 'left', 3),
            self::text('Body', $brief->body, 80, 560, 760, 120, $text, 32, '400', 'left', 4),
            self::text('CTA', $brief->cta, 80, 760, 320, 64, $accent, 36, '700', 'left', 5),
            self::image('Feature', 1040, 140, 800, 800, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function splitPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Top panel', 0, 0, 1080, 900, $primary, 1),
            self::shape('Bottom panel', 0, 900, 1080, 1020, $bg, 1),
            self::image('Feature', 80, 80, 920, 740, 2),
            self::logo(80, 960, 140, 70, 3),
            self::text('Headline', $brief->headline, 80, 1080, 920, 200, $text, 72, '700', 'left', 4),
            self::text('Body', $brief->body, 80, 1300, 920, 140, $text, 30, '400', 'left', 5),
            self::text('CTA', $brief->cta, 80, 1520, 360, 64, $accent, 36, '700', 'left', 6),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function editorialLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::shape('Accent rule', 120, 160, 200, 8, $accent, 2),
            self::logo(1600, 80, 160, 80, 2),
            self::text('Eyebrow', mb_strtoupper($brief->purpose), 120, 200, 600, 48, $accent, 28, '600', 'left', 3),
            self::text('Headline', $brief->headline, 120, 280, 1100, 240, $text, 88, '700', 'left', 4),
            self::text('Body', $brief->body, 120, 560, 900, 140, $text, 32, '400', 'left', 5),
            self::image('Editorial', 1200, 280, 600, 680, 3),
            self::text('CTA', $brief->cta, 120, 780, 280, 56, $accent, 32, '700', 'left', 6),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function editorialPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::shape('Accent rule', 80, 120, 160, 8, $accent, 2),
            self::text('Eyebrow', mb_strtoupper($brief->purpose), 80, 160, 600, 48, $accent, 26, '600', 'left', 3),
            self::text('Headline', $brief->headline, 80, 240, 920, 240, $text, 76, '700', 'left', 4),
            self::image('Editorial', 80, 520, 920, 700, 5),
            self::text('Body', $brief->body, 80, 1280, 920, 160, $text, 30, '400', 'left', 6),
            self::text('CTA', $brief->cta, 80, 1520, 320, 64, $accent, 34, '700', 'left', 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function infoLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::logo(80, 64, 140, 70, 2),
            self::text('Headline', $brief->headline, 80, 180, 1400, 120, $text, 72, '700', 'left', 3),
            self::text('Subheadline', $brief->subheadline, 80, 320, 1200, 64, $text, 32, '500', 'left', 4),
            self::shape('Card A', 80, 440, 560, 480, $primary, 5),
            self::shape('Card B', 680, 440, 560, 480, $primary, 5),
            self::shape('Card C', 1280, 440, 560, 480, $primary, 5),
            self::text('Card A title', 'Info', 120, 500, 480, 64, $accent, 36, '700', 'left', 6),
            self::text('Card A body', $brief->body, 120, 580, 480, 200, $text, 28, '400', 'left', 7),
            [
                'type' => 'widget',
                'name' => 'Clock',
                'x' => 1600,
                'y' => 80,
                'width' => 240,
                'height' => 100,
                'zIndex' => 8,
                'widgetType' => 'clock',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function infoPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::logo(80, 64, 140, 70, 2),
            self::text('Headline', $brief->headline, 80, 180, 920, 140, $text, 64, '700', 'left', 3),
            self::text('Subheadline', $brief->subheadline, 80, 340, 920, 80, $text, 30, '500', 'left', 4),
            self::shape('Card A', 80, 480, 920, 360, $primary, 5),
            self::shape('Card B', 80, 880, 920, 360, $primary, 5),
            self::shape('Card C', 80, 1280, 920, 360, $primary, 5),
            self::text('Card A title', 'Details', 120, 520, 840, 64, $accent, 34, '700', 'left', 6),
            self::text('Card A body', $brief->body, 120, 600, 840, 160, $text, 28, '400', 'left', 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function eventLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::shape('Date panel', 64, 120, 400, 400, $accent, 2),
            self::text('Date', 'SAT', 84, 170, 360, 64, $bg, 40, '700', 'center', 3),
            self::text('Day', '28', 84, 250, 360, 180, $bg, 128, '800', 'center', 4),
            self::logo(1716, 64, 140, 72, 5),
            self::text('Headline', $brief->headline, 520, 180, 1100, 160, $text, 80, '700', 'left', 6),
            self::text('Subheadline', $brief->subheadline, 520, 360, 900, 72, $text, 34, '500', 'left', 7),
            self::shape('CTA panel', 520, 500, 380, 84, $accent, 8),
            self::text('CTA', $brief->cta, 520, 515, 380, 56, $bg, 36, '700', 'center', 9),
            self::shape('Footer bar', 0, 980, 1920, 100, $primary, 10),
            [
                'type' => 'widget',
                'name' => 'Countdown',
                'x' => 1480,
                'y' => 820,
                'width' => 376,
                'height' => 120,
                'zIndex' => 11,
                'widgetType' => 'countdown',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function eventPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::logo(876, 64, 140, 72, 2),
            self::shape('Date panel', 64, 160, 952, 320, $accent, 3),
            self::text('Date', 'THIS WEEKEND', 84, 200, 912, 56, $bg, 32, '700', 'center', 4),
            self::text('Day', '28', 84, 270, 912, 160, $bg, 112, '800', 'center', 5),
            self::text('Headline', $brief->headline, 64, 540, 952, 180, $text, 72, '700', 'left', 6),
            self::text('Subheadline', $brief->subheadline, 64, 740, 952, 90, $text, 32, '500', 'left', 7),
            self::shape('CTA panel', 64, 880, 420, 88, $accent, 8),
            self::text('CTA', $brief->cta, 64, 895, 420, 60, $bg, 36, '700', 'center', 9),
            [
                'type' => 'widget',
                'name' => 'Countdown',
                'x' => 64,
                'y' => 1080,
                'width' => 952,
                'height' => 140,
                'zIndex' => 10,
                'widgetType' => 'countdown',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function menuLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::logo(80, 64, 140, 70, 2),
            self::text('Headline', $brief->headline, 80, 160, 1000, 100, $text, 64, '700', 'left', 3),
            self::text('Subheadline', $brief->subheadline, 80, 280, 900, 56, $accent, 28, '600', 'left', 4),
            self::shape('Row 1', 80, 400, 1100, 140, $primary, 5),
            self::text('Item 1', 'Signature item', 120, 430, 700, 48, $text, 36, '700', 'left', 6),
            self::text('Price 1', '£12', 900, 430, 200, 48, $accent, 36, '700', 'right', 6),
            self::shape('Row 2', 80, 560, 1100, 140, $primary, 5),
            self::text('Item 2', 'Chef special', 120, 590, 700, 48, $text, 36, '700', 'left', 6),
            self::text('Price 2', '£9', 900, 590, 200, 48, $accent, 36, '700', 'right', 6),
            self::image('Dish', 1280, 200, 560, 700, 4),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function menuPortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::logo(80, 64, 140, 70, 2),
            self::text('Headline', $brief->headline, 80, 180, 920, 120, $text, 60, '700', 'left', 3),
            self::text('Subheadline', $brief->subheadline, 80, 320, 920, 56, $accent, 28, '600', 'left', 4),
            self::shape('Row 1', 80, 440, 920, 160, $primary, 5),
            self::text('Item 1', 'Signature item', 120, 480, 600, 48, $text, 34, '700', 'left', 6),
            self::text('Price 1', '£12', 760, 480, 180, 48, $accent, 34, '700', 'right', 6),
            self::shape('Row 2', 80, 640, 920, 160, $primary, 5),
            self::text('Item 2', 'Chef special', 120, 680, 600, 48, $text, 34, '700', 'left', 6),
            self::text('Price 2', '£9', 760, 680, 180, 48, $accent, 34, '700', 'right', 6),
            self::image('Dish', 80, 900, 920, 800, 4),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fullBleedLandscape(CreativeBrief $brief): array
    {
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $bg = $brief->backgroundColor();

        return [
            self::image('Hero photo', 0, 0, 1920, 1080, 1),
            self::shape('Scrim', 0, 520, 1920, 560, '#000000', 2, 0.55),
            self::logo(80, 64, 160, 80, 3),
            self::text('Headline', $brief->headline, 80, 640, 1400, 160, $text, 88, '700', 'left', 4),
            self::text('Subheadline', $brief->subheadline, 80, 820, 1100, 72, $text, 34, '500', 'left', 5),
            self::shape('CTA panel', 80, 920, 360, 80, $accent, 6),
            self::text('CTA', $brief->cta, 80, 935, 360, 52, $bg, 34, '700', 'center', 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function fullBleedPortrait(CreativeBrief $brief): array
    {
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $bg = $brief->backgroundColor();

        return [
            self::image('Hero photo', 0, 0, 1080, 1920, 1),
            self::shape('Scrim', 0, 1100, 1080, 820, '#000000', 2, 0.55),
            self::logo(64, 64, 140, 70, 3),
            self::text('Headline', $brief->headline, 64, 1220, 952, 200, $text, 72, '700', 'left', 4),
            self::text('Subheadline', $brief->subheadline, 64, 1440, 952, 90, $text, 30, '500', 'left', 5),
            self::shape('CTA panel', 64, 1600, 400, 88, $accent, 6),
            self::text('CTA', $brief->cta, 64, 1615, 400, 60, $bg, 34, '700', 'center', 7),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function welcomeLandscape(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1920, 1080, $bg, 1),
            self::shape('Left wash', 0, 0, 720, 1080, $primary, 2),
            self::logo(80, 80, 180, 90, 3),
            self::text('Eyebrow', 'WELCOME', 80, 280, 560, 48, $accent, 28, '600', 'left', 4),
            self::text('Headline', $brief->headline, 80, 360, 560, 280, $text, 72, '700', 'left', 5),
            self::text('Body', $brief->body !== '' ? $brief->body : $brief->subheadline, 80, 680, 520, 160, $text, 30, '400', 'left', 6),
            self::image('Lobby', 800, 120, 1040, 840, 3),
            self::shape('CTA panel', 80, 900, 320, 72, $accent, 7),
            self::text('CTA', $brief->cta, 80, 912, 320, 48, $bg, 30, '700', 'center', 8),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function welcomePortrait(CreativeBrief $brief): array
    {
        $bg = $brief->backgroundColor();
        $text = $brief->textColor();
        $accent = $brief->accentColor();
        $primary = $brief->primaryColor();

        return [
            self::shape('Backdrop', 0, 0, 1080, 1920, $bg, 1),
            self::shape('Header bar', 0, 0, 1080, 280, $primary, 2),
            self::logo(64, 80, 160, 80, 3),
            self::text('Eyebrow', 'WELCOME', 64, 320, 952, 48, $accent, 26, '600', 'left', 4),
            self::text('Headline', $brief->headline, 64, 400, 952, 240, $text, 68, '700', 'left', 5),
            self::image('Lobby', 64, 700, 952, 720, 3),
            self::text('Body', $brief->body !== '' ? $brief->body : $brief->subheadline, 64, 1480, 952, 160, $text, 28, '400', 'left', 6),
            self::shape('CTA panel', 64, 1700, 400, 88, $accent, 7),
            self::text('CTA', $brief->cta, 64, 1715, 400, 60, $bg, 34, '700', 'center', 8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shape(string $name, int $x, int $y, int $w, int $h, string $fill, int $z, ?float $opacity = null): array
    {
        $el = [
            'type' => 'shape',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'fill' => $fill,
            'zIndex' => $z,
        ];
        if ($opacity !== null) {
            $el['opacity'] = max(0.0, min(1.0, $opacity));
        }

        return $el;
    }

    /**
     * @return array<string, mixed>
     */
    private static function text(
        string $name,
        string $text,
        int $x,
        int $y,
        int $w,
        int $h,
        string $color,
        int $fontSize,
        string $weight,
        string $align,
        int $z,
    ): array {
        return [
            'type' => 'text',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'text' => $text,
            'color' => $color,
            'fontSize' => $fontSize,
            'fontWeight' => $weight,
            'align' => $align,
            'zIndex' => $z,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function image(string $name, int $x, int $y, int $w, int $h, int $z): array
    {
        return [
            'type' => 'image',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'zIndex' => $z,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function logo(int $x, int $y, int $w, int $h, int $z): array
    {
        return [
            'type' => 'logo',
            'name' => 'Logo',
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'zIndex' => $z,
        ];
    }
}
