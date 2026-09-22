# RMSignage — Roadmap

Phases are sequential guidance for delivery. Do not implement future phases unless requested.

## Phase 0 — Project Foundation

Repo scaffold, Docker Postgres/Redis, Fortify auth shell, Inertia surfaces (`/`, `/app`, `/admin`, `/player`), Pest + Playwright smoke, authoritative `/docs` + Figma reference.

## Phase 1 — Authentication, Workspaces, Roles & Application Shells

**Status: implemented** (with later Phase 2/3 shell/role corrections)

Workspaces, memberships, roles/policies, current workspace, onboarding, team invitations, workspace settings, customer `/app` shell, dedicated `/admin` shell (platform staff), Super Admin workspace/user read views, workspace switcher.

## Phase 2 — Media Library

**Status: implemented**

Upload/manage reusable Media (images, videos, text, logos, documents, links). Workspace isolation, roles, local filesystem storage, replace/duplicate semantics. Authorised delete with confirmation; file cleanup for file-backed types; Text/Link DB-only.

## Phase 3 — Templates + Shared Rendering Schema

**Status: implemented** (with Phase 2/3 ownership + admin/role corrections)

- Templates are **platform-owned** (`workspace_id` null) — no workspace-owned / My Templates
- Platform roles: `super_admin`, `platform_admin` manage Templates under `/admin/templates` (builder, draft/publish/archive, versioning)
- Dedicated Admin shell: `AdminSidebar` (not customer sidebar), **RMSignage Admin**, **Back to App**, Figma-aligned nav; Overview shows real counts only
- Full WorkspaceRole matrix (billing/analytics/media/templates + flags for playlists/schedules/publish/screens/locations; Screen Design manage flags used in Phase 4; screen/publish flags used in Phase 5)
- Customer `/app/templates`: browse / search / filter / preview / favourite (Use Template completed in Phase 4)
- Customer sidebar: Create Design CTA (manage designs), Help, Admin Portal (platform staff only); Settings hub + Analytics + Display nav filtered by permissions
- Shared LayoutSchema v1 + `LayoutRenderer` + drag/resize foundations (platform Template Builder)
- `PlatformTemplatesSeeder`
- Legacy workspace Templates promoted to platform via migration

## Phase 4 — Screen Designs + Editor

**Status: implemented (COMPLETE)**

- **Use Template** (`POST /app/templates/{id}/use`) creates an independent Screen Design from the published Template schema (`source_template_id` / `source_template_version_id`)
- Blank Landscape 1920×1080 / Portrait 1080×1920
- Customers edit Screen Designs under `/app/screen-designs` — never master Templates
- Template version independence: later Template v2 does not mutate existing Screen Designs
- Customer Screen Design Editor reuses LayoutSchema v1 + LayoutRenderer (`mediaMap` / `props.mediaAssetId`) + Template Builder drag/resize/undo foundations
- Draft / Publish Design versioning — Publish Design finalises a version; it does **not** send content to a TV
- Media deletion blocked when referenced by a Screen Design
- Owner/Admin/Designer/Content Manager manage; Location Manager/Viewer read-only

## Phase 5 — Player + Pairing + First Real Publishing

**Status: implemented (COMPLETE)**

