# DZ Signage — Architecture

This document distinguishes **current** (Phase 0 scaffold) from **planned** systems. Do not treat planned items as implemented.

## Current stack (Phase 0)

| Layer                     | Choice                                                                               |
| ------------------------- | ------------------------------------------------------------------------------------ |
| Backend                   | One Laravel 13 app (PHP ^8.3; 8.5+ recommended locally)                              |
| Frontend                  | React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui                           |
| Auth                      | Laravel Fortify (register, login, password reset, email verification, 2FA, passkeys) |
| Database                  | PostgreSQL 16 (Docker; host port `5433`)                                             |
| Cache / queues / sessions | Redis 7 via Predis (configured; Horizon **not** installed)                           |
| Surfaces                  | Public `/`, Customer `/app`, Super Admin `/admin`, Player `/player`, Settings        |
| Tests                     | Pest (PHP), Playwright (e2e smoke)                                                   |

### Current code reality

- Domain models beyond `User` are **not** implemented.
- Most `/app/*` and `/admin/*` product routes are **coming-soon placeholders**.
- Multi-tenancy (`workspace_id`) is **planned**, not enforced yet.
- Super Admin gate today: `users.is_admin` middleware (platform flag, not workspace role). Not mass-assignable.
- Email verification is enforced (`User` implements `MustVerifyEmail`; `/app` and `/admin` use `verified`).
- Horizon, Reverb, Cashier/Stripe, S3/R2, FFmpeg, AI providers: **not** installed.

## Backend

**One Laravel application** serves:

- Public website
- Customer application
- Super Admin
- Future APIs
- Future Player APIs

Do **not** create multiple Laravel backend projects.

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

## Multi-tenancy (planned)

- Shared infrastructure (one DB)
- Customer-owned records belong to `workspace_id`
- **Not** one database per customer
- Laravel authorization must enforce workspace isolation server-side

## Rendering architecture (critical decision — planned)

> Templates and Screen Designs will use **one shared JSON rendering schema**.

Future Template Builder, Screen Design Editor, Preview, and TV Player should use the same rendering engine/schema wherever practical.

**Do not build this engine in documentation-only steps.**

## Versioning (planned)

```
Template → Template Versions

Screen Design → Draft Version → Published Version
```

Editing a Screen Design must **not** automatically change what is live on a TV.

Workflow: Edit → Save Draft → Preview → Explicit Publish.

## Player reliability (planned)

- Cached assets and manifests
- Offline playback
- Local schedule execution
- Automatic reconnection
- Safe content updates

A temporary network outage must **not** automatically blank the screen.

## Scheduling principle (planned)

Schedules sync to the Player **in advance**. The Player changes content locally at the required time even if the network drops after sync.

Example: internet disconnects at 11:45; Breakfast → Lunch at 12:00 still occurs if assets/schedule were already synced.

## Surfaces map

| Surface      | Path prefix | Notes                            |
| ------------ | ----------- | -------------------------------- |
| Public       | `/`         | Marketing/welcome (expand later) |
| Customer app | `/app`      | Auth + verified                  |
| Settings     | `/settings` | Profile, security, appearance    |
| Super Admin  | `/admin`    | Auth + verified + `admin`        |
| Player       | `/player`   | No app chrome; device surface    |
