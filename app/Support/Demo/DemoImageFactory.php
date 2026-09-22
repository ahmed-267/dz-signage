<?php

namespace App\Support\Demo;

/**
 * Generates small, meaningful demo PNG compositions (not solid rectangles).
 * Subjects are recognisable silhouettes over photographic-looking gradients.
 */
final class DemoImageFactory
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $bg
     * @param  array{0: int, 1: int, 2: int}  $accent
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    public static function compose(
        string $title,
        string $subtitle,
        array $bg,
        array $accent,
        int $width = 1280,
        int $height = 720,
        string $subject = 'abstract',
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension is required for demo images.');
        }

        $bg = self::rgb($bg);
        $accent = self::rgb($accent);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new \RuntimeException('Could not allocate demo image.');
        }

        self::paintGradient($image, $width, $height, $bg, $accent);
        self::paintSubject($image, $width, $height, $subject, $accent, $bg);

        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 226, 232, 240);
        $shadow = imagecolorallocate($image, 15, 23, 42);
        if ($white === false || $muted === false || $shadow === false) {
            imagedestroy($image);
            throw new \RuntimeException('Could not allocate demo colours.');
        }

        // Soft title plate for readability over photo-like backgrounds.
        $plate = imagecolorallocatealpha($image, 15, 23, 42, 70);
        if ($plate !== false) {
            imagefilledrectangle(
                $image,
                (int) ($width * 0.06),
                (int) ($height * 0.58),
                (int) ($width * 0.62),
                (int) ($height * 0.86),
                $plate,
            );
        }

        imagestring($image, 5, (int) ($width * 0.09), (int) ($height * 0.64), self::ascii($title, 42), $white);
        imagestring($image, 3, (int) ($width * 0.09), (int) ($height * 0.74), self::ascii($subtitle, 56), $muted);
        imagestring($image, 2, (int) ($width * 0.09), (int) ($height * 0.90), 'RMSignage demo · '.$subject, $muted);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        if ($binary === '') {
            throw new \RuntimeException('Failed to encode demo PNG.');
        }

        return $binary;
    }

    /**
     * Compact professional wordmark logo (rounded rect + text — no ring/ellipse graphics).
     *
     * @param  array{0: int, 1: int, 2: int}  $bg
     * @param  array{0: int, 1: int, 2: int}  $fg
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    public static function logo(string $mark, array $bg, array $fg, int $width = 512, int $height = 160): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension is required for demo logos.');
        }

        $bg = self::rgb($bg);
        $fg = self::rgb($fg);

        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            throw new \RuntimeException('Could not allocate logo canvas.');
        }

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        if ($transparent !== false) {
            imagefill($image, 0, 0, $transparent);
        }

        $bgColour = imagecolorallocate($image, $bg[0], $bg[1], $bg[2]);
        $fgColour = imagecolorallocate($image, $fg[0], $fg[1], $fg[2]);
        if ($bgColour === false || $fgColour === false) {
            imagedestroy($image);
            throw new \RuntimeException('Could not allocate logo colours.');
        }

        $radius = (int) min(28, $height / 4);
        self::filledRoundedRect($image, 0, 0, $width - 1, $height - 1, $radius, $bgColour);

        $label = self::ascii($mark, 28);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($label);
        $textHeight = imagefontheight($font);
        $x = (int) max(8, ($width - $textWidth) / 2);
        $y = (int) max(8, ($height - $textHeight) / 2);
        imagestring($image, $font, $x, $y, $label, $fgColour);

        ob_start();
        imagepng($image);
        $binary = (string) ob_get_clean();
        imagedestroy($image);

        if ($binary === '') {
            throw new \RuntimeException('Failed to encode demo logo PNG.');
        }

        return $binary;
    }

    /**
     * @param  \GdImage  $image
     */
    private static function filledRoundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $colour): void
    {
        $radius = max(0, min($radius, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $colour);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $colour);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $colour);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $colour);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $colour);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $colour);
    }

    /**
     * @param  \GdImage  $image
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $bg
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $accent
     */
    private static function paintGradient($image, int $width, int $height, array $bg, array $accent): void
    {
        for ($y = 0; $y < $height; $y++) {
            $t = $y / max(1, $height - 1);
            // Vertical wash + slight warm vignette toward corners.
            $r = (int) round($bg[0] * (1 - $t) + $accent[0] * $t * 0.35 + 18 * sin($t * M_PI));
            $g = (int) round($bg[1] * (1 - $t) + $accent[1] * $t * 0.28);
            $b = (int) round($bg[2] * (1 - $t) + $accent[2] * $t * 0.22);
            $row = imagecolorallocate($image, self::channel($r), self::channel($g), self::channel($b));
            if ($row !== false) {
                imageline($image, 0, $y, $width, $y, $row);
            }
        }

        // Soft light bloom (photographic highlight).
        $bloomX = (int) ($width * 0.72);
        $bloomY = (int) ($height * 0.22);
        for ($i = 8; $i >= 1; $i--) {
            $alpha = min(110, 12 * $i);
            $c = imagecolorallocatealpha(
                $image,
                self::channel($accent[0] + 40),
                self::channel($accent[1] + 30),
                self::channel($accent[2] + 20),
                $alpha,
            );
            if ($c !== false) {
                imagefilledellipse($image, $bloomX, $bloomY, 90 * $i, 70 * $i, $c);
            }
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $accent
     * @param  array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}  $bg
     */
    private static function paintSubject($image, int $width, int $height, string $subject, array $accent, array $bg): void
    {
        $fill = imagecolorallocate($image, self::channel($accent[0]), self::channel($accent[1]), self::channel($accent[2]));
        $dark = imagecolorallocate(
            $image,
            self::channel((int) ($bg[0] * 0.45)),
            self::channel((int) ($bg[1] * 0.45)),
            self::channel((int) ($bg[2] * 0.45)),
        );
        if ($fill === false || $dark === false) {
            return;
        }

        $cx = (int) ($width * 0.74);
        $cy = (int) ($height * 0.42);

        match ($subject) {
            'coffee' => self::drawCoffee($image, $cx, $cy, $fill, $dark),
            'plate' => self::drawPlate($image, $cx, $cy, $fill, $dark),
            'building' => self::drawBuilding($image, $cx, $cy, $fill, $dark),
            'fashion' => self::drawFashion($image, $cx, $cy, $fill, $dark),
            'hotel' => self::drawHotel($image, $cx, $cy, $fill, $dark),
            'desk' => self::drawDesk($image, $cx, $cy, $fill, $dark),
            'gym' => self::drawGym($image, $cx, $cy, $fill, $dark),
            'home' => self::drawHome($image, $cx, $cy, $fill, $dark),
            'stage' => self::drawStage($image, $cx, $cy, $fill, $dark),
            'book' => self::drawBook($image, $cx, $cy, $fill, $dark),
            'clinic' => self::drawClinic($image, $cx, $cy, $fill, $dark),
            'dome' => self::drawDome($image, $cx, $cy, $fill, $dark),
            default => self::drawAbstract($image, $cx, $cy, $fill, $dark),
        };
    }

    /** @param  \GdImage  $image */
    private static function drawCoffee($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx, $cy - 10, 160, 40, $fill);
        imagefilledrectangle($image, $cx - 70, $cy - 10, $cx + 70, $cy + 90, $fill);
        imagefilledellipse($image, $cx, $cy + 90, 140, 36, $fill);
        imagearc($image, $cx + 78, $cy + 30, 50, 70, 270, 90, $dark);
        imagearc($image, $cx + 78, $cy + 30, 36, 54, 270, 90, $dark);
        // Steam
        for ($i = 0; $i < 3; $i++) {
            imagearc($image, $cx - 30 + $i * 28, $cy - 70, 24, 50, 200, 340, $dark);
        }
    }

    /** @param  \GdImage  $image */
    private static function drawPlate($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx, $cy + 20, 240, 140, $fill);
        imagefilledellipse($image, $cx, $cy + 20, 180, 100, $dark);
        imagefilledellipse($image, $cx - 20, $cy + 10, 70, 40, $fill);
        imagefilledellipse($image, $cx + 35, $cy + 25, 50, 30, $fill);
    }

    /** @param  \GdImage  $image */
    private static function drawBuilding($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 90, $cy - 110, $cx + 90, $cy + 110, $fill);
        imagefilledrectangle($image, $cx - 40, $cy - 160, $cx + 40, $cy - 110, $fill);
        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 3; $col++) {
                imagefilledrectangle(
                    $image,
                    $cx - 70 + $col * 50,
                    $cy - 90 + $row * 40,
                    $cx - 45 + $col * 50,
                    $cy - 70 + $row * 40,
                    $dark,
                );
            }
        }
    }

    /** @param  \GdImage  $image */
    private static function drawFashion($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx, $cy - 80, 50, 50, $fill);
        imagefilledrectangle($image, $cx - 8, $cy - 55, $cx + 8, $cy - 20, $dark);
        imagefilledrectangle($image, $cx - 70, $cy - 20, $cx + 70, $cy + 20, $fill);
        imagefilledrectangle($image, $cx - 55, $cy + 20, $cx + 55, $cy + 120, $fill);
        imageline($image, $cx - 55, $cy + 40, $cx - 90, $cy + 110, $dark);
        imageline($image, $cx + 55, $cy + 40, $cx + 90, $cy + 110, $dark);
    }

    /** @param  \GdImage  $image */
    private static function drawHotel($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 100, $cy - 100, $cx + 100, $cy + 100, $fill);
        imagefilledrectangle($image, $cx - 35, $cy - 10, $cx + 35, $cy + 100, $dark);
        imagefilledellipse($image, $cx + 25, $cy + 45, 12, 12, $fill);
        imagefilledrectangle($image, $cx - 80, $cy - 70, $cx - 50, $cy - 40, $dark);
        imagefilledrectangle($image, $cx + 50, $cy - 70, $cx + 80, $cy - 40, $dark);
        imagefilledrectangle($image, $cx - 30, $cy - 130, $cx + 30, $cy - 100, $fill);
    }

    /** @param  \GdImage  $image */
    private static function drawDesk($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 110, $cy + 40, $cx + 110, $cy + 70, $fill);
        imagefilledrectangle($image, $cx - 100, $cy + 70, $cx - 85, $cy + 130, $dark);
        imagefilledrectangle($image, $cx + 85, $cy + 70, $cx + 100, $cy + 130, $dark);
        imagefilledrectangle($image, $cx - 50, $cy - 40, $cx + 50, $cy + 30, $fill);
        imagefilledrectangle($image, $cx - 40, $cy - 30, $cx + 40, $cy + 20, $dark);
    }

    /** @param  \GdImage  $image */
    private static function drawGym($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx - 70, $cy, 70, 70, $fill);
        imagefilledellipse($image, $cx + 70, $cy, 70, 70, $fill);
        imagefilledrectangle($image, $cx - 40, $cy - 12, $cx + 40, $cy + 12, $dark);
        imagefilledellipse($image, $cx - 70, $cy, 36, 36, $dark);
        imagefilledellipse($image, $cx + 70, $cy, 36, 36, $dark);
    }

    /** @param  \GdImage  $image */
    private static function drawHome($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 90, $cy - 20, $cx + 90, $cy + 100, $fill);
        $points = [$cx - 110, $cy - 20, $cx, $cy - 110, $cx + 110, $cy - 20];
        imagefilledpolygon($image, $points, $dark);
        imagefilledrectangle($image, $cx - 25, $cy + 20, $cx + 25, $cy + 100, $dark);
        imagefilledrectangle($image, $cx - 70, $cy + 10, $cx - 40, $cy + 40, $dark);
    }

    /** @param  \GdImage  $image */
    private static function drawStage($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 130, $cy + 60, $cx + 130, $cy + 100, $fill);
        imagefilledrectangle($image, $cx - 80, $cy - 60, $cx + 80, $cy + 60, $dark);
        imagefilledellipse($image, $cx, $cy - 20, 60, 60, $fill);
        imagefilledrectangle($image, $cx - 15, $cy + 10, $cx + 15, $cy + 60, $fill);
    }

    /** @param  \GdImage  $image */
    private static function drawBook($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 80, $cy - 90, $cx + 80, $cy + 90, $fill);
        imageline($image, $cx, $cy - 90, $cx, $cy + 90, $dark);
        for ($i = 0; $i < 4; $i++) {
            imageline($image, $cx + 15, $cy - 50 + $i * 28, $cx + 65, $cy - 50 + $i * 28, $dark);
        }
    }

    /** @param  \GdImage  $image */
    private static function drawClinic($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledrectangle($image, $cx - 30, $cy - 100, $cx + 30, $cy + 100, $fill);
        imagefilledrectangle($image, $cx - 100, $cy - 30, $cx + 100, $cy + 30, $fill);
        imagefilledellipse($image, $cx, $cy, 36, 36, $dark);
    }

    /** @param  \GdImage  $image */
    private static function drawDome($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx, $cy - 20, 200, 140, $fill);
        imagefilledrectangle($image, $cx - 100, $cy - 20, $cx + 100, $cy + 100, $fill);
        imagefilledrectangle($image, $cx - 20, $cy - 110, $cx + 20, $cy - 70, $dark);
        imagefilledellipse($image, $cx, $cy - 120, 30, 24, $fill);
    }

    /** @param  \GdImage  $image */
    private static function drawAbstract($image, int $cx, int $cy, int $fill, int $dark): void
    {
        imagefilledellipse($image, $cx, $cy, 180, 180, $fill);
        imagefilledellipse($image, $cx + 40, $cy - 30, 90, 90, $dark);
        imagefilledrectangle($image, $cx - 120, $cy + 70, $cx + 40, $cy + 90, $fill);
    }

    private static function ascii(string $value, int $max): string
    {
        $clean = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return substr($clean, 0, $max);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     * @return array{0: int<0, 255>, 1: int<0, 255>, 2: int<0, 255>}
     */
    private static function rgb(array $rgb): array
    {
        return [
            self::channel($rgb[0]),
            self::channel($rgb[1]),
            self::channel($rgb[2]),
        ];
    }

    /**
     * @return int<0, 255>
     */
    private static function channel(int $value): int
    {
        return max(0, min(255, $value));
    }
}