- Screens (workspace-owned): name, nullable orientation, operational status active/inactive, nullable `location_id` (future)
- ScreenDevice: `device_identifier`, `device_token_hash` (HMAC-SHA256 with app key), `paired_at`, `revoked_at`, `last_seen_at`
- PairingSession: ULID `public_id`, `code_hash`, 10-minute expiry, single-use claim, one-time encrypted pending device token for player poll
- Player pairing: `POST /player/api/pairing-sessions` → code `DZ-XXXX` + QR URL `/app/screens/pair/{public_id}`; manual claim `POST /app/screens/pair`; QR claim `GET/POST /app/screens/pair/{publicId}`; rate limited
- Device token never shown as pairing code; issued once via encrypted pending field on claim poll
- Standalone `/player` (no app/admin chrome); auth via Bearer, `X-Device-Token`, or cookie `dz_player_device_token`
- Manifest v1 (`GET /player/api/manifest` + `/player/api/manifest/check`); media `GET /player/api/media/{id}` scoped to active deployment; shared `LayoutRenderer`; polling reconciliation (not Reverb)
- Player states: pairing, loading, ready, no_content, inactive, error
- Independent state axes: Operational (stored), Pairing (derived), Network (derived from `last_seen_at` refreshed on player poll — superseded by Phase 6 heartbeats)
- **Publish Design ≠ Publish to Screen**; only published ScreenDesignVersion deployable
- Multi-screen publish `POST /app/screen-designs/{id}/publish-to-screens`; screen-first `POST /app/screens/{id}/publish`
- Later design edits do not change deployed version until republish
- Permissions: `canManageScreens` (Owner/Admin/Location Manager); `canPublishContent` (Owner/Admin/Content Manager); Designer manages designs + can view Screens but **cannot** publish to TV; Viewer read-only
- Delete Screen Design blocked while actively deployed; Media delete still blocked when referenced by Screen Designs
- `/admin/screens` read-only platform list

**Not in Phase 5:** Playlists, Schedules, dedicated heartbeats, offline/PWA, Reverb, widgets, AI.

## Phase 6 — Multi-Screen + Heartbeats + Screen States

**Implemented.** Dedicated Player heartbeats, screen health, and multi-screen operations.

- `POST /player/api/heartbeat` (device-authenticated) writes a `screen_heartbeats` row and refreshes `screen_devices.last_seen_at` plus telemetry: player version, viewport, reported orientation, playback state, error code, reported deployment
- `config/screens.php` — `heartbeat_interval_seconds` (45), `online_threshold_seconds` (90), `heartbeat_retention_days` (7), `player_version`; the Player takes its interval from the heartbeat response
- `App\Support\Screens\ScreenPresence` is the single source for Pairing / Network / Health / Content sync / orientation mismatch — thresholds are never duplicated in controllers or React
- Health `healthy` / `attention` / `offline`; content sync `up_to_date` / `out_of_sync` / `unknown` (device `reported_deployment_id` vs active Deployment)
- Customer Screens library: search, state filter chips with counts, sort, three labelled state badges, current content, last seen, multi-select bulk Activate / Deactivate / Publish (`POST /app/screens/bulk-status`, `POST /app/screens/bulk-publish`), quiet 30s refresh
- Customer Screen detail: Overview, State, Current content, Device telemetry, recent heartbeats, orientation mismatch banner
- Platform: searchable/filterable `/admin/screens`, read-only `/admin/screens/{screen}` support view, `/admin/screen-health` summary from real heartbeat data (no synthetic CPU / IP / uptime)
- `screens:prune-heartbeats` scheduled daily; history is troubleshooting only — presence still reads `screen_devices.last_seen_at`
- Heartbeat failures are silent in the Player: a screen that cannot report in keeps rendering its last known content

**Not in Phase 6:** Playlists, Schedules, Reverb, offline/PWA caching.

## Phase 7 — Playlists

**Implemented.** Ordered published Screen Designs with durations, transitions, and active flags.

