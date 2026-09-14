# DZ Signage — Roles & Permissions (Planned)

All authorization must be enforced **server-side**. UI hiding alone is not security.

**Current (Phase 0):** `User` implements `MustVerifyEmail`. Platform Super Admin is `users.is_admin` (not fillable; set via factory/seeder/admin tooling only). Workspace roles and policies are not implemented yet.

## Workspace roles (planned)

### Owner

Full workspace control, including:

- Billing
- Team
- Workspace deletion
- Screens
- Publishing
- Designs
- All operational features

### Admin

Operational control excluding sensitive ownership-only actions (e.g. workspace deletion, ownership transfer, billing ownership where reserved).

### Designer

Primarily:

- Media
- Templates (where permitted)
- Screen Designs

### Content Manager

Primarily:

- Screen Designs
- Playlists
- Schedules
- Publishing

### Location Manager

Access limited to assigned Locations / Screens.

### Viewer

Read-only within the workspace scope granted.

## Super Admin (platform)

Super Admin is **platform-level**. It is **not** a normal Workspace role.

Future access includes:

- Workspaces
- Users
- Screens
- Billing
- Templates (platform catalogue)
- Platform operations
- Support
- System health

Today’s scaffold uses `users.is_admin` + middleware for `/admin/*`. Expand carefully; never conflate Super Admin with Workspace Owner.

## Isolation rules

1. Workspace members only see data for workspaces they belong to.
2. Location Managers are further scoped to assigned locations/screens.
3. Super Admin may cross workspaces for support/ops — still audited.
4. Player/device APIs authenticate devices, not end-user sessions (planned).
