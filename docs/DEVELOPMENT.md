# RMSignage — Development

Commands below match this repository (`package.json`, `composer.json`, `docker-compose.yml`, `.env.example`).

App URL: **http://localhost:8001** (`SERVER_PORT=8001` — avoids clashes with apps on `8000`).

After migrate, create a public storage link if needed:

```bash
php artisan storage:link
```

### Local login accounts (no email required)

When `APP_ENV=local`, seed ready-made verified users:

```bash
php artisan db:seed
# or only:
php artisan db:seed --class=LocalDevSeeder
```

| Email               | Password   | Access                                                                    |
| ------------------- | ---------- | ------------------------------------------------------------------------- |
| `owner@dz.local`    | `password` | Workspace Owner → `/app` + Media + Templates + Screen Designs + Screens   |
| `viewer@dz.local`   | `password` | Viewer (read-only Media / Templates / Screen Designs / Screens)           |
| `admin@dz.local`    | `password` | Platform Super Admin (`/admin` full ops) + workspace Admin                |
| `platform@dz.local` | `password` | Admin / platform staff (`/admin` ops; cannot mutate flags/settings/roles) |
| `test@example.com`  | `password` | Verified user, **no** workspace (onboarding)                              |

New registrations in `local` are **auto-verified** (no inbox). Verification emails still use `MAIL_MAILER=log` → `storage/logs/laravel.log` if you need the link.

Workspace logos are stored on the local `public` disk (`storage/app/public/workspace-logos`).

Media Library files use `config('media.disk')` (default `public`) under `storage/app/public/workspaces/{id}/media/`. Set `MEDIA_DISK` when moving to S3/R2 later.

Optional demo text/link samples:

```bash
php artisan db:seed --class=MediaDemoSeeder
```

`LocalDevSeeder` also calls `PlatformOpsSeeder` (feature flags + default platform settings).
Platform starter Templates (curated LayoutSchema v1 library — idempotent by `slug`):

```bash
php artisan db:seed --class=PlatformTemplatesSeeder
```

(`DatabaseSeeder` already calls this when you run `php artisan db:seed` in `local`.)

`LocalDevSeeder` / `DemoWorkspaceSeeder` / `php artisan dz:cleanup-e2e` remove disposable E2E artifacts whose names start with the `E2E ` prefix (templates, screen designs, playlists, schedules, **screens**, media). Playwright `globalTeardown` runs `dz:cleanup-e2e` after the suite so `owner@dz.local` is not left polluted. Prefer the `E2E ` prefix for automated-test names; do not use generic Untitled names when avoidable.

### Demo workspace content

`DemoWorkspaceSeeder` fills the Local Dev Workspace (**North & Bean**) with coherent demo Media, Brand Kit, Locations (with Screens assigned), Screen Designs, Playlists, Schedules, and ~80 days of analytics / heartbeat history.

- **Real photographs** — curated Unsplash JPEGs under `resources/demo/photos/` (see `docs/DEMO_MEDIA_SOURCES.md`); landing copies sync via `php artisan marketing:generate-assets`. No remote hotlinks; GD silhouettes are fallback/test-only.
- **Junk cleanup** — on each run, strips `E2E ` fixtures plus demo-workspace `Untitled%` / blank / empty-schema Screen Designs so re-seeds stay clean.
- **Demo heartbeats** — `DemoHeartbeatSimulator` seeds recent presence for demo TVs only (believable online/attention mix); never fakes production Player state.
- **Idempotent** — safe to re-run; updates existing local-dev demo rows rather than duplicating them.
- **Refuses production** — no-ops with a warning when `APP_ENV=production`.
- **Requires** `owner@dz.local` (run `LocalDevSeeder` first if missing).
- Invoked automatically by `LocalDevSeeder`, or alone / via artisan:

```bash
php artisan db:seed --class=DemoWorkspaceSeeder
php artisan dz:seed-demo            # same seeder + history; --fresh-demo rebuilds demo-owned content
php artisan dz:cleanup-e2e          # strip E2E-prefixed fixtures (also runs after Playwright)
```

### Production demo account (deployed environments)

Use the dedicated command — **never** `db:seed` / `dz:seed-demo` against production:

```bash
# Requires RMSIGNAGE_DEMO_EMAIL + RMSIGNAGE_DEMO_PASSWORD in the environment
php artisan rmsignage:seed-demo-account --allow-production
```

