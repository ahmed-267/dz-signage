<?php

namespace App\Support\Marketing;

use App\Support\Demo\DemoImageFactory;
use App\Support\Demo\DemoPhotoCatalog;
use Illuminate\Support\Facades\File;

/**
 * Landing-page imagery catalog.
 *
 * Preferred source: curated real Unsplash JPEGs under resources/demo/photos/
 * (synced to public/images/marketing/). GD compose remains only as an emergency
 * fallback when a curated file is missing.
 */
final class MarketingImageFactory
{
    /**
     * @return array<string, array{
     *     file: string,
     *     title: string,
     *     subtitle: string,
     *     bg: array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>},
     *     accent: array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>},
     *     subject: string,
     *     width: int<1, max>,
     *     height: int<1, max>,
     *     badge?: string,
     *     source_file?: string,
     * }>
     */
    public static function catalog(): array
    {
        $marketing = DemoPhotoCatalog::marketing();

        $legacyMeta = [
            'cafe' => [
                'title' => 'ICED LATTE',
                'subtitle' => '40% OFF THIS WEEKEND',
                'bg' => [28, 18, 12],
                'accent' => [194, 120, 62],
                'subject' => 'coffee',
                'badge' => 'CAFE SPECIAL',
            ],
            'retail' => [
                'title' => 'MID-SEASON',
                'subtitle' => 'SELECTED LINES · IN STORE NOW',
                'bg' => [18, 12, 24],
                'accent' => [220, 70, 110],
                'subject' => 'fashion',
                'badge' => 'THIS WEEK',
            ],
            'corporate' => [
                'title' => 'WELCOME',
                'subtitle' => 'NORTHGATE CENTRE · LEVEL 1',
                'bg' => [8, 22, 34],
                'accent' => [56, 189, 210],
                'subject' => 'building',
                'badge' => 'RECEPTION',
            ],
            'hotel' => [
                'title' => 'WELCOME, GUESTS',
                'subtitle' => 'CHECK-IN FROM 15:00',
                'bg' => [18, 28, 28],
                'accent' => [45, 160, 150],
                'subject' => 'hotel',
                'badge' => 'LOBBY',
            ],
            'masjid' => [
                'title' => 'PRAYER TIMES',
                'subtitle' => 'TODAY · IQAMAH SCHEDULE',
                'bg' => [8, 28, 22],
                'accent' => [52, 180, 120],
                'subject' => 'dome',
                'badge' => 'TODAY',
            ],
            'gym' => [
                'title' => 'HIIT 12:00',
                'subtitle' => 'STUDIO 1 · 45 MIN · ALL LEVELS',
                'bg' => [12, 18, 8],
                'accent' => [160, 220, 60],
                'subject' => 'gym',
                'badge' => 'CLASS SCHEDULE',
            ],
            'restaurant' => [
                'title' => 'LUNCH MENU',
                'subtitle' => 'SERVED 11:30 – 14:30',
                'bg' => [32, 20, 10],
                'accent' => [230, 170, 70],
                'subject' => 'plate',
                'badge' => "CHEF'S PICK",
            ],
            'events' => [
                'title' => 'DOORS OPEN',
                'subtitle' => 'MAIN STAGE · HALL 2',
                'bg' => [28, 8, 32],
                'accent' => [210, 80, 200],
                'subject' => 'stage',
                'badge' => 'LIVE EVENT',
            ],
            'education' => [
                'title' => 'CAMPUS NEWS',
                'subtitle' => 'LECTURE HALLS · OPEN DAYS',
                'bg' => [12, 24, 40],
                'accent' => [96, 165, 250],
                'subject' => 'book',
                'badge' => 'EDUCATION',
            ],
            'healthcare' => [
                'title' => 'PLEASE WAIT',
                'subtitle' => 'YOU WILL BE CALLED SHORTLY',
                'bg' => [236, 253, 245],
                'accent' => [13, 148, 136],
                'subject' => 'clinic',
                'badge' => 'CLINIC',
            ],
            'property' => [
                'title' => 'OPEN VIEWING',
                'subtitle' => 'SUNDAY · 11:00 – 14:00',
                'bg' => [23, 37, 84],
                'accent' => [251, 191, 36],
                'subject' => 'home',
                'badge' => 'FOR SALE',
            ],
            'cafe_interior' => [
                'title' => 'NORTH & BEAN',
                'subtitle' => 'COFFEE WORTH GATHERING FOR',
                'bg' => [28, 18, 12],
                'accent' => [194, 120, 62],
                'subject' => 'coffee',
                'badge' => 'CAFÉ',
            ],
            'landscape' => [
                'title' => 'ESCAPE',
                'subtitle' => 'WEEKEND GETAWAYS',
                'bg' => [8, 22, 34],
                'accent' => [56, 189, 210],
                'subject' => 'abstract',
                'badge' => 'TRAVEL',
            ],
        ];

        $catalog = [];
        foreach ($legacyMeta as $key => $meta) {
            $photo = $marketing[$key] ?? null;
            $width = max(1, (int) ($photo['width'] ?? 1600));
            $height = max(1, (int) ($photo['height'] ?? 1067));
            $catalog[$key] = [
                'file' => $photo['file'] ?? str_replace('_', '-', $key).'.jpg',
                'title' => $meta['title'],
                'subtitle' => $meta['subtitle'],
                'bg' => $meta['bg'],
                'accent' => $meta['accent'],
                'subject' => $meta['subject'],
                'width' => $width,
                'height' => $height,
                'badge' => $meta['badge'],
                'source_file' => $photo['file'] ?? null,
            ];
        }

        return $catalog;
    }

