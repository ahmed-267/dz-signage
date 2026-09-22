# RMSignage Figma Reference

## Main Figma Project

https://www.figma.com/make/LV2LWOdsLyhfoG8sXFWlRV/Follow-Markdown-File?t=HD09hia6xnKtdHpb-0&preview-route=%2Fapp%2Fmedia

| Field                 | Value                                           |
| --------------------- | ----------------------------------------------- |
| Type                  | Figma Make                                      |
| Project name          | Follow-Markdown-File                            |
| File key              | `LV2LWOdsLyhfoG8sXFWlRV`                        |
| MCP access            | Confirmed via `get_design_context` (node `0:1`) |
| Preview route in link | `/app/media`                                    |

For Make files, Figma MCP returns the Make **source tree** (pages/components as resources). Prefer the specific page resource when implementing a screen.

## Purpose

The Figma design is the authoritative:

- visual reference
- layout reference
- interaction reference
- UX reference

for RMSignage.

The `/docs` files remain authoritative for:

- architecture
- backend behaviour
- security
- permissions
- domain logic
- data model

## Source-of-Truth Priority

### Product / business logic

`/docs`

### Visual design / UX

Figma

### Implementation

Existing codebase

If a Figma design conflicts with documented security/business architecture, preserve the architecture/security requirement while matching the design as closely as practical.

## UI Implementation Rule

Before implementing or significantly modifying a UI page:

1. Read relevant `/docs`.
2. Inspect the relevant Figma frame/node (or Make page resource) through Figma MCP.
3. Inspect existing code/components.
4. Reuse existing components where suitable.
5. Implement the design.
6. Compare result against Figma.
7. Preserve responsive behaviour.
8. Preserve light/dark/system themes.
9. Preserve accessibility.

Do not implement major UI based only on memory when Figma access is available.

## Existing Design Language

Observed from the accessed Make project (`index.css`, `Sidebar`, `Media`, app shell):

- Modern SaaS operational UI (data-dense, compact)
- Left sidebar (~240px) with section labels (Create / Display / Insights / Account)
- Cyan primary accent (`#22D3EE` dark / `#0891B2` light)
- Polished dark theme default (`#09090E` background, `#111318` cards, `#1E2333` borders)
- Explicit light theme (`data-theme="light"`) with white cards and cyan primary
- Theme toggle: light / dark / system
- Typography: Outfit (display), Inter (body), JetBrains Mono (labels/meta)
- Base radius ~8px; cards/modals often `rounded-xl` / `rounded-2xl`
- Subtle borders; card + table layouts (Media grid and list table)
- Primary CTA styling; restrained violet accent token for secondary emphasis
- Microinteractions: fade/scale/slide animations, hover states, skeleton shimmer
- Brand mark: TV icon in cyan rounded square + “RMSignage” wordmark

## Major Figma Areas

Screens/areas present in the Make source (not guessed):

### Public

- Landing
- Pricing
- Auth (login / register / forgot password)
- Onboarding

**Landing page exception (Phase 13.5 / 14).** The implemented marketing page at `GET /` treats Figma as **inspiration**, not a pixel target. Figma Make owns the app surfaces; the public page is composed from the same design tokens (cyan primary, Outfit display, JetBrains Mono labels) plus selected third-party [Watermelon](../resources/js/components/watermelon) components restyled onto those tokens. Pricing is a separate Make frame, but the implementation keeps pricing inside the landing page (`#pricing`) driven by `BillingPlanCatalog` / `BillingEntitlement` — do not add a standalone `/pricing` route to match Figma. Phase 14 adds a concise `#ai` section for shipped AI text/image/design assistance only (no AI video). If the Make landing frame ever shows testimonials, metrics, ratings, or trials, ignore them.

