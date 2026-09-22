<?php

namespace App\Support\Rendering;

/**
 * Ensures LayoutSchema v1 elements use name + props (renderer contract).
 * Also upgrades legacy flat fields produced by early seeders.
 */
final class LayoutSchemaNormalizer
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function normalize(array $schema): array
    {
        if (! isset($schema['elements']) || ! is_array($schema['elements'])) {
            $schema['elements'] = [];

            return $schema;
        }

        $normalized = [];
        foreach ($schema['elements'] as $index => $element) {
            if (! is_array($element)) {
                continue;
            }
            $normalized[] = self::normalizeElement($element, (int) $index);
        }
        $schema['elements'] = $normalized;

        if (! isset($schema['schemaVersion'])) {
            $schema['schemaVersion'] = LayoutSchema::SCHEMA_VERSION;
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private static function normalizeElement(array $element, int $index): array
    {
        $props = isset($element['props']) && is_array($element['props'])
            ? $element['props']
            : [];

        foreach (['text', 'fill', 'color', 'fontSize', 'fontWeight', 'fontFamily', 'textAlign', 'lineHeight', 'letterSpacing', 'opacity', 'borderRadius', 'objectFit', 'muted', 'loop', 'mediaAssetId', 'placeholder', 'stroke', 'strokeWidth', 'brandBinding'] as $key) {
            if (array_key_exists($key, $element) && ! array_key_exists($key, $props)) {
                $props[$key] = $element[$key];
            }
            unset($element[$key]);
        }

        $element['props'] = $props;

        if (! isset($element['name']) || ! is_string($element['name']) || $element['name'] === '') {
            $element['name'] = is_string($props['text'] ?? null) && $props['text'] !== ''
                ? (string) $props['text']
                : ucfirst((string) ($element['type'] ?? 'element')).' '.($index + 1);
        }

        if (! isset($element['zIndex'])) {
            $element['zIndex'] = $index + 1;
        }

        if (! isset($element['id'])) {
            $element['id'] = 'el-'.($index + 1);
        }

        return $element;
    }
}