Creates/refreshes only `demo@rmsignage.com` → **North & Bean Café** (`rmsignage-demo-north-bean`). Idempotent; no Stripe activity; no usable device tokens. See `docs/PRODUCTION.md`.

### Production platform staff accounts

Do **not** seed `admin@dz.local` / `platform@dz.local` into production. Create staff with:

```bash
php artisan rmsignage:create-super-admin --name="Ahmed" --email="you@example.com"
php artisan rmsignage:create-platform-admin --name="Sarah" --email="sarah@example.com"
```

Password is entered hidden (never via flags). Accounts are verified immediately and are **not** attached to any Business. Login defaults to `/admin`. See `docs/ROLES_AND_PERMISSIONS.md`.

### Phase 1–5 surfaces

| Path                                                | Purpose                                                                     |
| --------------------------------------------------- | --------------------------------------------------------------------------- |
| `/onboarding`                                       | First workspace creation for verified users with no membership              |
| `/app/*`                                            | Customer app (`AppSidebar`; requires current workspace membership)          |
| `/app/media`                                        | Media Library (authorised delete + confirmation + file cleanup)             |
| `/app/templates`                                    | Browse / search / filter / favourite / Use Template (published)             |
| `/app/templates/{id}/preview`                       | Template preview                                                            |
| `POST /app/templates/{id}/use`                      | Use Template → independent Screen Design                                    |
| `/app/screen-designs`                               | Screen Design list + blank create                                           |
| `/app/screen-designs/{id}/edit`                     | Customer Screen Design Editor (read-only for Location Manager / Viewer)     |
| `POST /app/screen-designs/{id}/publish`             | Publish Design (finalise version — not send to TV)                          |
| `POST /app/screen-designs/{id}/publish-to-screens`  | Publish published design to one or more Screens                             |
| `/app/screens`                                      | Screens list                                                                |
| `/app/screens/pair`, `/app/screens/pair/{publicId}` | Claim pairing (manual code or QR)                                           |
| `/app/screens/{id}`                                 | Screen detail (rename / status / unpair / publish)                          |
| `POST /app/screens/{id}/publish`                    | Screen-first publish of a published design                                  |
| `/app/playlists`                                    | Workspace Playlists library                                                 |
| `/app/playlists/{id}/edit`                          | Playlist editor (reorder, duration, transitions, active)                    |
| `/app/schedules`                                    | Schedules library (list table + week calendar tab)                          |
| `/app/schedules/create`, `/app/schedules/{id}/edit` | Shared schedule form (Details → Playlist → Screens → Timing → Review)       |
| `GET /app/schedules/{id}/preview`                   | Schedule preview JSON (summary, upcoming, conflicts, pinned items)          |
| `POST /app/schedules/{id}/conflicts`                | Overlap warnings for a saved schedule — advisory, never blocking            |
| `/app/team`                                         | Members + invitations                                                       |
| `/app/settings`                                     | Settings hub (General / Workspace / Billing / Security tabs)                |
| `/app/settings/{tab}`                               | Settings tab (`general`, `workspace`, `billing`, `security`)                |
| `/app/workspace-settings`                           | Redirects to `/app/settings/workspace` (POST update still here)             |
| `/app/billing`                                      | Redirects to `/app/settings/billing` (mutation routes remain under billing) |
| `/invitations/{token}`                              | Accept workspace invitations                                                |
| `/player`                                           | Standalone Player (no app/admin chrome)                                     |
| `POST /player/api/pairing-sessions`                 | Create pairing session → `DZ-XXXX` + QR URL                                 |
| `GET /player/api/pairing-sessions/{publicId}`       | Poll pairing (one-time device token after claim)                            |
| `GET /player/api/manifest` (+ `/check`)             | Manifest v1 (device auth)                                                   |
| `GET /player/api/offline-package`                   | Versioned offline sync package (horizon schedules + media list)             |
| `POST /player/api/heartbeat`                        | Presence + optional `metadata.offline` cache readiness                      |
| `GET /player/api/media/{id}`                        | Media for active deployment schema (device auth)                            |
| `/admin`                                            | Platform Overview — real counts (workspaces, users, templates, published)   |
| `/admin/workspaces`, `/admin/users`                 | Platform staff read-only lists                                              |
| `/admin/screens`, `/admin/screens/{screen}`         | Platform read-only Screens directory + support view                         |
| `/admin/screen-health`                              | Platform presence summary from Player heartbeats                            |
| `/admin/templates`                                  | Platform Templates (Super Admin / Admin)                                    |
| `/admin/templates/{id}/builder` (or `/edit`)        | Platform Template Builder                                                   |