**Brand Kit / Locations / Billing pricing.** Treat Figma as the visual reference for `/app/brand-kit`, `/app/locations`, and Billing/marketing presentation. **This catalog overrides older Figma amounts** (including single per-Screen £12/£120 and any Pro £99 structure). Authoritative source: `config/billing_plans.php` + `BillingPlanCatalog` — **Starter** £19/mo · £15/mo annual (£180/yr) · 5 Screens · 200 GB · 5 seats; **Business** £49/mo · £39/mo annual (£468/yr) · 20 Screens · 500 GB · 15 seats · Advanced Analytics; **Enterprise** custom / Contact Sales. No Pro plan. Legacy `billing.catalog` mirrors Starter. Do not hardcode prices in React.

### Customer app (`/app`)

- Dashboard
- Screen Designs (`/app/screen-designs` — list, blank create, editor)
- Templates (browse / preview / favourite / Use Template; no customer builder)
- Media
- Brand Kit
- Playlists
- Schedules
- Publishing
- Paired TVs (`/app/screens`)
- Locations
- Analytics (nav filtered by `can_view_analytics`)
- Team
- Billing (inside Settings hub; tab filtered by `can_view_billing`)
- Settings (hub at `/app/settings` for all members; Business/Billing tabs gated)
- Help
- Admin Portal (customer sidebar footer — platform staff only)
- Editor (full-screen Screen Design editor — implemented)

### TV / Player (in Make)

- TV Pairing (`/tv/pair`) — implemented as `/player` + `/app/screens/pair/{publicId}`
- TV Player (`/tv/player`) — implemented as `/player` (standalone; device auth + manifest polling)

### Platform Admin (`/admin`)

Dedicated admin shell (not the customer sidebar):

- Branding: amber mark + **RMSignage Admin** wordmark; platform role label (Super Admin / Admin)
- **Back to App** in sidebar footer and top bar
- Nav groups: Overview · Customers (Workspaces / Users / Screens) · Billing (Subscriptions / Invoices) · Content (Templates) · Operations (Screen Health / Publishing Jobs / System Health / Errors) · Support (Support Requests / Audit Log) · Platform (Feature Flags / Settings)
- **Overview metrics:** real counts only (workspaces, users, templates, published_templates) — no fake MRR
- Templates admin (+ platform Template Builder) — Super Admin & Admin
- Screens — read-only platform list (`/admin/screens`)
- Ops / Billing / Support / Platform pages are implemented in-app (Phase 12–13); prefer Laravel routes over Make “coming soon” frames

Figma may still show customer-side Template Builder / My Templates. **Architecture wins:** Templates are platform-owned; customer `/app/templates` is browse + Use Template (creates a Screen Design).

## Industry Diversity

RMSignage is universal.

Figma implementation and demo content should avoid restaurant-only framing.

Use mixed examples such as: Masjid · Retail · Corporate · Restaurant · Education · Healthcare · Hospitality · Events.

## Specific Frame Links

Fill when concrete frame/node URLs are supplied. Do not invent URLs.

### Dashboard

Figma frame: TBD

### Media

Figma frame: TBD  
(Make preview route in main URL: `/app/media`)

### Templates

Figma Make source: `src/pages/app/Templates.tsx` (library grid, aspect-ratio cards, Preview / Use Template overlays).  
Product cards must render **live schema** via `LayoutRenderer`, not Figma mock thumbnails.  
(Customer: library browse; platform builder lives under `/admin/templates`)

### Screen Designs

Figma Make source: `src/pages/app/ScreenDesigns.tsx` / `Designs.tsx`.  
List cards and Preview use the same LayoutSchema + `LayoutRenderer` as the editor and Player.  
Library status filters: **All / Draft / Archived** only (Published designs remain visible under All). Implemented.

### Dashboard / Analytics

Dashboard: compact KPIs + availability/health + limited activity tables aimed at one desktop viewport.  
Analytics: tabbed Overview / TVs / Content / Publishing (+ Errors) via `?tab=`. Real telemetry only.

### Playlists

Figma Make source: `src/pages/app/Playlists.tsx` (wide horizontal playlist rows with sequenced design previews, durations, Assign/Preview/Edit).  
Product: `/app/playlists` uses Figma-aligned **wide playlist cards** (design sequence + runtime + TV/Schedule counts), not a generic thumbnail grid. Preview uses shared `LayoutRenderer` / `PlaylistPreviewPlayer`. Assign opens existing Publish-to-TV flow. Single-orientation; Publish Playlist ≠ Publish to TV. Implemented Phase 7; UX refreshed post-Phase 15.

