export type WorkspaceRoleValue =
    | 'owner'
    | 'admin'
    | 'designer'
    | 'content_manager'
    | 'location_manager'
    | 'viewer';

export type WorkspaceSummary = {
    id: number;
    name: string;
    slug: string;
    industry: string;
    industry_label: string;
    logo_url: string | null;
};

export type WorkspaceListItem = {
    id: number;
    name: string;
    logo_url: string | null;
    role: WorkspaceRoleValue;
    role_label: string;
};

export type WorkspacePermissions = {
    can_manage_workspace: boolean;
    can_manage_team: boolean;
    can_invite: boolean;
    can_update_roles: boolean;
    can_remove_members: boolean;
    can_leave: boolean;
    can_view_billing: boolean;
    can_manage_billing: boolean;
    can_view_analytics: boolean;
    can_view_media: boolean;
    can_manage_media: boolean;
    can_delete_media: boolean;
    can_view_templates: boolean;
    can_manage_templates: boolean;
    can_publish_templates: boolean;
    can_manage_screen_designs: boolean;
    can_manage_playlists: boolean;
    can_manage_schedules: boolean;
    can_publish_content: boolean;
    can_manage_screens: boolean;
    can_manage_locations: boolean;
    can_manage_brand_kit: boolean;
    can_use_ai: boolean;
};

export type WorkspaceContext = {
    current: WorkspaceSummary | null;
    role: WorkspaceRoleValue | null;
    role_label: string | null;
    permissions: WorkspacePermissions | null;
    available: WorkspaceListItem[];
} | null;