`/admin` uses a dedicated **AdminSidebar** (amber **RMSignage Admin**, **Back to App**) — not the customer sidebar. Admin Billing, Operations, Support, and Platform settings routes are implemented (Phase 12–13); do not treat them as coming-soon placeholders.

There is **no** customer Template Builder and no workspace-owned Templates. Use Template creates a workspace Screen Design from the published schema.

**Publish Design ≠ Publish to Screen.** Only a published Screen Design version is deployable. Player device auth: Bearer, `X-Device-Token`, or cookie `dz_player_device_token`.

Playlists are implemented under `/app/playlists` (library, editor, preview, publish, publish-to-screens). Locations are implemented under `/app/locations` (including Screen → Location assignment). Brand Kit is under `/app/brand-kit`. Reverb is not built.

Player offline (Phase 10): Dexie stores the active sync package; Cache Storage holds required `/player/api/media/*` blobs; `/player-sw.js` caches the Player shell and `/build` assets only (never `/app` or `/admin`). Horizon defaults to 24h (`config/player.php`). Local Schedule changeovers use server-precomputed windows (inclusive start / exclusive end) — do not re-implement `ScheduleEvaluator` timing in React beyond picking among those windows. New dependency: **dexie**.

Widgets (Phase 11): LayoutSchema elements with `type: "widget"`, `props.widgetType`, and `props.config`. Registry: `resources/js/lib/widgets/registry.ts`. Render only through `WidgetRenderer` inside `LayoutRenderer`. External data: `POST /app/widgets/data` (editor) and `POST /player/api/widgets/data` (device); TTLs/timeouts in `config/widgets.php`. SSRF: `App\Support\Widgets\SafeRemoteUrl`. **Embeds:** `EmbedUrlValidator` + client `embed-resolve.ts` classify YouTube / Vimeo / HLS / direct video / website / blocked; shared `EmbedWidget` (Editor/Preview/Player); Chromium HLS via `hls.js`; sites that send `X-Frame-Options` / restrictive `frame-ancestors` show a clear blocked message — never bypass framing restrictions. Offline package may include `widgetData` snapshots for Weather/RSS/Calendar. Do not add a customer Widgets sidebar page.

Playlist defaults (`default` / min / max duration, default transition) live in `config/playlists.php` via `App\Support\Playlists\PlaylistDefaults` — do not re-declare those numbers in controllers or React.

Schedules are implemented under `/app/schedules` (library + week calendar, create/edit, activate, pause, duplicate, archive, delete, preview, conflict warnings). Schedule defaults (priority bounds, default window, default days) live in `config/schedules.php` via `App\Support\Schedules\ScheduleDefaults`. Timing lives only in `App\Support\Schedules\ScheduleEvaluator` and content precedence (Schedule → Deployment → none) only in `App\Support\Screens\ScreenContentResolver` — never re-derive either in controllers, the Player, or React. Pass an explicit `$at` when evaluating so a single evaluation cannot straddle two instants.

### List pagination (Schedules / Locations)

Schedules and Locations library tables use shared server-side pagination via `App\Support\ListPagination`:

- Query string: `per_page` (allowed **10 / 20 / 50**, default 20), plus list-specific `sort` and filters
- Controllers pass `per_page`, `sort`, and filter values back in the Inertia payload so the table stays in sync with the URL
- Frontend: `resources/js/components/ui/list-pagination.tsx` — do not invent a second pagination helper for these lists
- Sortable column headers use the shared Leads-style `SortableTableHeader` (`resources/js/components/ui/sortable-table-header.tsx`) — reuse it for new list tables instead of one-off sort UI

### Theme toggle

Customer app light/dark/system control lives top-right in `AppSidebarHeader` (`ThemeToggle`), not in the user menu.

