<?php

namespace App\Enums;

enum AiGenerationType: string
{
    case Text = 'text';
    case Image = 'image';
    case Design = 'design';
    case Video = 'video';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Design => 'Design',
            self::Video => 'Video',
            self::Agent => 'Agent',
        };
    }
}
