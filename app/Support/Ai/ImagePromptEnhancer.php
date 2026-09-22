<?php

namespace App\Support\Ai;

/**
 * Enhances user image prompts for digital signage (composition, mood, hard no-text).
 */
final class ImagePromptEnhancer
{
    /**
     * @param  array{
     *     aspect?: string|null,
     *     style?: string|null,
     *     style_instruction?: string|null,
     *     industry?: string|null,
     *     orientation?: string|null,
     *     brand_mood?: string|null,
     *     refinement?: string|null,
     *     width?: int|null,
     *     height?: int|null
     * }  $options
     */
    public static function enhance(string $prompt, array $options = []): string
    {
        $prompt = trim($prompt);
        $parts = [$prompt !== '' ? $prompt : 'Premium digital signage background'];

        $industry = trim((string) ($options['industry'] ?? ''));
        if ($industry !== '') {
            $parts[] = 'Setting suited to a '.$industry.' environment';
        }

        $aspect = (string) ($options['aspect'] ?? $options['orientation'] ?? 'landscape');
        $composition = match ($aspect) {
            'portrait' => 'Vertical portrait composition with generous empty space in the lower third for overlay text',
            'square' => 'Square composition with clear negative space on one side for overlay text',
            default => 'Wide landscape composition with text-safe negative space on the left third',
        };
        $parts[] = $composition;

        $styleKey = isset($options['style']) ? (string) $options['style'] : null;
        $styles = config('ai.image.styles', []);
        if ($styleKey && isset($styles[$styleKey])) {
            $parts[] = (string) $styles[$styleKey];
        } elseif (! empty($options['style_instruction'])) {
            $parts[] = (string) $options['style_instruction'];
        }

        $mood = trim((string) ($options['brand_mood'] ?? ''));
        if ($mood !== '') {
            $parts[] = 'Brand mood: '.$mood;
        }

        $refinement = isset($options['refinement']) ? (string) $options['refinement'] : null;
        $refinements = config('ai.image.refinements', []);
        if ($refinement && isset($refinements[$refinement])) {
            $parts[] = (string) $refinements[$refinement];
        }

        $parts[] = 'High-end digital signage artwork, sharp detail, TV-ready contrast';
        $parts[] = (string) config(
            'ai.image.no_text_instruction',
            'Absolutely no text, letters, words, numbers, logos, watermarks, signatures, or typography of any kind in the image.',
        );

        return implode('. ', array_filter($parts, fn ($p) => trim((string) $p) !== '')).'.';
    }
}
