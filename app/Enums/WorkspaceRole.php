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
            self::Owner => 'Full Business control including billing and team',
            self::Admin => 'Broad operational control for the Business (not platform Admin)',
            self::Designer => 'Browse Templates, manage Media and Screen Designs',
            self::ContentManager => 'Media, designs, playlists, schedules and publishing',
            self::LocationManager => 'Assigned locations and their screens',
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

    public function canViewBilling(): bool
    {
        return match ($this) {
            self::Owner, self::Admin => true,
            default => false,
        };
    }

    /**
     * Billing management is Owner-only until a richer billing phase exists.
     */
    public function canManageBilling(): bool
    {
        return $this === self::Owner;
    }

    public function canViewAnalytics(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Designer, self::ContentManager, self::Viewer => true,
            self::LocationManager => true,
        };
    }

    public function canViewMedia(): bool
    {
        return true;
    }

    public function canManageMedia(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Designer, self::ContentManager => true,
            self::LocationManager, self::Viewer => false,
        };
    }

    public function canDeleteMedia(): bool
    {
        return $this->canManageMedia();
    }

    public function canViewTemplates(): bool
    {
        return true;
    }

    /**
     * Workspace roles never manage master Templates.
     * Templates are platform-owned (Super Admin / Admin).
     */
    public function canManageTemplates(): bool
    {
        return false;
    }

    public function canPublishTemplates(): bool
    {
        return false;
    }

    /**
     * Future Screen Designs (Phase 4) — intent flags for nav/docs/tests.
     */
    public function canManageScreenDesigns(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Designer, self::ContentManager => true,
            default => false,
        };
    }

    public function canManagePlaylists(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::ContentManager => true,
            default => false,
        };
    }

    public function canManageSchedules(): bool
    {
        return $this->canManagePlaylists();
    }

    public function canPublishContent(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::ContentManager => true,
            default => false,
        };
    }

    public function canManageScreens(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::LocationManager => true,
            default => false,
        };
    }

    public function canManageLocations(): bool
    {
        return $this->canManageScreens();
    }

    /**
     * Brand Kit — Owner / Admin / Designer may edit; all members may view.
     */
    public function canManageBrandKit(): bool
    {
        return match ($this) {
            self::Owner, self::Admin, self::Designer => true,
            default => false,
        };
    }

    public function canViewBrandKit(): bool
    {
        return true;
    }
}
