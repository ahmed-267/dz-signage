<?php

namespace App\Support\Screens;

/**
 * Display acknowledgement for Publishing UI — derived from Deployment + heartbeat sync.
 * Not a stored Deployment status.
 */
final class PublishingAck
{
    public const NONE = 'none';

    public const LIVE = 'live';

    public const UPDATING = 'updating';

    public const WAITING = 'waiting';

    public const PUBLISHING = 'publishing';

    public const SCHEDULED = 'scheduled';

    public static function label(string $ack): string
    {
        return match ($ack) {
            self::LIVE => 'Live',
            self::UPDATING => 'Updating',
            self::WAITING => 'Waiting',
            self::PUBLISHING => 'Publishing',
            self::SCHEDULED => 'Scheduled',
            default => 'No content',
        };
    }
}
