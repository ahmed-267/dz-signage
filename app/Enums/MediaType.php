<?php

namespace App\Enums;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Text = 'text';
    case Logo = 'logo';
    case Document = 'document';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Image',
            self::Video => 'Video',
            self::Text => 'Text',
            self::Logo => 'Logo',
            self::Document => 'Document',
            self::Link => 'Link',
        };
    }

    public function isFileBased(): bool
    {
        return match ($this) {
            self::Image, self::Video, self::Logo, self::Document => true,
            self::Text, self::Link => false,
        };
    }

    public function isEditableContent(): bool
    {
        return match ($this) {
            self::Text, self::Link => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return self::cases();
    }
}
