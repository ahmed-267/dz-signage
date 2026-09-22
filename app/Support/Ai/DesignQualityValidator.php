<?php

namespace App\Support\Ai;

use App\Models\BrandKit;

/**
 * Validates and lightly repairs LayoutSchema output after AI / archetype build.
 *
 * @phpstan-type ValidationResult array{ok: bool, issues: list<string>, soft: list<string>, schema: array<string, mixed>}
 */
final class DesignQualityValidator
{
    /**
     * @param  array<string, mixed>  $schema
     * @return ValidationResult
     */
    public static function validate(array $schema): array
    {
        $issues = [];
        $soft = [];
        $canvas = is_array($schema['canvas'] ?? null) ? $schema['canvas'] : [];
        $canvasW = (int) ($canvas['width'] ?? 1920);
        $canvasH = (int) ($canvas['height'] ?? 1080);
        /** @var list<array<string, mixed>> $elements */
        $elements = array_values(array_filter(
            is_array($schema['elements'] ?? null) ? $schema['elements'] : [],
            fn ($el) => is_array($el),
        ));

        $minElements = (int) config('ai.design.min_elements', 3);
        $maxElements = (int) config('ai.design.max_elements', 14);
        $minMargin = (int) config('ai.design.min_margin', 48);
        $minHeadline = (int) config('ai.design.min_headline_font', 48);
        $minBody = (int) config('ai.design.min_body_font', 28);
        $maxTextChars = (int) config('ai.design.max_text_chars', 120);
        $maxOverlap = (float) config('ai.design.max_overlap_ratio', 0.15);
        $minFillRatio = (float) config('ai.design.min_fill_ratio', 0.08);
        $premium = strtolower((string) config('ai.quality_mode', 'premium')) === 'premium';
        $safeFonts = config('ai.design.safe_fonts', BrandKit::allowedFonts());

        $count = count($elements);
        if ($count < $minElements) {
            $issues[] = "Too few elements ({$count} < {$minElements}).";
        }
        if ($count > $maxElements) {
            $issues[] = "Too many elements ({$count} > {$maxElements}).";
        }

        $textBoxes = [];
        $coveredArea = 0.0;

        foreach ($elements as $i => $el) {
            $x = (float) ($el['x'] ?? 0);
            $y = (float) ($el['y'] ?? 0);
            $w = (float) ($el['width'] ?? 0);
            $h = (float) ($el['height'] ?? 0);
            $type = (string) ($el['type'] ?? '');

            if ($w < 40 || $h < 40) {
                $issues[] = "Element {$i} is too small.";
            }
            if ($x < 0 || $y < 0 || $x + $w > $canvasW + 1 || $y + $h > $canvasH + 1) {
                $issues[] = "Element {$i} is out of bounds.";
            }

            $coveredArea += $w * $h;

            $isFullBleed = self::isFullBleed($x, $y, $w, $h, $canvasW, $canvasH);
            $isEdgeAccent = self::isEdgeAccent($x, $y, $w, $h, $canvasW, $canvasH);

            if (! $isFullBleed && ! $isEdgeAccent && in_array($type, ['text', 'logo', 'image', 'widget'], true)) {
                $marginIssue = self::marginViolation($x, $y, $w, $h, $canvasW, $canvasH, $minMargin);
                if ($marginIssue !== null) {
                    $message = "Element {$i} ({$type}) violates min margin ({$minMargin}px).";
                    if ($premium && $type === 'text') {
                        $issues[] = $message;
                    } else {
                        $soft[] = $message;
                    }
                }
            }

            if ($type === 'text') {
                $props = is_array($el['props'] ?? null) ? $el['props'] : [];
                $fontSize = (float) ($props['fontSize'] ?? 0);
                $text = (string) ($props['text'] ?? '');
                $font = (string) ($props['fontFamily'] ?? '');
                $name = strtolower((string) ($el['name'] ?? ''));
                $isHeadline = self::isHeadlineName($name, $fontSize);

                if ($isHeadline && $fontSize < $minHeadline) {
                    $issues[] = "Headline font too small ({$fontSize} < {$minHeadline}).";
                } elseif (! $isHeadline && $fontSize > 0 && $fontSize < $minBody) {
                    $issues[] = "Body font too small ({$fontSize} < {$minBody}).";
                }

                if (mb_strlen($text) > $maxTextChars) {
                    $issues[] = "Text too long on element {$i}.";
                }

                if ($fontSize > 0 && $w > 0 && $h > 0 && $text !== '') {
                    $minForRole = $isHeadline ? $minHeadline : $minBody;
                    if (! TextFit::fits($text, $fontSize, $w, $h)) {
                        // After refine, clipping should be rare — still surface it.
                        $soft[] = "Text may clip on element {$i}.";
                        if ($premium && $fontSize >= $minForRole && ! TextFit::fits($text, $minForRole, $w, $h)) {
                            $issues[] = "Text overflows box on element {$i}.";
                        }
                    }
                }

                $fontRoot = trim(explode(',', $font)[0]);
                if ($fontRoot !== '' && is_array($safeFonts) && ! in_array($fontRoot, $safeFonts, true)
                    && ! str_contains($font, 'system-ui') && ! str_contains($font, 'Outfit')) {
                    $issues[] = "Unsupported font on element {$i}.";
                }

                $color = (string) ($props['color'] ?? '');
                if ($color !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                    $issues[] = "Invalid text colour on element {$i}.";
                }

                $textBoxes[] = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'i' => $i];
            }

            if ($type === 'shape') {
                $fill = (string) (($el['props']['fill'] ?? '') ?: '');
                if ($fill !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $fill) !== 1) {
                    $issues[] = "Invalid shape fill on element {$i}.";
                }
            }
        }

        $canvasArea = max(1.0, (float) ($canvasW * $canvasH));
        $fillRatio = min(1.0, $coveredArea / $canvasArea);
        if ($count >= $minElements && $fillRatio < $minFillRatio) {
            $soft[] = sprintf('Layout fill looks sparse (%.1f%%).', $fillRatio * 100);
        }

        for ($a = 0; $a < count($textBoxes); $a++) {
            for ($b = $a + 1; $b < count($textBoxes); $b++) {
                $ratio = self::overlapRatio($textBoxes[$a], $textBoxes[$b]);
                if ($ratio > $maxOverlap) {
                    $issues[] = "Text elements {$textBoxes[$a]['i']} and {$textBoxes[$b]['i']} overlap significantly.";
                }
            }
        }

        $fatal = array_values(array_filter(
            $issues,
            fn (string $issue) => str_contains($issue, 'out of bounds')
                || str_contains($issue, 'Too few elements')
                || str_contains($issue, 'overlap significantly')
                || str_contains($issue, 'Headline font too small')
                || str_contains($issue, 'Text overflows box')
                || ($premium && str_contains($issue, 'violates min margin')),
        ));

        return [
            'ok' => $fatal === [],
            'issues' => array_values(array_unique([...$issues, ...$soft])),
            'soft' => $soft,
            'schema' => $schema,
        ];
    }

    /**
     * Apply safe fixes (clamp bounds, TextFit, trim text) without inventing layout.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        $canvas = is_array($schema['canvas'] ?? null) ? $schema['canvas'] : [];
        $canvasW = (int) ($canvas['width'] ?? 1920);
        $canvasH = (int) ($canvas['height'] ?? 1080);
        $minHeadline = (int) config('ai.design.min_headline_font', 48);
        $minBody = (int) config('ai.design.min_body_font', 28);
        $maxTextChars = (int) config('ai.design.max_text_chars', 120);
        $maxElements = (int) config('ai.design.max_elements', 14);

        $elements = is_array($schema['elements'] ?? null) ? $schema['elements'] : [];
        $normalized = [];

        foreach (array_slice($elements, 0, $maxElements) as $el) {
            if (! is_array($el)) {
                continue;
            }

            $w = max(40, min($canvasW, (float) ($el['width'] ?? 200)));
            $h = max(40, min($canvasH, (float) ($el['height'] ?? 80)));
            $x = max(0, min($canvasW - $w, (float) ($el['x'] ?? 0)));
            $y = max(0, min($canvasH - $h, (float) ($el['y'] ?? 0)));

            $el['x'] = (int) round($x);
            $el['y'] = (int) round($y);
            $el['width'] = (int) round($w);
            $el['height'] = (int) round($h);

            if (($el['type'] ?? '') === 'text' && is_array($el['props'] ?? null)) {
                $name = strtolower((string) ($el['name'] ?? ''));
                $fontSize = (float) ($el['props']['fontSize'] ?? $minBody);
                $isHeadline = self::isHeadlineName($name, $fontSize);
                $minFont = $isHeadline ? $minHeadline : $minBody;
                $maxFont = $isHeadline ? 200 : 72;

                $text = trim(strip_tags((string) ($el['props']['text'] ?? '')));
                $fitted = TextFit::fit(
                    $text,
                    $fontSize,
                    (float) $el['width'],
                    (float) $el['height'],
                    $minFont,
                    $maxFont,
                    maxChars: $maxTextChars,
                );
                $el['props']['text'] = $fitted['text'];
                $el['props']['fontSize'] = $fitted['fontSize'];

                $color = (string) ($el['props']['color'] ?? '#FFFFFF');
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) !== 1) {
                    $el['props']['color'] = '#FFFFFF';
                }
            }

            $normalized[] = $el;
        }

        $schema['elements'] = $normalized;

        return $schema;
    }

    /**
     * One deterministic refine pass: push margins, shrink/shorten copy, re-fit text.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function refine(array $schema): array
    {
        $canvas = is_array($schema['canvas'] ?? null) ? $schema['canvas'] : [];
        $canvasW = (int) ($canvas['width'] ?? 1920);
        $canvasH = (int) ($canvas['height'] ?? 1080);
        $minMargin = (int) config('ai.design.min_margin', 48);
        $minHeadline = (int) config('ai.design.min_headline_font', 48);
        $minBody = (int) config('ai.design.min_body_font', 28);
        $maxTextChars = (int) config('ai.design.max_text_chars', 120);

        if (! is_array($schema['elements'] ?? null)) {
            return $schema;
        }

        foreach ($schema['elements'] as &$el) {
            if (! is_array($el)) {
                continue;
            }

            $type = (string) ($el['type'] ?? '');
            $x = (float) ($el['x'] ?? 0);
            $y = (float) ($el['y'] ?? 0);
            $w = (float) ($el['width'] ?? 0);
            $h = (float) ($el['height'] ?? 0);

            if (self::isFullBleed($x, $y, $w, $h, $canvasW, $canvasH)
                || self::isEdgeAccent($x, $y, $w, $h, $canvasW, $canvasH)) {
                continue;
            }

            if (in_array($type, ['text', 'logo', 'image', 'widget'], true)) {
                $maxW = max(40, $canvasW - (2 * $minMargin));
                $maxH = max(40, $canvasH - (2 * $minMargin));
                $w = min($w, $maxW);
                $h = min($h, $maxH);
                $x = max($minMargin, min($canvasW - $minMargin - $w, $x));
                $y = max($minMargin, min($canvasH - $minMargin - $h, $y));
                // If still overflowing after clamp, shrink toward margin box.
                if ($x + $w > $canvasW - $minMargin) {
                    $w = max(40, $canvasW - $minMargin - $x);
                }
                if ($y + $h > $canvasH - $minMargin) {
                    $h = max(40, $canvasH - $minMargin - $y);
                }

                $el['x'] = (int) round($x);
                $el['y'] = (int) round($y);
                $el['width'] = (int) round($w);
                $el['height'] = (int) round($h);
            }

            if ($type === 'text' && is_array($el['props'] ?? null)) {
                $name = strtolower((string) ($el['name'] ?? ''));
                $fontSize = (float) ($el['props']['fontSize'] ?? $minBody);
                $isHeadline = self::isHeadlineName($name, $fontSize);
                $minFont = $isHeadline ? $minHeadline : $minBody;
                $maxFont = $isHeadline ? 200 : 72;
                $maxChars = $isHeadline ? min(48, $maxTextChars) : $maxTextChars;

                $raw = (string) ($el['props']['text'] ?? '');
                $shortened = TextFit::shortenForSignage(
                    $raw,
                    $maxChars,
                    $isHeadline ? 6 : 16,
                );

                $fitted = TextFit::fit(
                    $shortened,
                    $fontSize,
                    (float) $el['width'],
                    (float) $el['height'],
                    $minFont,
                    $maxFont,
                    maxChars: $maxChars,
                );
                $el['props']['text'] = $fitted['text'];
                $el['props']['fontSize'] = $fitted['fontSize'];
            }
        }
        unset($el);

        return self::normalize($schema);
    }

    private static function isHeadlineName(string $name, float $fontSize): bool
    {
        return str_contains($name, 'headline')
            || str_contains($name, 'heading')
            || (str_contains($name, 'title') && ! str_contains($name, 'subtitle'))
            || $name === 'day'
            || str_contains($name, 'day number')
            || $fontSize >= 56;
    }

    private static function isFullBleed(float $x, float $y, float $w, float $h, int $canvasW, int $canvasH): bool
    {
        return $x <= 1 && $y <= 1 && $w >= $canvasW - 2 && $h >= $canvasH - 2;
    }

    /**
     * Thin edge bars / full-height side panels / footer bars are intentional and exempt from margin.
     */
    private static function isEdgeAccent(float $x, float $y, float $w, float $h, int $canvasW, int $canvasH): bool
    {
        $touchesLeft = $x <= 1;
        $touchesRight = $x + $w >= $canvasW - 1;
        $touchesTop = $y <= 1;
        $touchesBottom = $y + $h >= $canvasH - 1;

        // Vertical edge strip
        if (($touchesLeft || $touchesRight) && $h >= $canvasH * 0.85 && $w <= $canvasW * 0.55) {
            return true;
        }
        // Horizontal edge strip / footer
        if (($touchesTop || $touchesBottom) && $w >= $canvasW * 0.85 && $h <= $canvasH * 0.25) {
            return true;
        }
        // Half-canvas split panels
        if (($touchesLeft || $touchesRight) && $w >= $canvasW * 0.45 && $h >= $canvasH * 0.85) {
            return true;
        }

        return false;
    }

    private static function marginViolation(
        float $x,
        float $y,
        float $w,
        float $h,
        int $canvasW,
        int $canvasH,
        int $minMargin,
    ): ?string {
        if ($x < $minMargin || $y < $minMargin) {
            return 'edge';
        }
        if ($x + $w > $canvasW - $minMargin || $y + $h > $canvasH - $minMargin) {
            return 'edge';
        }

        return null;
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $a
     * @param  array{x: float, y: float, w: float, h: float}  $b
     */
    private static function overlapRatio(array $a, array $b): float
    {
        $x1 = max($a['x'], $b['x']);
        $y1 = max($a['y'], $b['y']);
        $x2 = min($a['x'] + $a['w'], $b['x'] + $b['w']);
        $y2 = min($a['y'] + $a['h'], $b['y'] + $b['h']);

        $iw = max(0, $x2 - $x1);
        $ih = max(0, $y2 - $y1);
        $intersection = $iw * $ih;
        if ($intersection <= 0) {
            return 0.0;
        }

        $smaller = min($a['w'] * $a['h'], $b['w'] * $b['h']);

        return $smaller > 0 ? $intersection / $smaller : 0.0;
    }
}
