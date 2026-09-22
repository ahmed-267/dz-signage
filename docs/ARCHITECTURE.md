# RMSignage — Architecture

This document distinguishes **current** (Phase 11) from **planned** systems. Do not treat planned items as implemented.

## Current stack (Phase 11)

| Layer                     | Choice                                                                                                                                                       |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Backend                   | One Laravel 13 app (PHP ^8.3; 8.5+ recommended locally)                                                                                                      |
| Frontend                  | React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui                                                                                                   |
| Auth                      | Laravel Fortify (register, login, password reset, email verification, 2FA, passkeys)                                                                         |
| Multi-tenancy             | Shared DB; `workspaces` + `workspace_members`; `users.current_workspace_id`                                                                                  |
| Platform roles            | `users.platform_role` (`super_admin`, `platform_admin`); legacy `users.is_admin` synced for Super Admin only                                                 |
| Media                     | `media_assets` + local/public filesystem (`config/media.php`, disk-agnostic)                                                                                 |
| Templates                 | Platform-owned `templates` (+ optional `slug`) + `template_versions` + favourites; curated starter catalog; LayoutSchema v1 + `LayoutRenderer` card previews |
| Screen Designs            | Workspace-owned `screen_designs` + `screen_design_versions`; customer editor + Use Template; list cards render live schema                                   |
| Screens / Publishing      | `screens`, `screen_devices`, `pairing_sessions`, `deployments`; Player device auth + manifest polling                                                        |
| Database                  | PostgreSQL 16 (Docker; host port `5433`)                                                                                                                     |
| Cache / queues / sessions | Redis 7 via Predis (configured; Horizon **not** installed)                                                                                                   |
| Surfaces                  | Public `/`, Onboarding `/onboarding`, Customer `/app`, Admin `/admin`, Player `/player`, Settings hub `/app/settings` (+ legacy `/settings/*` redirects)     |
| Tests                     | Pest (PHP), Playwright (e2e smoke + workspace + media + templates + screen-designs + player/publishing + screen presence flows)                              |

### Current code reality

