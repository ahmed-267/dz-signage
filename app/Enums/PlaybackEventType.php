<?php

namespace App\Enums;

enum PlaybackEventType: string
{
    case ContentStarted = 'content_started';
    case ContentEnded = 'content_ended';
    case PlaylistItemStarted = 'playlist_item_started';
    case DeploymentApplied = 'deployment_applied';
    case PlayerError = 'player_error';

    public function label(): string
    {
        return match ($this) {
            self::ContentStarted => 'Content started',
            self::ContentEnded => 'Content ended',
            self::PlaylistItemStarted => 'Playlist item started',
            self::DeploymentApplied => 'Deployment applied',
            self::PlayerError => 'Player error',
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
