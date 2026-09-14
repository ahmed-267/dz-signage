# DZ Signage — Data Model

**Current (Phase 1):** foundation auth tables plus Workspace domain.

## Implemented entities

| Entity                | Purpose                                  |
| --------------------- | ---------------------------------------- |
| `User`                | Account identity; `current_workspace_id` |
| `Workspace`           | Tenant boundary                          |
| `WorkspaceMember`     | User ↔ workspace + role (unique pair)    |
| `WorkspaceInvitation` | Pending team invites (hashed token)      |

## Planned entities (not yet migrated)

| Entity                | Purpose                                       |
| --------------------- | --------------------------------------------- |
| `Location`            | Physical/site grouping for screens            |
| `MediaAsset`          | Reusable media library item                   |
| `Template`            | Reusable layout definition                    |
| `TemplateVersion`     | Immutable/versioned template schema           |
| `ScreenDesign`        | Finished design from a template               |
| `ScreenDesignVersion` | Draft vs published design snapshots           |
| `Playlist`            | Ordered collection of screen designs          |
| `PlaylistItem`        | Position + future duration/transition/active  |
| `Schedule`            | Named timing config selecting a playlist      |
| `Screen`              | Logical remote display                        |
| `ScreenDevice`        | Paired device credentials / hardware identity |
| `PairingSession`      | Short-lived pairing codes / sessions          |
| `ScreenHeartbeat`     | Online/health telemetry                       |
| `Deployment`          | Publishing assignment to screen(s)            |
| `Subscription`        | Workspace billing entitlement                 |
| `AuditLog`            | Security/ops audit trail                      |

## High-level relationships (Phase 1)

```
User
├── currentWorkspace (optional FK)
└── workspaceMemberships

Workspace
├── members (WorkspaceMember)
│     └── User + WorkspaceRole
└── invitations (WorkspaceInvitation)
```

Future product trees (Locations, Media, Templates, …) remain under Workspace as documented previously.

## Workspace fields

- `name`, `slug` (unique), `industry`, `country`, `timezone` (IANA), `logo_path` nullable

## Invitation security

- Store `token_hash` (SHA-256 of plain token); never store the raw token
- Track `expires_at`, `accepted_at`, `revoked_at`
- Accept requires matching authenticated email

## JSON configuration (JSONB) — planned

Use PostgreSQL **JSONB** for editor/layout-heavy configuration where appropriate. Do **not** use JSONB for every business entity merely for convenience.