- **Implemented:** Users, Workspaces, memberships, invitations, current workspace, onboarding, team management, workspace settings, Media Library (incl. authorised delete + confirmation + file cleanup; delete blocked when referenced by Screen Designs), platform Templates (admin builder/versioning; customer browse/favourite/Use Template), workspace Screen Designs (list, blank create, editor, draft/publish versions), Screens + pairing + device auth, first real Publish to Screen (direct Screen Design → Screen deployments), standalone Player (manifest/media + shared `LayoutRenderer`, poll reconciliation), dedicated platform Admin shell (incl. read-only `/admin/screens`).
- **Implemented (Phase 6):** dedicated Player heartbeats (`POST /player/api/heartbeat` + `screen_heartbeats`), `ScreenPresence` health / content sync / orientation checks, Screens library search + filters + bulk Activate/Deactivate/Publish, `/admin/screens/{screen}` support view, `/admin/screen-health`.
- **Implemented (Phase 7):** workspace Playlists (`playlists` / `playlist_versions` / `playlist_items`), library + editor under `/app/playlists`, ordered items with duration / transition / transition speed / active flag, draft vs published playlist versions, playlist Deployments and Player playlist playback.
- **Implemented (Phase 8):** workspace Schedules (`schedules` / `schedule_screen`) pinning a published `PlaylistVersion` to Screens over a wall-clock window. `App\Support\Schedules\ScheduleEvaluator` owns all timing (IANA timezone, ISO days, date range, overnight windows, inclusive start / exclusive end, priority precedence **1–10**, default **5**, **10 = highest**); `DetectScheduleConflicts` owns overlap warnings (including create draft preview); `App\Support\Screens\ScreenContentResolver` owns content precedence (matching Schedule → active Deployment → none) for the player manifest, player media access, and Screen detail. `/app/schedules` carries the library (list + week calendar), the shared create/edit form, and a JSON-backed preview dialog; Screen detail surfaces Current / Next schedule from the same resolver. No timing rule is re-implemented in React — the form only mirrors the overnight hint for display.
- **Implemented (Phase 9):** `/app/publishing` operational centre (Live / Scheduled / History + Republish); unified `PublishContentToScreens`; `/admin/publishing-jobs` read-only Deployment list. Heartbeat acknowledgement drives Live/Updating/Waiting — Deployments stay Active so offline Players reconcile on reconnect.
- **Implemented (Phase 10):** Player offline reliability — `GET /player/api/offline-package`, Dexie package store, Cache Storage for required Media, `/player-sw.js` shell caching (Player-only), local Schedule window resolve from server-precomputed horizon, atomic package activation, reconnect reconciliation, heartbeat offline metadata on TV detail / Admin TV Health.
- **Implemented (Phase 11):** Widgets as LayoutSchema elements (`widgetType` + `config`) via central registry; shared `WidgetRenderer` in `LayoutRenderer`; Clock/Countdown/Weather/News/Calendar/Alert/Info Card/Embed; backend widget data services with SSRF guards + caching; offline `widgetData` in the Player package; Template Builder + Screen Design Editor integration (no Widgets sidebar page).
- **Implemented (Phase 12):** Super Admin Expansion — full `/admin/*` ops portal with real platform metrics (no fake MRR). Workspaces/Users/TVs list+detail; TV Health + Publishing Jobs; System Health (cached lightweight checks); operational Errors (`platform_errors`); Support Requests (+ customer `/app/help`); immutable Audit Log; Feature Flags; Platform Settings. Authorization via `EnsureUserIsAdmin` + `PlatformPermissions`. Billing inspect surfaces completed in Phase 13.
- **Implemented (Phase 13.5):** Public marketing landing page at `GET /` — see “Public website” below.
- **Implemented (Phase 13):** Workspace-level Stripe billing via Laravel Cashier. Plan catalog is DB-backed `billing_plans` (seeded from `config/billing_plans.php`) via `BillingPlanCatalog`: Starter / Business / Enterprise (**no Pro**). Super Admin plan management at `/admin/subscriptions/plans` (pricing, limits, features, Stripe Price sync, optional subscriber migration). Screen licences = subscription quantity; used = Connected (non-revoked) Screens. Checkout + Customer Portal; verified webhooks; `BillingEntitlement` enforces pairing/publish/player when `billing.enforce` is true. Admin `/admin/subscriptions` and `/admin/invoices` use real Cashier/`billing_invoices` data; Super Admin may change Starter/Business via Cashier when Stripe is configured. When Stripe Price IDs are unset, customer/marketing pricing falls back to plan catalog amounts.
- **Implemented (Phase 14):** Premium AI pipeline — creative brief → layout archetype → structured Design schema → `DesignQualityValidator`; Brand Kit–aware; image prompt enhancement (no baked-in text); text modes/variants; concept selection for Create with AI. `AiContentService` + replaceable providers (`fake` / `openai_compatible`); Media / Editor / Create Design entry points; `ai_generations` history; feature flag `ai_content_generation`. AI Video is **not** implemented. Player has no AI runtime.
- **Implemented (Phase 15):** Customer Analytics (`/app/analytics`) and Dashboard visualisations from real `playback_events` / `screen_daily_stats` / heartbeats / Deployments (Recharts shared chart system); Player playback telemetry; retention + daily aggregation; production health probes (`/health`, `/ready`); security headers; production runbooks.
- **Post-Phase 15 completeness:** Workspace **Brand Kit**; Workspace **Locations**; final **plan catalog**; `dz:seed-demo`; theme toggle; `SortableTableHeader`. UX corrections: customer-facing **TV/TVs** (nav **Paired TVs**; internal `Screen` model unchanged); customer content renamed to **Screens** (internal `ScreenDesign` unchanged); Settings hub with Billing tab; Playlist horizontal cards; Schedule create stepper; AI signage agent (Media/Template selection + Playlist/Schedule proposals); richer marketing landing; LiveMedia provider adapters (YouTube/Vimeo/HLS/DASH/MP4 + Teams/Zoom/Webex diagnostics); bulk delete on Screens/Playlists/Schedules.
- `/admin/*` gated by platform staff (`users.platform_role` / `isPlatformStaff()`). Workspace Admin ≠ Admin (platform).
- Email verification is enforced (`MustVerifyEmail`; `/app` and `/admin` use `verified`).
- Customer `/app/*` also requires workspace membership (`workspace` middleware) or redirects to onboarding.
- Horizon, Reverb, S3/R2, FFmpeg: **not** installed. Laravel Cashier + Stripe are installed (Phase 13). AI uses HTTP providers configured in `config/ai.php` (no browser-side AI SDK).

