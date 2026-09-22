<?php

namespace App\Support\Ai;

/**
 * Rough text-box fitting for digital signage (no browser measureText).
 *
 * Uses average glyph width ≈ 0.55×fontSize for proportional sans/serif stacks.
 */
final class TextFit
{
    private const AVG_CHAR_RATIO = 0.55;

    /**
     * Whether {@see $text} fits inside the box at the given font size.
     */
    public static function fits(
        string $text,
        float $fontSize,
        float $width,
        float $height,
        float $lineHeight = 1.2,
    ): bool {
        if ($fontSize <= 0 || $width <= 0 || $height <= 0) {
            return false;
        }

        $text = trim($text);
        if ($text === '') {
            return true;
        }

        $metrics = self::measure($text, $fontSize, $width, $lineHeight);

        return $metrics['requiredHeight'] <= $height + 0.5
            && $metrics['longestLineWidth'] <= $width + 0.5;
    }

    /**
     * Shrink fontSize within [min, max] and/or truncate text until it fits.
     *
     * @return array{text: string, fontSize: int, fits: bool, truncated: bool}
     */
    public static function fit(
        string $text,
        float $fontSize,
        float $width,
        float $height,
        float $minFontSize,
        float $maxFontSize,
        float $lineHeight = 1.2,
        ?int $maxChars = null,
    ): array {
        $maxChars ??= (int) config('ai.design.max_text_chars', 120);
        $text = trim(strip_tags($text));
        if ($maxChars > 0) {
            $text = mb_substr($text, 0, $maxChars);
        }

        $fontSize = max($minFontSize, min($maxFontSize, $fontSize));
        $truncated = false;

        if ($text === '' || $width <= 0 || $height <= 0) {
            return [
                'text' => $text,
                'fontSize' => (int) round($fontSize),
                'fits' => true,
                'truncated' => false,
            ];
        }

        // Shrink font first while staying readable.
        $size = $fontSize;
        while ($size > $minFontSize && ! self::fits($text, $size, $width, $height, $lineHeight)) {
            $size = max($minFontSize, $size - 2);
        }

        if (self::fits($text, $size, $width, $height, $lineHeight)) {
            return [
                'text' => $text,
                'fontSize' => (int) round($size),
                'fits' => true,
                'truncated' => false,
            ];
        }

        // Truncate to fit at min font size.
        $candidate = $text;
        while (mb_strlen($candidate) > 1 && ! self::fits($candidate, $minFontSize, $width, $height, $lineHeight)) {
            $candidate = rtrim(mb_substr($candidate, 0, mb_strlen($candidate) - 1));
            $truncated = true;
        }

        if ($truncated && mb_strlen($candidate) > 3) {
            $candidate = rtrim(mb_substr($candidate, 0, max(1, mb_strlen($candidate) - 1))).'…';
            // Ensure ellipsis variant still fits; drop one more char if needed.
            while (mb_strlen($candidate) > 2 && ! self::fits($candidate, $minFontSize, $width, $height, $lineHeight)) {
                $candidate = rtrim(mb_substr($candidate, 0, mb_strlen($candidate) - 2)).'…';
            }
        }

        $fits = self::fits($candidate, $minFontSize, $width, $height, $lineHeight);

        return [
            'text' => $candidate,
            'fontSize' => (int) round($minFontSize),
            'fits' => $fits,
            'truncated' => $truncated,
        ];
    }

    /**
     * Soften long copy for TV readability (word-aware).
     */
    public static function shortenForSignage(string $text, int $maxChars, int $maxWords = 8): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? $text);
        if ($text === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > $maxWords) {
            $text = implode(' ', array_slice($words, 0, $maxWords));
        }

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        $cut = mb_substr($text, 0, $maxChars);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > (int) ($maxChars * 0.4)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut);
    }

    /**
     * @return array{lineCount: int, requiredHeight: float, longestLineWidth: float}
     */
    public static function measure(
        string $text,
        float $fontSize,
        float $width,
        float $lineHeight = 1.2,
    ): array {
        $charWidth = max(1.0, $fontSize * self::AVG_CHAR_RATIO);
        $charsPerLine = max(1, (int) floor($width / $charWidth));
        $lineHeightPx = $fontSize * $lineHeight;

        $lines = preg_split('/\R/u', $text) ?: [$text];
        $wrapped = 0;
        $longest = 0.0;

        foreach ($lines as $line) {
            $len = mb_strlen($line);
            if ($len === 0) {
                $wrapped++;

                continue;
            }
            $needed = (int) ceil($len / $charsPerLine);
            $wrapped += max(1, $needed);
            $longest = max($longest, min($len, $charsPerLine) * $charWidth);
        }

        return [
            'lineCount' => max(1, $wrapped),
            'requiredHeight' => max(1, $wrapped) * $lineHeightPx,
            'longestLineWidth' => $longest,
        ];
    }
}