- `playlists` / `playlist_versions` / `playlist_items`; publishing finalises a version, and saving over a published version creates the next draft version (published versions are immutable)
- `config/playlists.php` + `App\Support\Playlists\PlaylistDefaults` are the single source for default/min/max duration and default transition — never re-declare those numbers in controllers or React
- Transitions `none` / `fade` / `slide_left` / `slide_right`; speeds `fast` / `normal` / `slow` (`PlaylistTransitionSpeed::milliseconds()` is mirrored by the frontend transition CSS)
- Playlists are single-orientation: the first item fixes the orientation and `SavePlaylistDraft` rejects mixed-orientation items
- Items may only reference **published** Screen Design versions; later design edits do not change a published playlist version
- Customer library `/app/playlists`: search, status/orientation filters, sort, pagination, cards rendering the first item through the shared `LayoutRenderer`, and Edit / Preview / Duplicate / Publish / Archive / Delete
- Editor `/app/playlists/{playlist}/edit`: native HTML5 drag reorder plus keyboard move, per-item duration / transition / speed / active toggle, published-design picker, and an unsaved-changes guard
- Preview is a JSON endpoint (`GET /app/playlists/{playlist}/preview`) consumed by a dialog, not an Inertia page; playback is shared by the editor, library, and Player via `PlaylistPreviewPlayer`
- Player manifests carry `contentType: screen_design | playlist`; playlist manifests send active items with per-item schema and media. Manifests without `contentType` are treated as `screen_design`

- Publishing a playlist to TVs is driven from the playlist library (`POST /app/playlists/{playlist}/publish-to-screens`); the `/app/screens` publish dialog still offers Screen Designs only

**Not in Phase 7:** Schedules, playlist-level timing rules, and per-item media overrides.

## Phase 8 — Schedules

**Implemented.** Named schedules selecting published playlists, with timing rules and precedence.

- `schedules` / `schedule_screen`: a schedule pins a **published** `PlaylistVersion` and targets one or more Screens over a wall-clock window (IANA timezone, optional date range, ISO days of week, start/end times, priority, `draft` / `active` / `paused` / `archived`)
- Publishing a later playlist version does **not** reach an existing schedule; the pinned version only changes when someone re-picks the playlist
- `ScheduleEvaluator` is the single source of timing truth: inclusive start / exclusive end minute so changeovers do not double-match, overnight windows anchored to the day they opened, and precedence by priority → most recent `activated_at` → higher id
- `ScreenContentResolver` is the single source of content precedence: matching Schedule → active Deployment → none. Player manifests report `contentSource`, the schedule identity, and a `validity` window; `check()` returns a version label that changes at a window changeover
- Screen detail reports `current_schedule` and `next_schedule`; overlapping live schedules surface as **warnings**, never blocked
- **Ended** is derived (active + past `end_date` in its own timezone), never stored
- Library `/app/schedules`: search, status filter (including derived **Ended**), screen / playlist filters, sort, pagination, a list table (playlist, screens, time, day chips, timezone, priority, status, next run) and a week calendar tab that becomes a day-by-day agenda on small viewports
- Create / edit (`/app/schedules/create`, `/app/schedules/{schedule}/edit`) share one form: Details → Playlist → Screens → Timing → Review, with keyboard-reachable day toggles, Every day / Weekdays / Weekends shortcuts, an overnight hint when the end time precedes the start, Save draft, Activate and Pause
- Preview is a JSON endpoint (`GET /app/schedules/{schedule}/preview`) consumed by a dialog: summary, upcoming occurrences, overlap warnings, and playback of the pinned playlist version through the shared `PlaylistPreviewPlayer`
- Overlaps are fetched from `POST /app/schedules/{schedule}/conflicts` on edit load and after each save, and always render as the warning "This schedule overlaps another active schedule. Higher priority content will play."

**Not in Phase 8:** offline/on-device schedule evaluation (Phase 10), an admin schedule portal, and scheduling content types other than published playlists.

## Phase 9 — Publishing / Deployments

**Implemented.** Operational Publishing centre on top of existing Deployments.

- `/app/publishing`: Live Now (resolved content + ack from heartbeats), Scheduled (schedule-controlled screens), History (paginated Deployments with Republish)
- `PublishContentToScreens` is the only create path for direct Design/Playlist Deployments (existing Design/Playlist wrappers delegate here)
- Statuses: `pending` / `active` / `failed` / `superseded` / `revoked` — new publishes are **Active** immediately so offline Players pick them up on reconnect; Live/Updating/Waiting is derived from `ScreenPresence::contentSyncState`
- Precedence unchanged: Schedule → Deployment → none (`ScreenContentResolver`)
- `/admin/publishing-jobs`: read-only Deployment list across workspaces