    /**
     * Resolve curated JPEG bytes for a catalog entry, falling back to GD compose.
     *
     * @param  array{
     *     file: string,
     *     title: string,
     *     subtitle: string,
     *     bg: array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>},
     *     accent: array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>},
     *     subject: string,
     *     width: int<1, max>,
     *     height: int<1, max>,
     *     badge?: string|null,
     *     source_file?: string|null,
     * }  $spec
     */
    public static function resolveBinary(array $spec): string
    {
        $sourceFile = $spec['source_file'] ?? $spec['file'];
        $path = DemoPhotoCatalog::path((string) $sourceFile);

        if (is_file($path) && filesize($path) > 1000) {
            $binary = File::get($path);
            if ($binary !== '') {
                return $binary;
            }
        }

        return self::compose(
            $spec['title'],
            $spec['subtitle'],
            $spec['bg'],
            $spec['accent'],
            $spec['subject'],
            $spec['width'],
            $spec['height'],
            $spec['badge'] ?? null,
        );
    }

    /**
     * GD silhouette fallback (tests / missing curated file only).
     *
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $bg
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $accent
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    public static function compose(
        string $title,
        string $subtitle,
        array $bg,
        array $accent,
        string $subject,
        int $width = 1280,
        int $height = 720,
        ?string $badge = null,
    ): string {
        $binary = DemoImageFactory::compose(
            $title,
            $subtitle,
            $bg,
            $accent,
            $width,
            $height,
            $subject,
        );

        $image = imagecreatefromstring($binary);
        if ($image === false) {
            throw new \RuntimeException('Could not reopen marketing canvas.');
        }

        self::paintVignette($image, $width, $height);
        self::paintBadge($image, $width, $height, $badge ?? 'DZ SIGNAGE', $accent);
        self::paintCtaBar($image, $width, $height, $accent);

        ob_start();
        imagepng($image, null, 6);
        $out = (string) ob_get_clean();

        if ($out === '') {
            throw new \RuntimeException('Failed to encode marketing PNG.');
        }

        return $out;
    }

    /**
     * @param  \GdImage  $image
     */
    private static function paintVignette($image, int $width, int $height): void
    {
        for ($i = 0; $i < 6; $i++) {
            $alpha = 100 - ($i * 12);
            $c = imagecolorallocatealpha($image, 0, 0, 0, max(20, $alpha));
            if ($c === false) {
                continue;
            }
            $pad = (int) ($i * 28);
            imagerectangle($image, $pad, $pad, $width - 1 - $pad, $height - 1 - $pad, $c);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $accent
     */
    private static function paintBadge($image, int $width, int $height, string $badge, array $accent): void
    {
        $x1 = (int) ($width * 0.06);
        $y1 = (int) ($height * 0.08);
        $x2 = (int) ($width * 0.34);
        $y2 = (int) ($height * 0.16);

        $fill = imagecolorallocatealpha(
            $image,
            self::channel($accent[0]),
            self::channel($accent[1]),
            self::channel($accent[2]),
            40,
        );
        $text = imagecolorallocate($image, 255, 255, 255);
        if ($fill === false || $text === false) {
            return;
        }

        imagefilledrectangle($image, $x1, $y1, $x2, $y2, $fill);
        imagestring($image, 3, $x1 + 14, $y1 + 10, self::ascii(strtoupper($badge), 28), $text);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $accent
     */
    private static function paintCtaBar($image, int $width, int $height, array $accent): void
    {
        $y = (int) ($height * 0.88);
        $bar = imagecolorallocate(
            $image,
            self::channel($accent[0]),
            self::channel($accent[1]),
            self::channel($accent[2]),
        );
        $white = imagecolorallocate($image, 255, 255, 255);
        if ($bar === false || $white === false) {
            return;
        }

        imagefilledrectangle($image, (int) ($width * 0.06), $y, (int) ($width * 0.28), $y + 6, $bar);
        imagestring($image, 2, (int) ($width * 0.72), $y - 4, 'RMSignage', $white);
    }

    /**
     * @return int<0, 255>
     */
    private static function channel(int $value): int
    {
        return max(0, min(255, $value));
    }

    private static function ascii(string $value, int $max): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return substr($clean, 0, $max);
    }
}