Player heartbeats are configured in `config/screens.php` (`SCREEN_HEARTBEAT_INTERVAL`, `SCREEN_ONLINE_THRESHOLD`, `SCREEN_HEARTBEAT_RETENTION_DAYS`, `DZ_PLAYER_VERSION`). `screens:prune-heartbeats` runs daily via the scheduler.

Analytics (Phase 15): `/app/analytics` reads Workspace-scoped heartbeats, `playback_events`, `screen_daily_stats`, and Deployments. UI uses focused tabs (`?tab=overview|tvs|content|publishing|errors`). Dashboard is a compact one-viewport operational surface. Players POST batched events to `/player/api/playback-events` (throttle `player-playback-events`). Commands: `analytics:aggregate-daily`, `analytics:prune`. Config: `config/analytics.php`. Production ops: `docs/PRODUCTION.md`, `docs/RELEASE_AND_LAUNCH.md`, `docs/PLAYER_SOAK_TEST.md`. Health probes: `/health`, `/ready`. Optional large fixture: `php artisan db:seed --class=LargeWorkspaceSeeder` (local only).

### Layout schema (shared)

Platform Template Builder / Preview, customer Template preview, Screen Design Editor, and Player:

| Location                                                | Role                         |
| ------------------------------------------------------- | ---------------------------- |
| `app/Support/Rendering/LayoutSchema.php`                | Blank schema + version const |
| `app/Support/Rendering/LayoutSchemaValidator.php`       | Server-side validation       |
| `resources/js/types/layout-schema.ts`                   | TS types + helpers           |
| `resources/js/components/rendering/layout-renderer.tsx` | React `LayoutRenderer`       |

Do not invent a second layout format. Screen Design schemas store media as `props.mediaAssetId`; `LayoutRenderer` resolves via `mediaMap`. Widget elements use `props.widgetType` + `props.config` (and shared style props); Info Card images may use `props.config.mediaAssetId`. Optional semantic `brandBinding` on canvas/element props lets **Use Template** / Apply Brand Kit personalise via `BrandKitSchemaApplier`.

## Prerequisites

- PHP **8.3+** with `pdo_pgsql` (`composer.json`; 8.5+ recommended for local)
- Composer
- Node.js **22+** / npm
- Docker + Docker Compose
- Git

## 1. Clone and start infrastructure

```bash
cd dz-signage
docker compose up -d
```

| Service       | Host             | Credentials                             |
| ------------- | ---------------- | --------------------------------------- |
| PostgreSQL 16 | `127.0.0.1:5433` | db/user `dz_signage`, password `secret` |
| Redis 7       | `127.0.0.1:6379` | no password                             |

Testing DB: `dz_signage_testing` (same host/credentials; see `phpunit.xml`). Created via Docker init scripts under `docker/postgres/`.

## 2. Backend setup

```bash
composer install
cp .env.example .env   # if .env does not exist
php artisan key:generate
php artisan migrate
```

Or one-shot:

```bash
composer run setup
```

(`setup` = composer install → ensure `.env` → key:generate → migrate --force → npm install → npm run build)

### `.env` essentials

Already aligned in `.env.example`:

- `DB_CONNECTION=pgsql`, `DB_PORT=5433`, `DB_DATABASE=dz_signage`
- `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`
- `REDIS_CLIENT=predis`, `REDIS_PORT=6379`
- `APP_URL=http://localhost:8001`

## 3. Frontend setup

```bash
npm install
npm run build   # required before Playwright; optional for pure Vite HMR after first build
```

## 4. Local development

```bash
composer run dev
# equivalent: php artisan dev
```

Laravel Chisel runs the HTTP server, queue listener, log tail (`pail`), and Vite (`npm run dev`) together.

Vite only (if backend already running separately):

```bash
npm run dev
```

## 5. Quality checks

```bash
# PHP lint (Pint)
composer run lint          # fix
composer run lint:check    # CI-style check

# PHPStan (memory raised for Larastan on this stack)
composer run types:check
# or: php -d memory_limit=512M vendor/bin/phpstan analyse

# Pest
php artisan test
# or: composer run test:pest
# or: npm run test:backend

# Combined PHP quality (lint check + phpstan + pest)
composer run quality

# Frontend lint / format (vite-plus)
npm run lint               # vp check
npm run lint:fix
npm run check              # alias of lint

# TypeScript
npm run types:check        # tsc --noEmit

# Production frontend build
npm run build
npm run build:ssr          # client + SSR build

# Playwright (build assets first; do not leave `public/hot` in place —
# Vite HMR URLs break offline Player boots and Cache Storage shell tests)
npm run build
rm -f public/hot
npm run test:e2e
npm run test:e2e:ui

# Full JS+PHP gate used in package.json
npm run quality
```

