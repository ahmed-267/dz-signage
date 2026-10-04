<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$iconsDir = $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'icons';

if (! is_dir($iconsDir) && ! mkdir($iconsDir, 0777, true) && ! is_dir($iconsDir)) {
    fwrite(STDERR, "Unable to create {$iconsDir}\n");
    exit(1);
}

function roundedFill(GdImage $im, float $x1, float $y1, float $x2, float $y2, float $r, int $color): void
{
    imagefilledrectangle($im, (int) ($x1 + $r), (int) $y1, (int) ($x2 - $r), (int) $y2, $color);
    imagefilledrectangle($im, (int) $x1, (int) ($y1 + $r), (int) $x2, (int) ($y2 - $r), $color);
    imagefilledellipse($im, (int) ($x1 + $r), (int) ($y1 + $r), (int) ($r * 2), (int) ($r * 2), $color);
    imagefilledellipse($im, (int) ($x2 - $r), (int) ($y1 + $r), (int) ($r * 2), (int) ($r * 2), $color);
    imagefilledellipse($im, (int) ($x1 + $r), (int) ($y2 - $r), (int) ($r * 2), (int) ($r * 2), $color);
    imagefilledellipse($im, (int) ($x2 - $r), (int) ($y2 - $r), (int) ($r * 2), (int) ($r * 2), $color);
}

function thickLine(GdImage $im, float $x1, float $y1, float $x2, float $y2, float $w, int $color): void
{
    imagesetthickness($im, max(1, (int) round($w)));
    imageline($im, (int) round($x1), (int) round($y1), (int) round($x2), (int) round($y2), $color);
}

function drawMark(GdImage $im, float $ox, float $oy, float $box, int $color): void
{
    $u = $box / 24;
    $w = max(2, $u * 2);
    $x1 = $ox + 2 * $u;
    $y1 = $oy + 5 * $u;
    $x2 = $ox + 22 * $u;
    $y2 = $oy + 19 * $u;
    $r = 2 * $u;

    thickLine($im, $x1 + $r, $y1, $x2 - $r, $y1, $w, $color);
    thickLine($im, $x1 + $r, $y2, $x2 - $r, $y2, $w, $color);
    thickLine($im, $x1, $y1 + $r, $x1, $y2 - $r, $w, $color);
    thickLine($im, $x2, $y1 + $r, $x2, $y2 - $r, $w, $color);
    imagefilledellipse($im, (int) round($x1 + $r), (int) round($y1 + $r), (int) round($w), (int) round($w), $color);
    imagefilledellipse($im, (int) round($x2 - $r), (int) round($y1 + $r), (int) round($w), (int) round($w), $color);
    imagefilledellipse($im, (int) round($x1 + $r), (int) round($y2 - $r), (int) round($w), (int) round($w), $color);
    imagefilledellipse($im, (int) round($x2 - $r), (int) round($y2 - $r), (int) round($w), (int) round($w), $color);

    thickLine($im, $ox + 12 * $u, $oy + 19 * $u, $ox + 12 * $u, $oy + 21 * $u, $w, $color);
    thickLine($im, $ox + 8 * $u, $oy + 21 * $u, $ox + 16 * $u, $oy + 21 * $u, $w, $color);
    thickLine($im, $ox + 8 * $u, $oy + 9 * $u, $ox + 11 * $u, $oy + 11.5 * $u, $w, $color);
    thickLine($im, $ox + 11 * $u, $oy + 11.5 * $u, $ox + 8 * $u, $oy + 14 * $u, $w, $color);
    thickLine($im, $ox + 13 * $u, $oy + 14 * $u, $ox + 16 * $u, $oy + 14 * $u, $w, $color);
}

function makeIcon(string $path, int $size, float $padRatio, bool $opaque = false): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    $navy = imagecolorallocate($im, 9, 9, 14);

    if ($opaque) {
        imagefilledrectangle($im, 0, 0, $size, $size, $navy);
        imagealphablending($im, true);
    } else {
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $size, $size, $transparent);
        imagealphablending($im, true);
        $pad = (int) round($size * $padRatio);
        $inner = $size - (2 * $pad);
        $radius = (int) round($inner * 0.22);
        roundedFill($im, $pad, $pad, $size - $pad, $size - $pad, max(8, $radius), $navy);
    }

    $cyan = imagecolorallocate($im, 34, 211, 238);
    $pad = (int) round($size * $padRatio);
    $inner = $size - (2 * $pad);
    $markPad = (int) round($inner * 0.16);
    $box = $inner - (2 * $markPad);
    drawMark($im, $pad + $markPad, $pad + $markPad, $box, $cyan);

    imagepng($im, $path, 9);
    imagedestroy($im);
}

makeIcon($iconsDir.DIRECTORY_SEPARATOR.'pwa-192.png', 192, 0.06);
makeIcon($iconsDir.DIRECTORY_SEPARATOR.'pwa-512.png', 512, 0.06);
makeIcon($iconsDir.DIRECTORY_SEPARATOR.'pwa-192-maskable.png', 192, 0.18);
makeIcon($iconsDir.DIRECTORY_SEPARATOR.'pwa-512-maskable.png', 512, 0.18);
makeIcon($root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'apple-touch-icon.png', 180, 0.08, true);

fwrite(STDOUT, "Generated PWA icons in public/icons and apple-touch-icon.png\n");
