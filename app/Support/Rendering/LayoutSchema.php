<?php

namespace App\Support\Rendering;

use App\Enums\TemplateOrientation;
use App\Enums\TemplateTheme;

final class LayoutSchema
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array{
     *     schemaVersion: int,
     *     canvas: array{
     *         width: int,
     *         height: int,
     *         orientation: string,
     *         background: array{type: string, value: string}
     *     },
     *     theme: string,
     *     elements: list<array<string, mixed>>
     * }
     */
    public static function blank(TemplateOrientation $orientation, TemplateTheme $theme): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'canvas' => [
                'width' => $orientation->canvasWidth(),
                'height' => $orientation->canvasHeight(),
                'orientation' => $orientation->value,
                'background' => [
                    'type' => 'color',
                    'value' => $theme->defaultBackground(),
                ],
            ],
            'theme' => $theme->value,
            'elements' => [],
        ];
    }
}