## Backend

**One Laravel application** serves:

- Public website
- Customer application
- Platform Admin shell (Super Admin / Admin)
- Player page + Player APIs (`/player`, `/player/api/*`)

Do **not** create multiple Laravel backend projects.

## Multi-tenancy (implemented)

- Shared infrastructure (one DB)
- Customer-owned records belong to `workspace_id` (workspace, media, screen designs, screens, deployments; Templates are platform-owned with `workspace_id` null)
- **Not** one database per customer
- Laravel Policies (`WorkspacePolicy`, `MediaAssetPolicy`, `TemplatePolicy`, `ScreenDesignPolicy`, `ScreenPolicy`, `DeploymentPolicy`) + membership checks enforce isolation server-side
- Current workspace is stored on `users.current_workspace_id` and validated on switch
- Workspace Owner / Workspace Admin ≠ platform Super Admin / Admin

## Media Library (implemented)

- Types: image, video, text, logo, document, link (`App\Enums\MediaType`)
- Storage: Laravel filesystem; default `public` disk under `workspaces/{id}/media/…`
- Replace keeps stable MediaAsset IDs for Screen Design references (`props.mediaAssetId`)
- Duplicate physically copies files (independent lifecycle)
- **Delete:** authorised roles only (`can_delete_media`); UI confirmation required; blocked when referenced by a Screen Design (covers deployed content); file-backed types remove the stored file after DB delete; Text/Link are DB-only (no file)
- Video duration/dimensions nullable until a later FFmpeg/processing phase
- AI content generation is implemented (Phase 14) — Media / Editor / Create Design entry points; see “AI content generation” below.
- Future: switch `MEDIA_DISK` to S3/R2 without rewriting the domain

## Templates (implemented — platform-owned)

Templates are **global / platform-owned** (`workspace_id` null). There are no workspace-owned Templates and no customer “My Templates”.

| Surface            | Who                         | Capabilities                                                       |
| ------------------ | --------------------------- | ------------------------------------------------------------------ |
| `/admin/templates` | Super Admin, Platform Admin | Create, edit (Template Builder), publish, archive, delete, version |
| `/app/templates`   | Workspace members           | Browse, search, filter, preview, favourite, **Use Template**       |

- Customer routes never create/edit/publish/delete Templates; no customer Template Builder.
- **Use Template** (`POST /app/templates/{id}/use`) is available when the member can manage Screen Designs (`use_template_available`). It deep-copies the published schema into a new Screen Design (`source_template_id` / `source_template_version_id`).
- Customers never modify master Templates.
- Template version independence: later Template v2 does **not** mutate existing Screen Designs.
- Shared **LayoutSchema v1** + `LayoutRenderer` (`mediaMap` / `props.mediaAssetId`) + drag/resize/undo foundations (admin Template Builder, customer Screen Design Editor, and Player).

## Screen Designs (implemented — workspace-owned)

Finished workspace signage under `/app/screen-designs`. Not platform Templates.

| Action                                   | Route                                                       |
| ---------------------------------------- | ----------------------------------------------------------- |
| List / create blank                      | `GET /app/screen-designs`, `POST /app/screen-designs/blank` |
| Editor / preview                         | `GET /app/screen-designs/{id}/edit`, `GET …/preview`        |
| Save draft / rename / duplicate / delete | `PATCH`, `POST …/rename`, `POST …/duplicate`, `DELETE`      |
| Publish Design                           | `POST /app/screen-designs/{id}/publish`                     |
| Publish to Screens                       | `POST /app/screen-designs/{id}/publish-to-screens`          |
| Use Template                             | `POST /app/templates/{id}/use`                              |

