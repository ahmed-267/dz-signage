<?php

namespace App\Enums;

enum ContentSyncState: string
{
    case UpToDate = 'up_to_date';
    case OutOfSync = 'out_of_sync';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::UpToDate => 'Up to date',
            self::OutOfSync => 'Out of sync',
            self::Unknown => 'Unknown',
        };
    }
}