Composer `ci:check`: `npm run check` + `npm run types:check` + Pest.

## 6. Useful paths

| Path                 | Role                                                            |
| -------------------- | --------------------------------------------------------------- |
| `app/`               | Laravel domain/code                                             |
| `resources/js/`      | React + Inertia                                                 |
| `routes/`            | `web.php`, `app.php`, `admin.php`, `player.php`, `settings.php` |
| `e2e/`               | Playwright specs                                                |
| `docs/`              | Authoritative product/architecture docs                         |
| `docker-compose.yml` | Postgres + Redis only                                           |

## 7. Stripe billing (Phase 13)

Laravel Cashier is installed. Workspace is the billable model.

```bash
# .env (test mode)
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
CASHIER_CURRENCY=gbp
# Plan Price IDs (authoritative catalog: config/billing_plans.php)
DZ_SIGNAGE_STARTER_MONTHLY_PRICE_ID=price_...   # also falls back from DZ_SIGNAGE_MONTHLY_PRICE_ID
DZ_SIGNAGE_STARTER_YEARLY_PRICE_ID=price_...
DZ_SIGNAGE_BUSINESS_MONTHLY_PRICE_ID=price_...
DZ_SIGNAGE_BUSINESS_YEARLY_PRICE_ID=price_...
BILLING_ENFORCE=false   # set true only when you want pairing/publish gated
# Optional legacy single-price catalog display (pence; mirrors Starter £19 / £180):
# BILLING_CATALOG_MONTHLY_AMOUNT=1900
# BILLING_CATALOG_YEARLY_AMOUNT=18000
```

Config keys:

| Key / file                                                             | Purpose                                                                     |
| ---------------------------------------------------------------------- | --------------------------------------------------------------------------- |
| `config/billing_plans.php` + `billing_plans` DB + `BillingPlanCatalog` | **Authoritative** plan catalog (DB preferred; config seed/fallback; no Pro) |
| `/admin/subscriptions/plans`                                           | Super Admin plan management (pricing, limits, features, Stripe sync)        |
| `billing.enforce`                                                      | Gate pairing/publish/player when true (`BILLING_ENFORCE`)                   |
| `billing.prices.monthly` / `yearly`                                    | Legacy Starter Price ID aliases (`DZ_SIGNAGE_*_PRICE_ID`)                   |
| `billing.catalog.*`                                                    | Legacy display fallback amounts (mirrors Starter)                           |
| `billing.min_screen_licenses` / `max_screen_licenses`                  | Quantity bounds                                                             |
| `billing.price_cache_ttl`                                              | Stripe price display cache                                                  |

**Plans (no Pro):** Starter £19/mo · £15/mo annual (£180/yr) · 5 Screens · 200 GB · 5 seats; Business £49/mo · £39/mo annual (£468/yr) · 20 Screens · 500 GB · 15 seats · Advanced Analytics; Enterprise custom / Contact Sales. Super Admin may edit amounts/limits/features in DB; Stripe amount changes create **new** Prices (existing subscriptions stay on old Price until explicit migrate).

Forward webhooks locally:

```bash
stripe listen --forward-to localhost:8001/stripe/webhook
```

Without Stripe Price IDs, `/app/settings/billing` (and marketing `#pricing`) still show **catalog** amounts from `BillingPlanCatalog` (DB or `config/billing_plans.php`). Checkout remains unavailable until Price IDs are set. Do not hardcode ad-hoc amounts in React — read them from `BillingEntitlement` / `BillingPlanCatalog`.

Licence rule: used = Screens with a non-revoked `ScreenDevice`. Online/Offline and Active/Inactive do not change used licences. Super Admin may change a Workspace between Starter and Business via Cashier (`AdminChangeWorkspaceSubscription`) when Stripe is configured; Enterprise stays Contact Sales.

## 8. Marketing landing page (Phase 13.5)

`GET /` renders `resources/js/pages/marketing/home.tsx` through `LandingController`. The old `welcome` page is gone.

