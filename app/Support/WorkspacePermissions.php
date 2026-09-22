<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

final class WorkspacePermissions
{
    /**
     * @return array{
     *     can_manage_workspace: bool,
     *     can_manage_team: bool,
     *     can_invite: bool,
     *     can_update_roles: bool,
     *     can_remove_members: bool,
     *     can_leave: bool,
     *     can_view_billing: bool,
     *     can_manage_billing: bool,
     *     can_view_analytics: bool,
     *     can_view_media: bool,
     *     can_manage_media: bool,
     *     can_delete_media: bool,
     *     can_view_templates: bool,
     *     can_manage_templates: bool,
     *     can_publish_templates: bool,
     *     can_manage_screen_designs: bool,
     *     can_manage_playlists: bool,
     *     can_manage_schedules: bool,
     *     can_publish_content: bool,
     *     can_manage_screens: bool,
     *     can_manage_locations: bool,
     *     can_manage_brand_kit: bool,
     *     can_use_ai: bool
     * }
     */
    public static function for(?WorkspaceRole $role, User $user, Workspace $workspace): array
    {
        $canManageTeam = $role?->canManageTeam() ?? false;
        $isOwner = $role?->isOwner() ?? false;
        $ownerCount = $workspace->members()
            ->where('role', WorkspaceRole::Owner->value)
            ->count();

        $isSoleOwner = $isOwner && $ownerCount <= 1;

        return [
            'can_manage_workspace' => $role?->canManageWorkspace() ?? false,
            'can_manage_team' => $canManageTeam,
            'can_invite' => $canManageTeam,
            'can_update_roles' => $canManageTeam,
            'can_remove_members' => $canManageTeam,
            'can_leave' => $role !== null && ! $isSoleOwner,
            'can_view_billing' => $role?->canViewBilling() ?? false,
            'can_manage_billing' => $role?->canManageBilling() ?? false,
            'can_view_analytics' => $role?->canViewAnalytics() ?? false,
            'can_view_media' => $role?->canViewMedia() ?? false,
            'can_manage_media' => $role?->canManageMedia() ?? false,
            'can_delete_media' => $role?->canDeleteMedia() ?? false,
            'can_view_templates' => $role?->canViewTemplates() ?? false,
            'can_manage_templates' => $role?->canManageTemplates() ?? false,
            'can_publish_templates' => $role?->canPublishTemplates() ?? false,
            'can_manage_screen_designs' => $role?->canManageScreenDesigns() ?? false,
            'can_manage_playlists' => $role?->canManagePlaylists() ?? false,
            'can_manage_schedules' => $role?->canManageSchedules() ?? false,
            'can_publish_content' => $role?->canPublishContent() ?? false,
            'can_manage_screens' => $role?->canManageScreens() ?? false,
            'can_manage_locations' => $role?->canManageLocations() ?? false,
            'can_manage_brand_kit' => $role?->canManageBrandKit() ?? false,
            'can_use_ai' => ($role?->canManageMedia() ?? false)
                || ($role?->canManageScreenDesigns() ?? false),
        ];
    }
}