**Not in Phase 9:** offline Player cache, queues/Horizon/Reverb, scheduled “publish later” one-shots (use Schedules).

## Phase 10 — Offline Player Reliability

**Implemented.** Player offline foundation on top of existing manifest / heartbeat / Schedule evaluation.

- Service Worker (`/player-sw.js`) caches Player shell + `/build` assets (never `/app` or `/admin`)
- IndexedDB via **Dexie** stores the versioned offline sync package; Cache Storage holds required Media
- `GET /player/api/offline-package` builds a 24h-horizon package (config `player.offline_horizon_hours`) with current content, precomputed Schedule windows (from `ScheduleEvaluator`), fallback Deployment, and asset list
- Player resolves offline content locally (Schedule window → Deployment → none) and changeovers without the network
- Atomic activate: pending package + assets must fully cache before replacing the active package
- Heartbeat `metadata.offline` reports cache readiness; Screen detail + Admin Screen Health surface it
- Revocation / 401 clears local cache and returns to pairing

**Not in Phase 10:** widgets, analytics, offline first-time pairing, Horizon/Reverb.

## Phase 11 — Widgets

**COMPLETE.** Widgets are LayoutSchema `type: "widget"` elements with `props.widgetType` + `props.config`.

- Registry: `resources/js/lib/widgets/registry.ts` + PHP `WidgetType` / `WidgetConfigValidator`
- Shared render: `WidgetRenderer` inside `LayoutRenderer` (editor / preview / player)
- Widgets: Clock · Countdown · Weather · News/RSS · Calendar/ICS · Alert · Information Card · Embed (YouTube / Vimeo / HLS / direct video / embeddable websites; blocked framing reported clearly; SSRF via `SafeRemoteUrl`)
- External data: `POST /app/widgets/data`, `POST /player/api/widgets/data`; Open-Meteo weather; SSRF-safe RSS/ICS (`SafeRemoteUrl`); cached TTLs in `config/widgets.php`
- Offline: Clock/Countdown/Alert/Info Card local; Weather/RSS/Calendar last-synced via offline package `widgetData`; Embed shows fallback offline
- Editors: Template Builder + Screen Design Editor Widgets tab + properties panel (no separate Widgets page)
- Starters enhanced: Prayer Times (Clock), Hotel Welcome (Clock+Weather), Event Welcome (Countdown), Corporate Welcome (Clock+Info Card)

**Not in Phase 11:** marketplace, custom JS/HTML widgets, OAuth calendars, social feeds, AI, billing.

## Phase 12 — Super Admin Expansion

**COMPLETE.** Full `/admin/*` ops portal: real Overview metrics; Workspaces/Users/Screens list+detail; Screen Health; Publishing Jobs; System Health; Errors; Support Requests (+ `/app/help`); Audit Log; Feature Flags; Platform Settings. Admin Billing routes are implemented in Phase 13 (Cashier / mirrored invoices). Super Admin vs Admin (platform) enforced via `PlatformPermissions`.

## Phase 13 — Billing / Subscriptions

**COMPLETE.** Workspace is the Cashier billable entity. Stripe Checkout + Portal; Screen licence quantity; `BillingEntitlement`; pairing/publish/player gated when `BILLING_ENFORCE=true`. Admin Subscriptions/Invoices use real mirrored Stripe data. Plan catalog is persistent `billing_plans` (seeded from `config/billing_plans.php`) via `BillingPlanCatalog` (Starter / Business / Enterprise; **no Pro**), editable at `/admin/subscriptions/plans`. When Stripe Price IDs are unset, UI shows catalog amounts (Starter £19/mo · £180/yr; Business £49/mo · £468/yr). Legacy `billing.catalog` mirrors Starter.

## Phase 13.5 — Public Marketing Landing Page

**COMPLETE.** Production marketing site at `GET /` (`LandingController` → `marketing/home`), replacing the placeholder `welcome` page.