| Path                                        | Role                                                                   |
| ------------------------------------------- | ---------------------------------------------------------------------- |
| `resources/js/layouts/marketing-layout.tsx` | Public shell + `MarketingSection` / `SectionHeading` helpers           |
| `resources/js/components/marketing/`        | One file per section, plus `product-frame.tsx` for the CSS mockups     |
| `resources/js/lib/marketing-motion.ts`      | Reveal variants, ambient loops, reduced-motion check, anchor scrolling |
| `resources/js/components/watermelon/`       | Third-party components, restyled onto DZ tokens before use             |

Rules when editing it:

- Describe only shipped capability. No testimonials, usage metrics, ratings, or trial claims. AI marketing copy must match Phase 14 (text/image/design only — no AI video).
- Prices come from `BillingPlanCatalog` / `BillingEntitlement` via the controller (Stripe when configured; otherwise plan catalog amounts). Never hardcode an amount in React.
- Product visuals are CSS/div mockups, not screenshots or stock photography.
- Read `prefers-reduced-motion` only through `marketing-motion.ts`.
- Do not add `href="#"` links. Omit footer links (Privacy, Terms) until the pages exist.
- Section anchors (`#product` `#features` `#templates` `#industries` `#ai` `#pricing` `#faq`) are referenced by the navbar/footer/tests where applicable — rename carefully. Landing home keeps hero, workflow (with Reach every TV), feature-showcase, templates, industries, ai, pricing, faq, and final-cta; pairing/fleet/schedule/offline/widgets/publishing are not separate top-level sections.

Tests: `php artisan test --filter=Landing` and `npx playwright test e2e/landing.spec.ts`.

## 9. AI content generation (Phase 14)

| Path                                                                                                    | Role                                                      |
| ------------------------------------------------------------------------------------------------------- | --------------------------------------------------------- |
| `config/ai.php`                                                                                         | Provider/models/limits/quality (no secrets in frontend)   |
| `App\Support\Ai\AiContentService`                                                                       | Central orchestration (brief → archetype → validate)      |
| `App\Support\Ai\AiSignageAgent`                                                                         | Propose/confirm Designs, Draft Playlists, Draft Schedules |
| `App\Support\Ai\MediaRelevance` / `TemplateRelevance`                                                   | Workspace Media + Template scoring for designs            |
| `App\Support\Ai\CreativeBrief*` / `DesignArchetypes` / `DesignQualityValidator` / `ImagePromptEnhancer` | Premium quality pipeline                                  |
| `App\Support\Ai\Providers\*`                                                                            | `fake` + `openai_compatible`                              |
| `resources/js/components/ai/*`                                                                          | Design/text/image dialogs + unified agent dialog          |
| Feature flag                                                                                            | `ai_content_generation`                                   |

Local defaults: `AI_ENABLED=true`, `AI_PROVIDER=fake` for tests/e2e without paid APIs. Production should use `openai_compatible` + `AI_TEXT_MODEL`/`AI_DESIGN_MODEL` (default `gpt-4o`). Schedule: `php artisan ai:prune-generations`.

Agent: `POST /app/ai/agent/propose` → review → `POST /app/ai/agent/confirm` (drafts only unless activate/deploy confirmed). Never auto-publish to TVs.

Tests: `php artisan test --filter='AiContent|PremiumAi|AiSignage'` and `npx playwright test e2e/ai-phase14.spec.ts`.

## 10. Analytics charts (Phase 15)

Shared Recharts components live in `resources/js/components/charts/*`. Dashboard uses 30-day availability + content activity + health donut. Analytics page uses range/screen filters over `AnalyticsQuery` series. Never invent chart datasets client-side.

## 11. Product terminology

Customer UI: the tenant is a **Business** (internal model remains `Workspace`). Physical displays are **TV/TVs** (nav **Paired TVs**). Content remains **Screen Design(s)**. Assignable team roles are Owner, Admin, Designer, and Content Manager. Helpers: `App\Support\ProductLabels` and `resources/js/lib/product-labels.ts`. Internal `Screen` models/routes may remain unchanged.

## 12. Not installed yet

Do not document or assume without adding packages: Horizon, Reverb, S3/R2 SDKs, FFmpeg pipelines. AI uses Laravel HTTP client (no dedicated AI SDK package). Charts use Recharts only.
