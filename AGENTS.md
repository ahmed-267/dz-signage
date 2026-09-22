# RMSignage Agent Rules

Mandatory context for Cursor/AI work on this repository.

## Before Significant Work

1. Read the relevant `/docs` files (`PRD`, `ARCHITECTURE`, `DATA_MODEL`, `ROLES_AND_PERMISSIONS`, `ROADMAP`, `DEVELOPMENT`, `FIGMA_REFERENCE`).
2. Check current implementation before adding new abstractions.
3. Do not implement future phases unless requested.

## Product Terminology

Always preserve the content pipeline:

```
Media → Templates → Screens → Playlists → Schedules → Publishing → Paired TVs
```

Customer-facing names:

- **Screen** = workspace content (internal model: `ScreenDesign`)
- **TV / Paired TVs** = physical paired playback devices (internal model: `Screen`)

Do not blur these concepts.

- Do **not** confuse **Templates** (platform-owned reusable layouts) with **Screens** (workspace-owned finished signage / `ScreenDesign`).
- Do **not** confuse **Publish Screen** (finalise a Screen Design version) with **Publish to TV** / **Publishing** (deployment via `Deployment`).
- Do **not** confuse **Schedules** (timing) with **Publishing** (deployment to TVs).
- Do **not** implement future roadmap phases unless explicitly requested.
- Do **not** document or build workspace-owned / “My Templates” customer Template CRUD.
- Do **not** rename physical TVs back to “Screens” in customer UI.

## Figma

Before significant UI implementation:

1. Read `/docs/FIGMA_REFERENCE.md`.
2. Use Figma MCP to inspect the relevant screen/frame (Make file key `LV2LWOdsLyhfoG8sXFWlRV`).
3. Treat Figma as the UI/UX source of truth.
4. Treat `/docs` as architecture/business-logic source of truth.
5. Do not rely on memory when Figma is accessible.
6. Reuse existing React/UI components where possible.
7. Preserve responsiveness.
8. Preserve accessibility.
9. Preserve light/dark/system modes.

Main design: https://www.figma.com/make/LV2LWOdsLyhfoG8sXFWlRV/Follow-Markdown-File?t=HD09hia6xnKtdHpb-0&preview-route=%2Fapp%2Fmedia

## Architecture

