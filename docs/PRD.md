# DZ Signage — Product Requirements

DZ Signage is a **universal multi-tenant digital signage SaaS** platform.

Customers manage reusable media, templates, finished Screen Designs, Playlists, Schedules, remote Screens, and Publishing — across many industries. The platform is **not** restaurant-specific.

## Supported industries (examples)

Masjids · Restaurants · Cafés · Retail · Corporate · Education · Healthcare · Hotels · Gyms · Real Estate · Events · Community organisations · Other organisations

## What customers can do

- Manage reusable **Media**
- Select platform **Templates**
- Build their own templates
- Create finished **Screen Designs**
- Combine Screen Designs into **Playlists**
- Configure playback duration and transitions (planned on playlist items)
- Create named **Schedules**
- Connect remote TVs/displays (**Screens**)
- **Publish** content/schedules remotely
- Manage multiple Screens
- Eventually use dynamic **widgets**
- Eventually generate **AI** images/videos/text
- Manage subscriptions and team members

## Core product flow (authoritative)

```
Media → Templates → Screen Designs → Playlists → Schedules → Publishing → Screens
```

Do not blur or reorder these concepts in product language.

## Definitions

### Media

Reusable content such as: images, videos, text, logos, documents, links.

### Template

Reusable screen layout/structure. Contains orientation, layout, placeholders, panels, and future widget positions.

### Screen Design

Finished signage created from a Template.

### Playlist

Ordered collection of Screen Designs. Each item can later have duration, transition, and active/inactive state.

### Schedule

A named timing configuration selecting a Playlist. Can later control dates, days, start/end times, timezone, active/inactive state, and priority.

### Publishing

Deployment/assignment of a Schedule, Playlist, or Screen Design to one or more Screens.

### Screen

A remote physical TV/display/player.

## User onboarding flow

```
Create Account
  → Create Workspace
  → Add Media
  → Select or Build Template
  → Configure Screen Design
  → Create Playlist
  → Create Schedule
  → Connect Screen
  → Publish
  → Screen goes live
```

Some steps may later be skippable.

## Screen state model

Three **independent** state pairs (not six mutually exclusive statuses):

| Axis            | Values                   |
| --------------- | ------------------------ |
| **Operational** | Active · Inactive        |
| **Pairing**     | Connected · Disconnected |
| **Network**     | Online · Offline         |

Examples: `Active + Connected + Online`, or `Active + Connected + Offline`.

## Out of scope for early phases

Billing providers, AI generation, widgets, analytics, offline Player reliability, and full Super Admin tooling are planned later — see `/docs/ROADMAP.md`.
