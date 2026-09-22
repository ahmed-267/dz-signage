<?php

namespace App\Enums;

/**
 * What is driving the content on a Screen right now: a matching Schedule
 * (timing), the active Deployment (Publish to Screen), or nothing.
 */
enum ScreenContentSource: string
{
    case Schedule = 'schedule';
    case Deployment = 'deployment';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Schedule => 'Schedule',
            self::Deployment => 'Published to screen',
            self::None => 'No content',
        };
    }
}
