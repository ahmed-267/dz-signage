# RMSignage — Product Requirements

RMSignage is a **universal multi-tenant digital signage SaaS** platform.

Customers manage reusable Media, browse platform Templates, create finished Screen Designs, build Playlists, pair Screens, publish published designs or playlists to TVs, and schedule playlists to play at the right times — across many industries. The platform is **not** restaurant-specific.

## Supported industries (examples)

Masjids · Restaurants · Cafés · Retail · Corporate · Education · Healthcare · Hotels · Gyms · Real Estate · Events · Community organisations · Other organisations

## What customers can do

- Manage reusable **Media**
- Browse / search / filter / preview / favourite platform **Templates** (starter library cards render the published schema via shared `LayoutRenderer`)
- **Use Template** to create an independent **Screen Design** (copies published schema; later Template edits do not mutate it)
- Create finished **Screen Designs** (blank Landscape 1920×1080 / Portrait 1080×1920, or from a Template); library cards render the actual design schema
- Edit Screen Designs in the customer editor (shared LayoutSchema v1)
- **Publish Design** — finalise a version for playlists / deployment eligibility (does **not** send content to a TV by itself)
- **Publish to Screen** — deploy a **published** Screen Design version to one or more paired Screens
- Combine published Screen Designs into **Playlists** (ordered items with duration, **loop count**, transition, active/inactive; runtime = duration × loops via `PlaylistRuntimeCalculator`)
- **Publish Playlist** — finalise a playlist version (does **not** schedule or deploy by itself)
- **Publish Playlist to Screen** — deploy a published playlist version to one or more Screens
- Create named **Schedules** that play a published playlist on chosen Screens over a timing window (timezone, days, dates, times, priority)
- Connect remote TVs/displays (**TVs**) via pairing code / QR — hosted Player at `/player`. Fire TV can run the **same** Player as a PWABuilder-packaged APK (sideload). That APK is not a native rewrite and is not an Amazon Appstore app.
- Manage Screens (rename, operational status, unpair)
- Use dynamic **Widgets** inside Screen Designs (Weather, RSS, Calendar, Embed — YouTube/Vimeo/HLS/video/website with blocked-site detection, Clock, etc.)
- Generate **AI** text, images, and draft Screen Designs from professional layout archetypes (no AI video)
- View **Analytics** for Screen connectivity, playback, content, and publishing (real telemetry only)
- Maintain a workspace **Brand Kit** (colours, fonts, logos) for design + AI
- Organise Screens into **Locations** and assign Screens to Locations
- Manage subscriptions (Starter / Business / Enterprise plan catalog via Stripe Checkout / Portal; catalog pricing display when Stripe is unconfigured) and team members (plan seat limits on `/app/team`)

Customers never create, edit, publish, archive, or delete master Templates. Templates are platform-owned.

## Core product flow (authoritative)

```
Media → Templates → Screen Designs → Playlists → Schedules → Publishing → Screens
```

Do not blur or reorder these concepts in product language.

**Shortcut:** Publishing can assign a published Screen Design **or** a published Playlist directly to Screens without a Schedule. That Deployment is always-on and stays the fallback: a Schedule only overrides it inside its own window.

## Definitions

### Media

Reusable content such as: images, videos, text, logos, documents, links.

### Template

Reusable screen layout/structure owned by the DZ platform. Contains orientation, layout, placeholders, panels, and future widget positions. Managed only by platform staff under `/admin/templates`.

### Screen Design

Workspace-owned finished signage. Created blank (Landscape 1920×1080 or Portrait 1080×1920) or via **Use Template**, which copies the published Template schema into an independent design (`source_template_id` / `source_template_version_id`). Later Template versions do not mutate existing Screen Designs. Customers edit Screen Designs — never master Templates. **Publish Design** finalises a version; it is not TV deployment. Only a published version is deployable.

### Playlist

Workspace-owned ordered collection of **published** Screen Design versions. Each item has position, duration, transition (none / fade / slide left / slide right), transition speed, and active/inactive. Playlists are single-orientation. **Publish Playlist** finalises an immutable version; further edits create a new draft version. Existing deployments keep the pinned published version until explicit redeploy.

### Schedule

A named timing configuration that plays a **published** Playlist version on a set of Screens. Controls an optional date range, days of the week, start/end times, IANA timezone, priority, and a `draft` / `active` / `paused` / `archived` status. A schedule pins the playlist version that was published when it was saved, so publishing a later playlist version never silently changes what a schedule plays.

### Publishing (to Screens)

Deployment of a **published** Screen Design version **or** a **published** Playlist version to one or more Screens (`Deployment` with `content_type` `screen_design` | `playlist`). Distinct from **Publish Design** / **Publish Playlist**, and distinct from a **Schedule**: a Deployment is always-on, while a Schedule only plays inside its window. A matching Schedule takes precedence over the Deployment, which acts as the fallback outside every window. Later edits do not change the deployed pinned version until republish/redeploy.

