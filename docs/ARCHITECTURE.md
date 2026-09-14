# DZ Signage — Architecture

This document distinguishes **current** (Phase 1) from **planned** systems. Do not treat planned items as implemented.

## Current stack (Phase 1)

| Layer                     | Choice                                                                                                              |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Backend                   | One Laravel 13 app (PHP ^8.3; 8.5+ recommended locally)                                                             |
| Frontend                  | React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui                                                          |
| Auth                      | Laravel Fortify (register, login, password reset, email verification, 2FA, passkeys)                                |
| Multi-tenancy             | Shared DB; `workspaces` + `workspace_members`; `users.current_workspace_id`                                         |
| Database                  | PostgreSQL 16 (Docker; host port `5433`)                                                                            |
| Cache / queues / sessions | Redis 7 via Predis (configured; Horizon **not** installed)                                                          |
| Surfaces                  | Public `/`, Onboarding `/onboarding`, Customer `/app`, Super Admin `/admin`, Player `/player`, Settings `/settings` |
| Tests                     | Pest (PHP), Playwright (e2e smoke + workspace flows)                                                                |

### Current code reality

- **Implemented:** Users, Workspaces, memberships, invitations, current workspace, onboarding, team management, workspace settings, Super Admin workspace/user lists.
- Most product `/app/*` modules (Media, Templates, Screen Designs, Playlists, Schedules, Publishing, Screens, etc.) remain **coming-soon placeholders**.
- Super Admin gate: `users.is_admin` middleware (platform flag, **not** a workspace role). Not mass-assignable.
- Email verification is enforced (`MustVerifyEmail`; `/app` and `/admin` use `verified`).
- Customer `/app/*` also requires workspace membership (`workspace` middleware) or redirects to onboarding.
- Horizon, Reverb, Cashier/Stripe, S3/R2, FFmpeg, AI providers: **not** installed.

## Backend

**One Laravel application** serves:

- Public website
- Customer application
- Super Admin
- Future APIs
- Future Player APIs

Do **not** create multiple Laravel backend projects.

## Multi-tenancy (implemented)

- Shared infrastructure (one DB)
- Customer-owned records belong to `workspace_id` (workspace domain tables now; future product tables follow the same rule)
- **Not** one database per customer
- Laravel Policies (`WorkspacePolicy`) + membership checks enforce isolation server-side
- Current workspace is stored on `users.current_workspace_id` and validated on switch
- Workspace Owner ≠ platform Super Admin

## Customer / Admin frontend

- React + TypeScript + Inertia
- Reuse shared components (`resources/js/components`) where appropriate
- Match Figma for visual/UX (see `/docs/FIGMA_REFERENCE.md`)

## Player

Architecturally **separate** from Customer/Admin UI.

|             | Current                           | Planned                                                                                                                                 |
| ----------- | --------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Route       | `GET /player` → lightweight shell | Pairing, device auth, manifest/media download, local cache, playback, playlists/schedules, realtime updates, heartbeat, error reporting |
| Form factor | Minimal page                      | Lightweight PWA/appliance                                                                                                               |

Figma Make currently prototypes TV flows under `/tv/pair` and `/tv/player`. The **implementation** route remains `/player` unless deliberately changed and documented.

## Database

Primary database: **PostgreSQL**.

Local: Docker Compose service `postgres` → `127.0.0.1:5433`, database/user `dz_signage`.

## Redis

**Configured today** for cache, queues, and sessions (`.env.example`).

**Planned uses:** cache, queues, realtime infrastructure, background jobs.

Horizon / Reverb are **not** installed — do not claim they exist until added.

## Rendering architecture (critical decision — planned)

> Templates and Screen Designs will use **one shared JSON rendering schema**.

Future Template Builder, Screen Design Editor, Preview, and TV Player should use the same rendering engine/schema wherever practical.

## Versioning (planned)

```
Template → Template Versions

Screen Design → Draft Version → Published Version
```

Editing a Screen Design must **not** automatically change what is live on a TV.

## Player reliability / scheduling (planned)

See Phase 0 notes — unchanged. Offline player and advanced scheduling remain future work.

## Surfaces map

| Surface      | Path prefix    | Notes                              |
| ------------ | -------------- | ---------------------------------- |
| Public       | `/`            | Marketing/welcome                  |
| Onboarding   | `/onboarding`  | First workspace creation           |
| Invitations  | `/invitations` | Accept team invites                |
| Customer app | `/app`         | Auth + verified + workspace        |
| Settings     | `/settings`    | User profile/security/appearance   |
| Super Admin  | `/admin`       | Auth + verified + platform `admin` |
| Player       | `/player`      | No app chrome; device surface      |
