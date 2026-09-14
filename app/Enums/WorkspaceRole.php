<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Designer = 'designer';
    case ContentManager = 'content_manager';
    case LocationManager = 'location_manager';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Designer => 'Designer',
            self::ContentManager => 'Content Manager',
            self::LocationManager => 'Location Manager',
            self::Viewer => 'Viewer',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full control including billing and team',
            self::Admin => 'Manage operational platform settings and team',
            self::Designer => 'Templates, media and designs',
            self::ContentManager => 'Designs, playlists and schedules',
            self::LocationManager => 'Assigned locations only',
            self::Viewer => 'Read-only access',
        };
    }

    /**
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [
            self::Admin,
            self::Designer,
            self::ContentManager,
            self::LocationManager,
            self::Viewer,
        ];
    }

    public function canManageWorkspace(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }

    public function canManageTeam(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}