### Screen

A workspace-owned remote physical TV/display/player. Paired via `ScreenDevice` credentials. Independent operational / pairing / network state axes. Optionally assigned to a **Location** (`location_id`) for grouping. The customer UI calls these **TVs**. The Location Manager role is no longer assignable; Locations remain.

### Brand Kit

Workspace-owned brand identity (one per Workspace): brand name/tagline, colour palette, heading/body fonts, and optional logo MediaAssets. Used as defaults in Screen Design work and AI prompts. LayoutSchema elements may carry semantic `brandBinding` fields; **Use Template** auto-personalises from the workspace Brand Kit via `BrandKitSchemaApplier`. Does not auto-publish content.

### Location

Workspace-owned physical site grouping Screens. Screens are assigned to a Location for filtering, analytics, and publishing. The dedicated Location Manager role is deprecated from Team assignment; existing `location_manager` memberships are left unchanged.

## User onboarding flow

```
Create Account
  → Create Workspace
    → Add Media
    → Select Template (browse / Use Template)
    → Configure Screen Design
    → Publish Design
    → Connect Screen (pair)
    → Publish to Screen
    → Screen goes live
    → Create Playlist → Create Schedule → Activate schedule
```

After Business creation, new Owners receive a **contextual product tour** (coach marks on real UI) plus a Dashboard checklist through first publish. Progress is stored on the user (`onboarding_*` columns). Skip / resume / **Replay Product Tour** (Help & Support) are supported. Replay keeps historical `onboarding_completed_at` and does not create Business/Screen/TV data. Demo and mature workspaces do not auto-start the tour, but may still replay from Help.

**After publishing**, Paired TVs is the primary “what is showing?” surface (`now_showing` from `ScreenContentResolver`). **Preview** on a TV card/detail opens a read-only modal that renders the resolver-backed Screen or Playlist (shared `PlaylistPreviewPlayer`). Change Content on a TV publishes Screen or Playlist via `PublishContentToScreens` (schedules still win while matching). Publishing remains the ops/history centre.

Playlist transitions (`none` / `fade` / `slide_left` / `slide_right`) share one double-buffer engine (`PlaylistPreviewPlayer` + `transitions.ts`) for Preview and `/player`. Lifecycle: IDLE → PREPARING (mount both layers at start poses) → ANIMATING → COMMIT. Fade is a true crossfade (A 1→0, B 0→1). Slides are push/carousel (both layers translate together). Fast/Normal/Slow ≈ 400/700/1000ms. Transition duration is visual only and does not change `PlaylistRuntimeCalculator` totals.

Some steps may later be skippable. Media, Templates, Screen Designs, Playlists, Screens/pairing, Publish to Screen (design or playlist), Schedules, Locations, Brand Kit, Billing, and Analytics are implemented.

## Screen state model

Three **independent** state pairs (not six mutually exclusive statuses):

| Axis            | Values                   | Source                                                                   |
| --------------- | ------------------------ | ------------------------------------------------------------------------ |
| **Operational** | Active · Inactive        | Stored (`operational_status`)                                            |
| **Pairing**     | Connected · Disconnected | Derived from active (non-revoked) device                                 |
| **Network**     | Online · Offline         | Derived from `last_seen_at` within the configured online threshold (90s) |

Examples: `Active + Connected + Online`, or `Active + Connected + Offline`.

Network presence comes from real Player heartbeats (`POST /player/api/heartbeat`, default every 45s) plus authenticated polls. A screen that stops reporting becomes Offline while staying Connected — only unpairing makes it Disconnected. Health (Healthy · Attention · Offline) and content sync (Up to date · Out of sync · Unknown) are derived views over these axes, never a replacement for them.

## Shipped completeness (through Phase 15)

Phases 0–15 are **COMPLETE**, including Billing (Phase 13), Analytics (Phase 15), Brand Kit, and Locations. Product branding is **RMSignage** (internal `Screen` model / `dz_*` identifiers may remain). Customer-facing physical displays are **TVs** (sidebar: **Paired TVs**). Customer-facing content objects are **Screens** (internal `ScreenDesign` / routes may remain `/app/screen-designs`). Schedule priority is **1–10** (default **5**, **10 = highest**). Overlaps are warnings via `DetectScheduleConflicts`. Live/video embeds use a shared LiveMedia resolver (YouTube, Vimeo, HLS, DASH, MP4/WebM, Teams/Zoom/Webex when officially embeddable). Landing and demo Media use curated real photographs (`docs/DEMO_MEDIA_SOURCES.md`). Dashboard is a compact one-viewport operational surface; Analytics uses Overview / TVs / Content / Publishing tabs. Screens library filters are All / Draft / Archived (Published designs still appear under All). AI is a signage-building agent (`AiSignageAgent`). See `/docs/ROADMAP.md` for phase history and post-launch corrections.