- Blank canvases: Landscape **1920×1080**, Portrait **1080×1920**.
- Status: `draft` \| `published` \| `archived`. Draft versions overwrite until publish; saving after publish creates a new version.
- **Publish Design** sets `published_version_id` and status `published`. It finalises a version. It does **not** send content to a TV by itself.
- **Publish to Screen** deploys only a published `ScreenDesignVersion` via `Deployment` records. Later edits do not change the live deployment until republish.
- Delete Screen Design is **blocked** while actively deployed. Media deletion is blocked when a Screen Design schema references that asset.

## Screens, pairing & publishing (implemented — Phase 5)

| Concern         | Details                                                                                                                                                                                                                                                 |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Screen          | Workspace-owned: `name`, nullable `orientation`, `operational_status` active/inactive, nullable `location_id` FK → Locations                                                                                                                            |
| ScreenDevice    | `device_identifier`, `device_token_hash` (HMAC-SHA256 with app key), `paired_at`, `revoked_at`, `last_seen_at`, heartbeat telemetry (`player_version`, viewport, `reported_orientation`, `playback_state`, `last_error_code`, `reported_deployment_id`) |
| ScreenHeartbeat | Append-only Player check-in history (`recorded_at`, playback state, error code, viewport, orientation, deployment); pruned by `screens:prune-heartbeats`                                                                                                |
| PairingSession  | ULID `public_id`, `code_hash`, `expires_at` (10 min), `claimed_at`, `pending_device_token_ciphertext` (one-time encrypted token)                                                                                                                        |
| Deployment      | Direct publish assignment: `content_type` screen_design\|playlist, pinned version FKs, status `pending`/`active`/`failed`/`superseded`/`revoked`, `deployed_by`, `deployed_at`, `superseded_at`. Create only via `PublishContentToScreens`.             |

**State axes (independent):**

| Axis        | Values                   | Source                                                                                         |
| ----------- | ------------------------ | ---------------------------------------------------------------------------------------------- |
| Operational | Active / Inactive        | Stored                                                                                         |
| Pairing     | Connected / Disconnected | Derived from active (non-revoked) device                                                       |
| Network     | Online / Offline         | `last_seen_at` within `screens.online_threshold_seconds` (90s); refreshed by Player heartbeats |

Health (`healthy` / `attention` / `offline`), content sync (`up_to_date` / `out_of_sync` / `unknown`), and orientation mismatch are derived views over those axes. `App\Support\Screens\ScreenPresence` owns every threshold — do not re-implement them in controllers or React.

**Pairing flow:**

1. Player `POST /player/api/pairing-sessions` → code `DZ-XXXX` + QR URL `/app/screens/pair/{public_id}`
2. Manual claim `POST /app/screens/pair` or QR claim `GET/POST /app/screens/pair/{publicId}`
3. Single-use; expired rejected; rate limited
4. Device token never shown as pairing code; issued once via encrypted pending field when player polls the session

**Publish paths:**

- Publishing centre: `GET/POST /app/publishing`, republish `POST /app/publishing/{deployment}/republish`
- Design-first: `POST /app/screen-designs/{id}/publish-to-screens`
- Playlist-first: `POST /app/playlists/{id}/publish-to-screens`
- Screen-first: `POST /app/screens/{id}/publish`

All direct publishes go through `App\Actions\Deployments\PublishContentToScreens`. Offline Connected Screens still get an Active Deployment; acknowledgement is heartbeat `reported_deployment_id` vs active Deployment (Live / Updating / Waiting on `/app/publishing`).

## Public website (implemented — Phase 13.5)

`GET /` is a real marketing landing page, not a placeholder.