- One Laravel backend.
- PostgreSQL primary database.
- Shared multi-tenant architecture via `workspaces` / `workspace_members` / `users.current_workspace_id`.
- Enforce Workspace isolation (server-side policies + middleware).
- Platform roles (`users.platform_role`: `super_admin`, `platform_admin`) are separate from Workspace roles. Workspace Admin ≠ Admin (platform). Legacy `users.is_admin` syncs for Super Admin only.
- Three shells: customer `/app` (`AppSidebar` — nav filtered by workspace permissions), platform `/admin` (dedicated `AdminSidebar` — amber **RMSignage Admin**, **Back to App**), device `/player` (no app/admin chrome). Do not reuse customer sidebar in `/admin`.
- Templates are **platform-owned** (global). Managed under `/admin/templates` by platform staff only. Customer `/app/templates` is browse/preview/favourite/Use Template only — no Create/My Templates/customer builder.
- Starter Templates are real published LayoutSchema v1 records (`StarterTemplateCatalog` / `PlatformTemplatesSeeder`, stable `slug`). Template and Screen Design **cards** render schemas with shared `LayoutRenderer` in preview mode (contain-fit, no editor chrome). Do not use static screenshots as the source of truth.
- **Use Template** (`POST /app/templates/{id}/use`) copies the published Template schema into an independent workspace **Screen Design**. Customers never modify master Templates; later Template versions must not mutate existing Screen Designs.
- E2E/test fixtures should use an `E2E ` name prefix (cleaned by `LocalDevSeeder`) so local libraries stay usable.
- Screen Designs live under `/app/screen-designs` (`screen_designs` / `screen_design_versions`). Blank Landscape 1920×1080 / Portrait 1080×1920, or Use Template. Editor reuses Template Builder foundations (drag/resize/undo) + shared LayoutSchema v1 + `LayoutRenderer` (`mediaMap` / `props.mediaAssetId`).
- **Publish Design** finalises a version. It does **not** send content to a TV by itself.
- **Publish to Screen** deploys only a published `ScreenDesignVersion` (`POST …/publish-to-screens` or `POST /app/screens/{id}/publish`). Later edits do not change the live deployment until republish.
- Screens / pairing / Player device auth are implemented (Phase 5 COMPLETE). Player auth: Bearer, `X-Device-Token`, or cookie `dz_player_device_token`. Manifest + media via `/player/api/*`; polling reconciliation (not Reverb).
- Presence is heartbeat-driven (Phase 6): `POST /player/api/heartbeat` + `screen_heartbeats`, thresholds in `config/screens.php`. `App\Support\Screens\ScreenPresence` is the only place that decides Pairing / Network / Health / Content sync — never duplicate those thresholds in controllers or React. Screen Health surfaces must use real heartbeat data (no synthetic CPU / IP / uptime).
- Template Builder = platform tool; Screen Design Editor = customer tool.
- Designer / Content Manager / Owner / Admin manage Screen Designs; Location Manager / Viewer are read-only on designs.
- Designer can view Screens but **cannot** publish to TV (`canPublishContent` is Owner/Admin/Content Manager). `canManageScreens` is Owner/Admin/Location Manager.
- Player remains architecturally separate from Customer/Admin UI (`/player`).
- Do not claim Horizon/Reverb exist unless installed. Cashier/Stripe are installed (Phase 13).
- Phase 5 (Player + Pairing + First Real Publishing), Phase 6 (Heartbeats + Screen States + Screen Health), Phase 7 (Playlists), Phase 8 (Schedules), Phase 9 (Publishing), Phase 10 (Offline Player), Phase 11 (Widgets), Phase 12 (Super Admin Expansion), Phase 13 (Billing & Subscriptions), Phase 13.5 (Production Marketing Landing Page), Phase 14 (AI Content Generation) and Phase 15 (Analytics + Production Hardening) are **COMPLETE**. Do not invent post-launch product phases unless requested.
- Public `/` is the marketing landing (`LandingController` → `marketing/home`) with a dedicated marketing shell — never reuse App/Admin/Player chrome there. Landing may mention AI only for shipped Phase 14 capabilities (no AI video).
- **AI (Phase 14):** Server-side `AiContentService` + replaceable providers (`config/ai.php`). Keys never reach the browser. Generated images become normal `MediaAsset`s; designs become normal Screen Designs. AI never auto-publishes. Player has no AI dependency. Feature flag `ai_content_generation`.
- **Analytics (Phase 15):** `/app/analytics` from real heartbeats, `playback_events`, `screen_daily_stats`, Deployments. Player telemetry: `POST /player/api/playback-events` (batched; keep heartbeat light). Availability = connectivity. Ops: `docs/PRODUCTION.md`, `docs/RELEASE_AND_LAUNCH.md`. Probes: `/health`, `/ready`.
- Admin portal (`/admin/*`): dedicated `AdminSidebar`; real platform metrics; Workspaces/Users/Screens; Screen Health; Publishing Jobs; System Health; Errors; Support; Audit Log; Feature Flags; Platform Settings; Subscriptions/Invoices (Stripe/Cashier). Super Admin vs Admin (platform) via `PlatformPermissions` — never grant Admin via Workspace roles.
- Publishing centre: `/app/publishing` (Live / Scheduled / History). Direct Design/Playlist Deployments are created only via `PublishContentToScreens`. Live/Updating/Waiting acknowledgement is derived from heartbeats — do not invent a second sync model.
- Offline Player: `/player/api/offline-package` + Dexie + Cache Storage + `/player-sw.js`. Local resolve uses server-precomputed Schedule windows (same inclusive-start/exclusive-end rules). Do not cache `/app` or `/admin`.
- Widgets: LayoutSchema `type: "widget"` + `props.widgetType` / `config`; registry in `resources/js/lib/widgets/`; render only via `WidgetRenderer` inside `LayoutRenderer`. External data via `/app/widgets/data` and `/player/api/widgets/data` with SSRF-safe URL checks (`SafeRemoteUrl`).
- Platform ops (`/admin`): `EnsureUserIsAdmin` remains staff-gated. Super Admin-only mutations go through `PlatformPermissions` (roles, feature flags, platform settings). `AuditLogger` records sensitive platform actions. `SystemHealthChecker` is cached and credential-safe. Do not add impersonation.
- **Billing (Phase 13):** Workspace is the Cashier billable entity (one Stripe Customer). TV licences (UI) = Screen licence quantity; used = Connected (non-revoked) Screens. Customer billing lives under `/app/settings/billing`. Online/Offline and Active/Inactive do not affect billing. `App\Support\Billing\BillingEntitlement` is the only entitlement authority. `billing.enforce` (env `BILLING_ENFORCE`) gates pairing/publish/player when true; default false for local/test. Stripe Checkout + Customer Portal; verified webhooks; Owner manages, Admin may view.
- Schedules (`schedules` / `schedule_screen`) decide **when** a pinned published `PlaylistVersion` plays; Deployments remain the always-on fallback. `App\Support\Schedules\ScheduleEvaluator` is the only place that decides whether a schedule window matches and `App\Support\Screens\ScreenContentResolver` is the only place that ranks Schedule over Deployment — never duplicate either rule.
- Schedule display status `Ended` is **derived** (active + past `end_date` in its own timezone), never stored, and is unrelated to a Screen being operationally Active.
- The Schedules UI lives at `/app/schedules` (library list + week calendar) with a shared create/edit form and a JSON-backed preview dialog. Never re-derive timing, precedence or `Ended` in React — read them from the controller payload. Overlaps are **warnings only** and never block a save; the wording is "This schedule overlaps another active schedule. Higher priority content will play."
- Playlists are single-orientation, ordered lists of **published** Screen Design versions (`playlists` / `playlist_versions` / `playlist_items`). Duration/transition defaults live in `config/playlists.php` via `App\Support\Playlists\PlaylistDefaults` — never duplicate those numbers. Publishing a playlist finalises a version; it does not send content to a TV. **Publish to Screen** for a published playlist is driven from the playlist library (`POST /app/playlists/{playlist}/publish-to-screens`); the `/app/screens` publish dialog still offers Screen Designs only.

## Coding Rules

- Do not install packages without a real requirement.
- Do not duplicate existing services/components.
- Do not create speculative abstractions.
- Prefer simple maintainable architecture.
- Enforce authorization server-side.
- Validate user input.
- Use transactions where business operations require atomicity.
- Never commit secrets.
- Do not silently ignore errors.
- Do not remove tests simply to make CI pass.

## Testing

After meaningful changes:

- run relevant backend tests (`php artisan test`)
- run relevant frontend checks (`npm run lint`, `npm run types:check`)
- run build (`npm run build`) when UI/assets change
- run relevant Playwright flows (`npm run test:e2e`) when user journeys change

Fix failures before reporting completion.

## Documentation

When an architectural decision genuinely changes:

Update the appropriate `/docs` file.

Do not let documentation knowingly drift from implementation. Mark planned work as planned.

## Product Scope

RMSignage is universal.

Do not make the platform restaurant-specific.
