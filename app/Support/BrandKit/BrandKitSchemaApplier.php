<?php

namespace App\Support\BrandKit;

use App\Models\BrandKit;
use App\Models\MediaAsset;
use App\Models\Workspace;

/**
 * Applies semantic Brand Kit bindings onto a LayoutSchema copy.
 *
 * Missing Brand Kit values leave template placeholders unchanged (never null them out).
 */
final class BrandKitSchemaApplier
{
    /**
     * @var list<string>
     */
    public const BINDINGS = [
        'brand.business_name',
        'brand.tagline',
        'brand.logo',
        'brand.primary_color',
        'brand.secondary_color',
        'brand.accent_color',
        'brand.background_color',
        'brand.text_color',
        'brand.heading_font',
        'brand.body_font',
    ];

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public static function apply(array $schema, ?BrandKit $kit, Workspace $workspace): array
    {
        /** @var array<string, mixed> $copy */
        $copy = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        if ($kit === null) {
            return $copy;
        }

        $copy = self::applyCanvasBinding($copy, $kit);

        $elements = $copy['elements'] ?? [];
        if (! is_array($elements)) {
            return $copy;
        }

        $next = [];
        foreach ($elements as $element) {
            if (! is_array($element)) {
                continue;
            }
            $next[] = self::applyElementBinding($element, $kit, $workspace);
        }
        $copy['elements'] = $next;

        return $copy;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    private static function applyCanvasBinding(array $schema, BrandKit $kit): array
    {
        $background = $schema['canvas']['background'] ?? null;
        if (! is_array($background)) {
            return $schema;
        }

        $binding = $background['brandBinding'] ?? null;
        if (! is_string($binding) || $binding === '') {
            return $schema;
        }

        $color = self::resolveColor($kit, $binding);
        if ($color === null) {
            return $schema;
        }

        $background['type'] = 'color';
        $background['value'] = $color;
        $schema['canvas']['background'] = $background;

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array<string, mixed>
     */
    private static function applyElementBinding(array $element, BrandKit $kit, Workspace $workspace): array
    {
        $props = isset($element['props']) && is_array($element['props'])
            ? $element['props']
            : [];

        $binding = $props['brandBinding'] ?? null;
        if (! is_string($binding) || $binding === '') {
            $element['props'] = $props;

            return $element;
        }

        $type = (string) ($element['type'] ?? '');

        switch ($binding) {
            case 'brand.business_name':
                if (filled($kit->name)) {
                    $props['text'] = (string) $kit->name;
                }
                break;

            case 'brand.tagline':
                if (filled($kit->tagline)) {
                    $props['text'] = (string) $kit->tagline;
                }
                break;

            case 'brand.logo':
                $logoId = self::resolveOwnedLogoId($kit, $workspace);
                if ($logoId !== null) {
                    $props['mediaAssetId'] = $logoId;
                    unset($props['placeholder']);
                }
                break;

            case 'brand.primary_color':
            case 'brand.secondary_color':
            case 'brand.accent_color':
            case 'brand.background_color':
            case 'brand.text_color':
                $color = self::resolveColor($kit, $binding);
                if ($color !== null) {
                    self::applyColorToElement($props, $type, $color);
                }
                break;

            case 'brand.heading_font':
                if (filled($kit->heading_font)) {
                    $props['fontFamily'] = BrandKit::fontStack((string) $kit->heading_font);
                }
                break;

            case 'brand.body_font':
                if (filled($kit->body_font)) {
                    $props['fontFamily'] = BrandKit::fontStack((string) $kit->body_font);
                }
                break;
        }

        $element['props'] = $props;

        return $element;
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function applyColorToElement(array &$props, string $type, string $color): void
    {
        if ($type === 'text') {
            $props['color'] = $color;

            return;
        }

        if (in_array($type, ['panel', 'shape'], true)) {
            $props['fill'] = $color;
        }
    }

    private static function resolveColor(BrandKit $kit, string $binding): ?string
    {
        $raw = match ($binding) {
            'brand.primary_color' => $kit->primary_color,
            'brand.secondary_color' => $kit->secondary_color,
            'brand.accent_color' => $kit->accent_color,
            'brand.background_color' => $kit->background_color,
            'brand.text_color' => $kit->text_color,
            default => null,
        };

        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $kit->normalizeHex($raw) ?? $raw;
    }

    private static function resolveOwnedLogoId(BrandKit $kit, Workspace $workspace): ?int
    {
        $logoId = $kit->logo_media_asset_id;
        if ($logoId === null) {
            return null;
        }

        $owned = MediaAsset::query()
            ->whereKey($logoId)
            ->where('workspace_id', $workspace->id)
            ->exists();

        return $owned ? (int) $logoId : null;
    }
}