| Concern     | Details                                                                                                                                                                                                                                                                                                                                                                              |
| ----------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Route       | `GET /` → `App\Http\Controllers\LandingController` → Inertia `marketing/home` (named `home`)                                                                                                                                                                                                                                                                                         |
| Props       | `canRegister`, plan catalog / pricing (from `BillingPlanCatalog` / `BillingEntitlement`), `seo` — no secrets, no Stripe Price IDs, no Workspace data                                                                                                                                                                                                                                 |
| Shell       | `resources/js/layouts/marketing-layout.tsx` — public-only chrome (glass navbar + footer). It does **not** reuse `AppSidebar` or `AdminSidebar`                                                                                                                                                                                                                                       |
| Sections    | `resources/js/components/marketing/*`, anchored as `#product` `#features` `#templates` `#industries` `#ai` `#pricing` `#faq`. Home composes hero → workflow (incl. Reach every TV) → feature-showcase → templates → industries → ai → pricing → faq → final-cta. Standalone pairing/fleet/schedule/offline/widgets/publishing sections were folded into workflow + feature-showcase. |
| Visuals     | CSS/div product mockups via `ProductFrame` / `ScreenFrame`. No stock photography, no screenshots that can go stale                                                                                                                                                                                                                                                                   |
| Motion      | `resources/js/lib/marketing-motion.ts` is the only place that reads `prefers-reduced-motion` for this page                                                                                                                                                                                                                                                                           |
| Pricing     | Plan catalog via `BillingPlanCatalog` (Starter / Business / Enterprise). Stripe amounts when configured; otherwise catalog display. CTAs point at `/register`                                                                                                                                                                                                                        |
| Third-party | Selected Watermelon components restyled onto DZ tokens (`continuous-tabs`, `card-split-accordian`). Pricing uses the multi-plan catalog (not single per-Screen-only pricing)                                                                                                                                                                                                         |

The landing page must stay truthful: it describes only shipped capability, and carries no testimonials, usage metrics, ratings, or trial claims. Phase 14 may mention AI text/image/design assistance — never AI video until implemented. Privacy and Terms links are omitted until those pages exist — no `href="#"` placeholders.

## AI content generation (implemented — Phase 14)

| Concern          | Details                                                                                                                                                                                                                                                                              |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Orchestration    | `App\Support\Ai\AiContentService` — controllers never call providers directly                                                                                                                                                                                                        |
| Quality pipeline | User intent → `CreativeBriefBuilder` → `DesignArchetypes` (Hero Product · Split · Editorial · Information Board · Event · Menu · Full-Bleed · Welcome Lobby) → provider copy fill → `AiDesignSchemaBuilder` → `TextFit` + `DesignQualityValidator` refine/gate → draft Screen Design |
| Providers        | `AiTextProvider` / `AiImageProvider`; `fake` and `openai_compatible`. Defaults prefer `gpt-4o` / configurable image model. `AiVideoProvider` reserved (disabled)                                                                                                                     |
| Brand Kit        | Brief + schema builder use colours, fonts, logo when `use_brand_kit` is on                                                                                                                                                                                                           |
| Images           | `ImagePromptEnhancer` adds composition, mood, and hard no-text/logo/watermark instructions; variants + regenerate with refinement                                                                                                                                                    |
| Text             | Signage modes (headline, CTA, …), length default `short`, multi-option variants                                                                                                                                                                                                      |
| Playlist runtime | Canonical `PlaylistRuntimeCalculator`: active item = `duration_seconds × loop_count`; Schedule shows published + draft totals when they differ                                                                                                                                       |
| Embeds           | `EmbedUrlValidator` + shared `EmbedWidget` (YouTube/Vimeo/HLS/MP4/website/blocked); SSRF via `SafeRemoteUrl`; CSP allows intentional HTTPS frame/media sources                                                                                                                       |
| Pairing UX       | Browser Player at `/player`; Paired TVs + Help guide QR/PIN steps; no native TV OS apps claimed                                                                                                                                                                                      |
| Team UX          | `/app/team` Figma-style table + Roles & Permissions cards; seat limit from `BillingEntitlement`                                                                                                                                                                                      |
| Config           | `config/ai.php` + env (`AI_ENABLED`, `AI_PROVIDER`, `AI_OPENAI_*`, `AI_QUALITY_MODE`) — keys server-side only                                                                                                                                                                        |
| Feature flag     | `ai_content_generation` (plus `AiAvailability`); when `billing.enforce`, plan `ai_*` features soft-gate capabilities                                                                                                                                                                 |
| History          | `ai_generations` (Workspace-scoped); concepts accepted via `POST /app/ai/design/accept`; pruned by `ai:prune-generations`                                                                                                                                                            |
| Agent            | `AiSignageAgent` + Media/Template relevance — propose/confirm Designs, Draft Playlists, Draft Schedules (never auto-activate/deploy without explicit flags)                                                                                                                          |
| Designs          | Concept cards (up to 3) → accept → draft `ScreenDesign` (never auto-publish); prefers existing Media before generating imagery                                                                                                                                                       |
| Routes           | `/app/ai/*` (throttled) including `agent/propose` + `agent/confirm`                                                                                                                                                                                                                  |
| Permissions      | `can_use_ai` = manage media **or** manage screen designs; agent playlist/schedule needs matching manage permissions; Viewer denied                                                                                                                                                   |
| Product labels   | Customer UI: content = **Screens** (`ScreenDesign` model / `/app/screen-designs`); devices = **TV/TVs** / **Paired TVs** (`Screen` model / `/app/screens`). `ProductLabels` is the source of truth.                                                                                  |
| Player           | No AI calls — only finished Media / Designs / Playlists / Schedules                                                                                                                                                                                                                  |

