# DZ Signage Figma Reference

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

for DZ Signage.

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
- Brand mark: TV icon in cyan rounded square + “DZ Signage” wordmark

## Major Figma Areas

Screens/areas present in the Make source (not guessed):

### Public

- Landing
- Pricing
- Auth (login / register / forgot password)
- Onboarding

### Customer app (`/app`)

- Dashboard
- Screen Designs
- Templates (+ Template Builder)
- Media
- Brand Kit
- Playlists
- Publishing
- Schedules
- Screens
- Locations
- Analytics
- Team
- Billing
- Settings
- Help
- Editor (full-screen design editor)

### TV / Player (in Make)

- TV Pairing (`/tv/pair`)
- TV Player (`/tv/player`)

### Super Admin (`/admin`)

- Admin Dashboard
- Workspaces (+ workspace detail)
- Templates admin
- Screen Health
- Publishing Jobs
- System Health
- Support
- Audit Log
- (Additional nav placeholders exist in Make for some admin sections)

## Industry Diversity

DZ Signage is universal.

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

Figma frame: TBD

### Screen Designs

Figma frame: TBD

### Playlists

Figma frame: TBD

### Schedules

Figma frame: TBD

### Publishing

Figma frame: TBD

### Screens

Figma frame: TBD

### Super Admin

Figma frame: TBD

## Known Make ↔ repo path notes

| Concern         | Figma Make                  | Current Laravel app               |
| --------------- | --------------------------- | --------------------------------- |
| Player URL      | `/tv/pair`, `/tv/player`    | `/player`                         |
| Product screens | Full interactive prototypes | Mostly `coming-soon` placeholders |
| Stack in Make   | Standalone React SPA mock   | React + Inertia inside Laravel    |

Prefer architecture docs + Laravel routes for URLs; prefer Figma for visual/UX fidelity.