- `MarketingLayout` (glass navbar + footer) with anchored sections `#product` `#features` `#templates` `#industries` `#ai` `#pricing` `#faq`; mobile navigation uses the shared `Sheet` drawer
- Home composition is intentionally short: hero → workflow (Create → Playlist → Schedule → Publish → TV, including Reach every TV) → feature-showcase → templates → industries → AI → pricing → FAQ → final CTA. Standalone pairing / fleet / schedule / offline / widgets / publishing sections were folded into workflow + feature-showcase.
- Landing and demo Media use curated **real photographs** (Unsplash License) under `resources/demo/photos/` — see `docs/DEMO_MEDIA_SOURCES.md`. Do not reintroduce GD silhouettes as primary landing art.
- Pricing reads Stripe Price data through `BillingEntitlement` / `BillingPlanCatalog` when configured; otherwise plan catalog amounts. No fake testimonials, metrics, ratings, trials, or native TV apps anywhere on the page
- Motion flows through `resources/js/lib/marketing-motion.ts` so `prefers-reduced-motion` is honoured in one place
- Selected Watermelon components (`continuous-tabs`, `card-split-accordian`) were restyled onto DZ tokens; unused registry demos and components depending on uninstalled packages were removed. Pricing presents the Starter / Business / Enterprise plan catalog (not a single per-Screen-only calculator)
- Tests: `tests/Feature/Marketing/LandingPageTest.php` + `e2e/landing.spec.ts`

**Not in Phase 13.5:** separate pricing/about/contact pages, blog/CMS, Privacy and Terms pages, lead capture forms, analytics scripts.

## Phase 14 — AI Content Generation

**COMPLETE.** AI is a content-creation assistant inside Media and the Screen Design Editor — never a Player runtime, and never auto-publishes.

- Architecture: `AiContentService` → `AiTextProvider` / `AiImageProvider` (replaceable). Providers: `fake` (tests/local) and `openai_compatible`. Config: `config/ai.php`. Video provider interface reserved; video generation **not** implemented.
- Persistence: `ai_generations` history + temp image files under `workspaces/{id}/ai-temp/`; Save to Media uses `CreateMediaAsset` / `MediaStorage`. Design generation validates via `AiDesignSchemaBuilder` + `LayoutSchemaValidator`.
- Entry points: Media **Generate with AI**; Screen Designs **Create with AI**; Editor AI tab (Write / Rewrite / AI Image). Feature flag `ai_content_generation`.
- Controls: throttle `ai-generation`, per-user/workspace RateLimiter, idempotency keys, `ai:prune-generations` schedule.
- Tests: `tests/Feature/Ai/AiContentGenerationTest.php` + `e2e/ai-phase14.spec.ts` (provider fakes; no paid API calls).

**Not in Phase 14:** AI video, credits/billing add-ons, autonomous publishing/scheduling, RAG, fine-tuning, Admin AI dashboards.

## Phase 15 — Analytics + Production Hardening ✅ COMPLETE

Real customer Analytics + launch hardening (no fake metrics).

- `/app/analytics`: overview, Screen performance, content/playlist leaderboards, publishing counts, date range + Screen filter (Workspace timezone).
- Telemetry: `playback_events` via `POST /player/api/playback-events` (batched, idempotent); heartbeats stay lightweight.
- Aggregates: `screen_daily_stats` via `analytics:aggregate-daily`; retention via `analytics:prune` (`config/analytics.php`).
- Hardening: `/health` + `/ready`, `SecurityHeaders`, System Health scheduler heartbeat, production docs (`docs/PRODUCTION.md`, `docs/RELEASE_AND_LAUNCH.md`, `docs/PLAYER_SOAK_TEST.md`).
- Optional load fixture: `LargeWorkspaceSeeder` (local only).
- Tests: `tests/Feature/Analytics/AnalyticsPhase15Test.php`.

**Not in Phase 15:** enterprise BI, Kafka, new product features, fake uptime charts, GDPR automation suite.