## Application shells (three surfaces)

Customer, Platform Admin, and Player are **separate shells** — do not reuse customer `AppSidebar` inside `/admin`.

| Surface        | Path      | Shell                                                                                                                                                                           | Who                 |
| -------------- | --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------- |
| Customer app   | `/app`    | `AppSidebar` — Create Design CTA + Display nav filtered by workspace permissions; Settings hub + Analytics gated; **Admin Portal** link only for platform staff                 | Workspace members   |
| Platform Admin | `/admin`  | Dedicated `AdminSidebar` — amber **RMSignage Admin** branding, **Back to App**, Figma-aligned nav groups (Overview, Customers, Billing, Content, Operations, Support, Platform) | Super Admin / Admin |
| Player         | `/player` | No app/admin chrome                                                                                                                                                             | Paired devices      |

### Admin nav (Figma-aligned)

- **Overview** — real metrics only: workspaces, users, templates, published_templates (no fake MRR)
- **Customers** — Businesses (UI; route remains `/admin/workspaces`), Users, TVs (read-only platform Screens list + per-TV support view). Super Admin may soft-delete a Business after typing its name. Plans stay on the central `BillingPlan` catalog under Billing. Admin Templates render the published (or latest) LayoutSchema through `LayoutRenderer`. TV Health shows the configured heartbeat window from `ScreenPresence` (`online_threshold_seconds`), last heartbeat, age, and content sync.
- **Billing** — Plans (`/admin/subscriptions/plans`), Subscriptions, Invoices (DB `billing_plans` + Cashier + mirrored `billing_invoices`; Super Admin edits catalog pricing/limits/features; may change Starter/Business when Stripe is configured)
- **Content** — Templates (implemented CRUD + Builder)
- **Operations** — TV Health (real heartbeat data); Publishing Jobs; System Health; Errors
- **Support** — Support Requests; Audit Log
- **Platform** — Feature Flags; Settings

### Customer / Admin frontend tech

- React + TypeScript + Inertia
- Reuse shared components (`resources/js/components`) where appropriate
- Match Figma for visual/UX (see `/docs/FIGMA_REFERENCE.md`)

## Player (implemented — Phase 5)

Architecturally **separate** from Customer/Admin UI.

|           | Current                                                                                                                                                                                 |
| --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Route     | `GET /player` — standalone shell (pairing → playback)                                                                                                                                   |
| Auth      | Bearer token, `X-Device-Token`, or cookie `dz_player_device_token` (for `<img>` / `<video>` media)                                                                                      |
| APIs      | `POST/GET /player/api/pairing-sessions…`; authenticated `POST /player/api/heartbeat`, `GET /player/api/manifest`, `/manifest/check`, `/media/{id}` (scoped to active deployment schema) |
| Rendering | Shared `LayoutRenderer` + LayoutSchema v1                                                                                                                                               |
| Sync      | Polling reconciliation (not Reverb)                                                                                                                                                     |
| UI states | pairing, loading, ready, no_content, inactive, error                                                                                                                                    |

The Player also posts an authenticated heartbeat (`POST /player/api/heartbeat`) on the interval returned by the server. Heartbeat failures are silent — the screen keeps rendering its last known content.

