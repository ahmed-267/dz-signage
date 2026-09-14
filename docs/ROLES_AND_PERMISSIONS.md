# DZ Signage — Roles & Permissions

All authorization must be enforced **server-side**. UI hiding alone is not security.

**Current (Phase 1):** Workspace roles + policies are implemented for team/workspace administration. Product-module permissions (Media, Designs, etc.) remain future work.

Platform Super Admin remains `users.is_admin` (not fillable).

## Workspace roles (implemented)

Enum: `App\Enums\WorkspaceRole`

### Owner

Can:

- Update workspace settings
- Invite/manage members
- Change non-owner roles
- Remove non-owner members
- Access workspace administration

Cannot (Phase 1):

- Transfer ownership (blocked; future work)
- Leave if sole Owner
- Be removed by Admin

### Admin

Can:

- Update workspace settings
- Invite/manage non-owner members

Cannot:

- Modify/remove Owner
- Promote anyone to Owner

### Designer / Content Manager / Location Manager

Basic workspace access (dashboard, read team list).

Future design/content/location-scoped permissions are documented for later phases only.

### Viewer

Read-only.

Cannot:

- Modify workspace
- Invite users
- Change roles
- Remove members

## Super Admin (platform)

Super Admin is **platform-level**. It is **not** a normal Workspace role.

Phase 1 Super Admin can view:

- Workspaces list/detail (read-only)
- Users list (read-only)

Do **not** grant `/admin/*` via Workspace Owner.

## Isolation rules

1. Workspace members only act within membership-validated current workspace.
2. Switching workspace requires membership.
3. Invitation acceptance requires email match + valid pending token.
4. Location Managers’ location scoping comes later when Locations exist.
