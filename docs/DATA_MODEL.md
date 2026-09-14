# DZ Signage — Data Model (Planned)

These entities are the **planned** domain model. Do **not** create all migrations during documentation-only work.

**Current (Phase 0):** `users` (+ Fortify/passkeys/2FA columns), `sessions`, `password_reset_tokens`, `passkeys`, `cache`/`jobs` infra tables. No workspace/signage domain tables yet.

## Planned entities

| Entity                | Purpose                                       |
| --------------------- | --------------------------------------------- |
| `User`                | Account identity (exists today)               |
| `Workspace`           | Tenant boundary                               |
| `WorkspaceMember`     | User ↔ workspace + role                       |
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

## High-level relationships

```
Workspace
├── WorkspaceMembers
├── Locations
├── MediaAssets
├── Templates
├── ScreenDesigns
├── Playlists
├── Schedules
├── Screens
└── Deployments

Template
└── TemplateVersions

ScreenDesign
└── ScreenDesignVersions

Playlist
└── PlaylistItems
      └── ScreenDesign

Schedule
└── Playlist

Location
└── Screens

Screen
├── ScreenDevice
├── ScreenHeartbeats
└── Deployments
```

Customer-owned rows should ultimately include `workspace_id` (see `/docs/ARCHITECTURE.md`).

## JSON configuration (JSONB)

Use PostgreSQL **JSONB** for editor/layout-heavy configuration where appropriate, for example:

- Template layout schema
- Screen Design layout schema
- Widget configuration
- Transitions
- Styling properties

Do **not** use JSONB for every business entity merely for convenience. Relational fields remain preferred for identity, FKs, status enums, billing, and query-heavy filters.

## Screen states (logical fields)

Screens expose three independent axes (see `/docs/PRD.md`):

- Operational: Active / Inactive
- Pairing: Connected / Disconnected
- Network: Online / Offline (derived largely from heartbeats)