Playback telemetry (Phase 15) is separate: `POST /player/api/playback-events` accepts small batched, idempotent events (`content_started`, `playlist_item_started`, `deployment_applied`, `player_error`, …). Do not inflate the heartbeat payload.

Playlists (Phase 7) and Schedules (Phase 8) reach the Player through the same manifest: `contentSource` says whether a Schedule or a Deployment won, and `/manifest/check` returns a version label that also changes at a schedule window changeover, so the existing poll picks the handover up without a new code path.

**Complete:** Offline/PWA Player (Phase 10), Widgets (Phase 11), AI generation (Phase 14 — server-side only; Player has no AI runtime).

Figma Make prototypes TV flows under `/tv/pair` and `/tv/player`. The **implementation** route remains `/player`.

## Analytics (implemented — Phase 15)

| Concern   | Details                                                                                                                                  |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| UI        | `/app/dashboard` (ops KPIs + charts) and `/app/analytics` (deep history) — Workspace timezone ranges, Screen filter                      |
| Charts    | Shared Recharts components under `resources/js/components/charts/*` (availability, playback, rankings, status donuts); light/dark tokens |
| Sources   | Heartbeats / presence, `playback_events`, `screen_daily_stats`, Deployments (`AnalyticsQuery`)                                           |
| Series    | Availability %, playback, errors, publishing trend (superseded ≠ failed)                                                                 |
| Ingest    | `RecordPlaybackEvents` + `PlaybackEventController`                                                                                       |
| Aggregate | `analytics:aggregate-daily` → `screen_daily_stats`                                                                                       |
| Retention | `analytics:prune` + `config/analytics.php`                                                                                               |
| Isolation | Screen IDs + content FKs validated to current Workspace                                                                                  |
| Honesty   | No fake metrics; empty chart states until real telemetry exists                                                                          |

## Database

Primary database: **PostgreSQL**.

Local: Docker Compose service `postgres` → `127.0.0.1:5433`, database/user `dz_signage`.

## Redis

**Configured today** for cache, queues, and sessions (`.env.example`).

**Planned uses:** cache, queues, realtime infrastructure, background jobs.

Horizon / Reverb are **not** installed — do not claim they exist until added.

## Rendering architecture (critical decision)

> Templates, Screen Designs, and the Player use **one shared JSON rendering schema** (LayoutSchema v1).

**Implemented today:** PHP (`app/Support/Rendering/LayoutSchema`, validator) + TS (`resources/js/types/layout-schema.ts`) + React `LayoutRenderer` for platform Template Builder / Preview, customer Template preview, Screen Design Editor, and Player (`mediaMap` resolves `props.mediaAssetId` / canvas background).

Do not invent a second layout format.

## Versioning

```
Template → Template Versions          (implemented; platform staff)
   published_version_id → live snapshot

Screen Design → Draft Version → Published Version   (implemented; workspace)
   Use Template copies published Template schema into independent Screen Design
   Publish Design finalises a version — does not deploy to TVs by itself
   Publish to Screen deploys published_version only (Deployment pins screen_design_version_id)
```

Editing a Screen Design must **not** automatically change what is live on a TV until republish. Publishing a new Template version must **not** mutate existing Screen Designs.

## Player reliability / scheduling (planned)

Offline player (Phase 10) and playlists/schedules (Phases 7–8) remain future work. Presence uses Phase 6 heartbeats plus poll reconciliation — not Reverb.

## Surfaces map

| Surface        | Path prefix     | Notes                                                                        |
| -------------- | --------------- | ---------------------------------------------------------------------------- |
| Public         | `/`             | Marketing landing page (`LandingController`)                                 |
| Onboarding     | `/onboarding`   | First workspace creation                                                     |
| Invitations    | `/invitations`  | Accept team invites                                                          |
| Customer app   | `/app`          | Auth + verified + workspace; customer `AppSidebar`                           |
| Settings       | `/app/settings` | Hub: General / Workspace / Billing / Security (`/settings/*` redirects here) |
| Platform Admin | `/admin`        | Auth + verified + platform staff; dedicated `AdminSidebar`                   |
| Player         | `/player`       | No app chrome; device surface + `/player/api/*`                              |