## Post-Phase 15 — Product completeness correction ✅ COMPLETE

Not a new numbered phase. Docs and product surfaces corrected so shipped work is documented as complete:

- **Brand Kit** — `/app/brand-kit`, one `brand_kits` row per Workspace; Owner/Admin/Designer manage; all members view; LayoutSchema `brandBinding` + Use Template auto-personalise via `BrandKitSchemaApplier`
- **Locations** — `/app/locations`, Screen → Location assignment, Location Manager scope via `location_user`
- **Demo seed** — idempotent `DemoWorkspaceSeeder` / `php artisan dz:seed-demo` (North & Bean + ~80 days history; refuses production). Production demo: `php artisan rmsignage:seed-demo-account --allow-production` (env credentials; scoped to designated demo Business only).
- **Schedule / Location table UX** — server-side `ListPagination` (`per_page` 10/20/50, sort, filters, query string); shared `SortableTableHeader` (Leads-style)
- **Theme toggle** — top-right in `AppSidebarHeader`
- **Billing plan catalog (final)** — DB `billing_plans` + `BillingPlanCatalog` (config seed/fallback): Starter £19/mo · £15/mo annual (£180/yr) · 5 TVs · 200 GB · 5 seats; Business £49/mo · £39/mo annual (£468/yr) · 20 TVs · 500 GB · 15 seats · Advanced Analytics; Enterprise custom. **No Pro.** Super Admin manages plans at `/admin/subscriptions/plans`. Stripe amount changes create new Prices; existing subscriptions stay until explicit migrate. Checkout still requires Price IDs.
- **Premium AI quality correction** — creative brief, layout archetypes (Hero Product, Split, Editorial, Information Board, Event, Menu, Full-Bleed, Welcome Lobby), `TextFit` + `DesignQualityValidator` refine/gate, Brand Kit context, image prompt enhancement, text variants, Create-with-AI concept selection.
- **Playlist runtime source of truth** — `PlaylistRuntimeCalculator` (`duration_seconds × loop_count`); draft vs published totals on Schedule editor; Player/manifest use the same rule.
- **Embed / live content** — `EmbedUrlValidator` + shared `EmbedWidget` (YouTube/Vimeo/HLS/`hls.js`/MP4/website); blocked framing reported; SSRF via `SafeRemoteUrl`; CSP `frame-src`/`media-src` intentional.
- **TV pairing guidance** — Paired TVs empty state + Pair a TV steps show browser Player URL; Help “How to pair a TV”; no native Tizen/WebOS/Fire claims.
- **Team UX** — Figma-style members table, role badges, Roles & Permissions cards, plan seat limit from `BillingEntitlement` (no fake Last Active).
- **Landing simplification** — ~20–30% shorter; redundant pairing/fleet/schedule/offline/widgets/publishing sections folded into workflow + feature-showcase.
- **Analytics visualisation correction** — shared Recharts chart system on Dashboard + `/app/analytics` over real telemetry (no fake datasets).
- **UX / TV terminology / Settings / AI agent correction** — customer-facing physical displays are TVs (nav **Paired TVs**); Screen Designs unchanged; Settings hub with Billing tab; Playlist Figma-style rows; Schedule stepper; `AiSignageAgent` propose/confirm for Designs/Playlists/Schedules.
- **AI / live-stream / admin / workflow correction** — not a new phase. AI plans honour design count, duration, loops, and exact TV names (ambiguous names ask); YouTube embeds use `www.youtube.com/embed` (nocookie does not play from the app origin) without a sandbox, and channel `/live` URLs are resolved server-side; customer Display nav is Playlists → Schedules → Publishing → Paired TVs → Locations; assignable roles are Owner, Admin, Designer, Content Manager; customer copy says Business; Super Admin lands on `/admin`; Business soft-delete; Admin Templates render schemas; TV Health shows the heartbeat window.

Do **not** invent Phase 16 here. Further work belongs in post-launch backlog or an explicitly requested phase.