### Schedules

Figma Make source: `src/pages/app/Schedules.tsx` (list + week calendar).  
Create/Edit UX: compact **5-step stepper** (Basics → Content → Timing → TVs → Review) with two-column fields and weekday chips — inspired by RML form IA, RMSignage visual language. Product logic unchanged (`ScheduleEvaluator` / `ScreenContentResolver`). Schedules pin **published playlists** only. Implemented Phase 8; create flow UX refreshed post-Phase 15.

### Publishing

Figma frame: TBD  
(Publish to TV implemented — distinct from Publish Design)

### Paired TVs (Screens)

Figma Make source: `src/pages/app/Screens.tsx` (fleet table, status filters, Connect Screen).  
Customer UI label: **Paired TVs** (`ProductLabels`); routes may remain `/app/screens` (+ `/app/tvs` alias). Three independent axes (Active/Inactive · Connected/Disconnected · Online/Offline). **Screen Designs** terminology is unchanged. Pairing guide shows the real browser Player URL (`/player`), QR + PIN steps, and Help “How to pair a TV” — do not claim native TV OS apps. Implemented Phase 6 (+ pairing UX correction).

### Admin TV Health

Figma Make source: `src/pages/admin/AdminScreenHealth.tsx`.  
Product uses real heartbeat/presence counts only — no synthetic CPU, IP, or uptime diagnostics. UI label: **TV Health** (`/admin/screen-health`). Implemented Phase 6.

### Team

Figma Make source: `src/pages/app/Team.tsx`.  
Product: `/app/team` — members table (avatar, role badges, Active/Invited status), Roles & Permissions overview cards, Invite Member gated by `BillingEntitlement` seat limit. Do not invent a Last Active column unless activity is tracked.

### Settings / Billing

Settings hub at `/app/settings/{tab}` (General, Workspace, Brand, Team link, Billing, Security). Standalone Billing nav removed; `/app/billing` redirects to Settings → Billing. Theme control stays in the shared app header.

## Known Make ↔ repo path notes

| Concern            | Figma Make                        | Current Laravel app                                                                                                                    |
| ------------------ | --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Player URL         | `/tv/pair`, `/tv/player`          | `/player` (+ `/player/api/*`); QR claim at `/app/screens/pair/{publicId}`                                                              |
| Admin shell        | Dedicated admin chrome            | `AdminSidebar`: **RMSignage Admin**, amber branding, **Back to App**                                                                   |
| Admin Overview     | May show SaaS KPIs                | Real counts only (workspaces / users / templates / published_templates)                                                                |
| Admin Screens      | Full ops prototypes               | `/admin/screens` + detail; `/admin/screen-health` (UI: **TV Health**, real heartbeats)                                                 |
| Template Builder   | Often shown under customer `/app` | Platform only: `/admin/templates` (+ builder)                                                                                          |
| Customer Templates | May include Create / My Templates | Browse / preview / favourite / Use Template (`POST /app/templates/{id}/use`)                                                           |
| Screen Designs     | Full editor prototype             | Implemented: `/app/screen-designs` + editor; Publish Design ≠ Publish to TV                                                            |
| Screens / Player   | Full interactive prototypes       | Customer **Paired TVs**; Phase 5 pairing/publish + Phase 6 heartbeats; internal `Screen` model retained                                |
| Product screens    | Full interactive prototypes       | Media + Templates + Screen Designs + Playlists + Schedules + Publishing + Paired TVs/Player + Brand Kit + Locations + Settings/Billing |
| Settings / Billing | Often separate nav items          | Settings hub tabs; Billing under Settings; theme in header                                                                             |
| Stack in Make      | Standalone React SPA mock         | React + Inertia inside Laravel                                                                                                         |

Prefer architecture docs + Laravel routes for URLs and ownership; prefer Figma for visual/UX fidelity.
