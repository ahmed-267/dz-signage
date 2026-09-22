<?php

namespace App\Support\Rendering;

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;

/**
 * Helpers to build valid LayoutSchema v1 elements for starter templates.
 */
final class LayoutSchemaBuilder
{
    /**
     * @param  list<array<string, mixed>>  $elements
     * @return array<string, mixed>
     */
    public static function make(
        TemplateOrientation $orientation,
        TemplateTheme $theme,
        array $elements,
        ?string $background = null,
    ): array {
        $schema = LayoutSchema::blank($orientation, $theme);
        if ($background !== null) {
            $schema['canvas']['background'] = [
                'type' => 'color',
                'value' => $background,
            ];
        }

        $withZ = [];
        foreach ($elements as $i => $element) {
            $element['zIndex'] = $element['zIndex'] ?? ($i + 1);
            $withZ[] = $element;
        }

        $schema['elements'] = $withZ;

        return LayoutSchemaNormalizer::normalize($schema);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function text(
        string $id,
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props = [],
    ): array {
        return [
            'id' => $id,
            'type' => 'text',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'text' => $name,
                'fontSize' => 32,
                'fontWeight' => 600,
                'color' => '#0F172A',
                'textAlign' => 'left',
                'lineHeight' => 1.2,
                'fontFamily' => 'Outfit, system-ui, sans-serif',
            ], $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function panel(
        string $id,
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props = [],
    ): array {
        return [
            'id' => $id,
            'type' => 'panel',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'fill' => '#FFFFFF',
                'opacity' => 1,
                'borderRadius' => 16,
            ], $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function shape(
        string $id,
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props = [],
    ): array {
        return [
            'id' => $id,
            'type' => 'shape',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'fill' => '#22D3EE',
                'opacity' => 1,
                'borderRadius' => 0,
            ], $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function imagePlaceholder(
        string $id,
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props = [],
    ): array {
        return [
            'id' => $id,
            'type' => 'image',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'placeholder' => true,
                'objectFit' => 'cover',
                'borderRadius' => 12,
                'opacity' => 1,
            ], $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function logoPlaceholder(
        string $id,
        string $name,
        float $x,
        float $y,
        float $width,
        float $height,
        array $props = [],
    ): array {
        return [
            'id' => $id,
            'type' => 'logo',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $width,
            'height' => $height,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'placeholder' => true,
                'objectFit' => 'contain',
                'borderRadius' => 8,
                'opacity' => 1,
            ], $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $styleProps
     * @return array<string, mixed>
     */
    public static function widget(
        string $widgetType,
        string $name,
        float $x,
        float $y,
        float $w,
        float $h,
        array $config = [],
        array $styleProps = [],
    ): array {
        $normalizedType = strtolower(trim($widgetType));
        $normalizedType = str_replace('-', '_', $normalizedType);

        return [
            'id' => 'widget-'.$normalizedType.'-'.substr(md5($name.$x.$y.$w.$h), 0, 8),
            'type' => 'widget',
            'name' => $name,
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'locked' => false,
            'editable' => true,
            'props' => array_merge([
                'widgetType' => $normalizedType,
                'config' => $config,
                'opacity' => 1,
                'borderRadius' => 12,
            ], $styleProps),
        ];
    }
}
