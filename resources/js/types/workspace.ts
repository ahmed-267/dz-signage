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
};

export type WorkspaceContext = {
    current: WorkspaceSummary | null;
    role: WorkspaceRoleValue | null;
    role_label: string | null;
    permissions: WorkspacePermissions | null;
    available: WorkspaceListItem[];
} | null;
