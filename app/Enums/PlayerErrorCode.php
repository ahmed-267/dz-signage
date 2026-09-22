<?php

namespace App\Enums;

enum PlayerErrorCode: string
{
    case ManifestInvalid = 'manifest_invalid';
    case MediaUnavailable = 'media_unavailable';
    case RendererFailure = 'renderer_failure';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ManifestInvalid => 'Manifest invalid',
            self::MediaUnavailable => 'Media unavailable',
            self::RendererFailure => 'Renderer failure',
            self::Unknown => 'Unknown error',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
