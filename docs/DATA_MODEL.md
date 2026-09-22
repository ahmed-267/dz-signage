# RMSignage — Data Model

**Current (Phase 15 + Brand Kit / Locations completeness):** foundation auth, Workspace domain, Media Library, platform Templates (+ shared layout schema), workspace Screen Designs, Screens / pairing / devices, Deployments, Player heartbeats, Playlists, Schedules, Widgets, Super Admin platform ops, Workspace Stripe billing (Cashier subscriptions + mirrored `billing_invoices`), AI content generation, customer Analytics (playback events + daily Screen stats), Workspace Locations, and Brand Kit.

## Implemented entities

| Entity                     | Purpose                                                                                               |
| -------------------------- | ----------------------------------------------------------------------------------------------------- |
| `User`                     | Account; `current_workspace_id`; `platform_role`                                                      |
| `Workspace`                | Tenant boundary (customer-facing name: **Business**); Cashier billable; soft-deleted via `deleted_at` |
| `WorkspaceMember`          | User ↔ workspace + role (unique pair)                                                                 |
| `WorkspaceInvitation`      | Pending team invites (hashed token)                                                                   |
| `BrandKit`                 | One brand identity per Workspace (colours, fonts, logos)                                              |
| `MediaAsset`               | Workspace-scoped reusable media item                                                                  |
| `Template`                 | Platform-owned reusable layout (`workspace_id` null)                                                  |
| `TemplateVersion`          | Versioned layout schema snapshot (`schema` JSONB)                                                     |
| `TemplateFavourite`        | Per-user favourite of a platform template                                                             |
| `ScreenDesign`             | Workspace-owned finished signage                                                                      |
| `ScreenDesignVersion`      | Draft vs published design schema snapshots                                                            |
| `Screen`                   | Workspace-owned remote display (UI label: **TV**)                                                     |
| `ScreenDevice`             | Paired device credentials / hardware identity                                                         |
| `PairingSession`           | Short-lived pairing codes / sessions                                                                  |
| `Deployment`               | Publish-to-screen assignment                                                                          |
| `Playlist`                 | Workspace-owned ordered rotation of Screen Designs                                                    |
| `PlaylistVersion`          | Draft vs published playlist snapshot                                                                  |
| `PlaylistItem`             | Position, duration, loop count, transition, active flag                                               |
| `Schedule`                 | Timing window over a pinned published playlist                                                        |
| `AuditLog`                 | Immutable platform ops audit trail                                                                    |
| `SupportRequest`           | Customer Help → Admin support ticket                                                                  |
| `SupportRequestNote`       | Internal Admin note on a support request                                                              |
| `FeatureFlag`              | Global platform feature toggle                                                                        |
| `PlatformSetting`          | Typed platform-level setting key/value                                                                |
| `PlatformError`            | Safe operational error record (Player/widget/publish)                                                 |
| `Subscription`             | Cashier subscription (workspace_id, quantity = licences)                                              |
| `SubscriptionItem`         | Cashier subscription line items                                                                       |
| `BillingInvoice`           | Mirrored Stripe invoice metadata for Admin/customer                                                   |
| `BillingPlan`              | Persistent Super Admin–editable plan catalog                                                          |
| `WorkspaceBillingOverride` | Optional Enterprise/custom limit/feature overrides                                                    |
| `PlaybackEvent`            | Player playback / error telemetry (Phase 15)                                                          |
| `ScreenDailyStat`          | Daily Screen availability + playback aggregates                                                       |
| `Location`                 | Workspace-owned physical site grouping for Screens                                                    |

## Planned entities (not yet migrated)

_(none — Brand Kit and Locations shipped)_

`ScreenHeartbeat` is implemented (Phase 6) — see below.

## High-level relationships (Phase 7)

```
User
├── platform_role (nullable: super_admin | platform_admin)
├── is_admin (legacy; synced true only for Super Admin)
├── currentWorkspace (optional FK)
├── workspaceMemberships
└── templateFavourites (TemplateFavourite)

Workspace
├── members (WorkspaceMember)
│     └── User + WorkspaceRole
├── invitations (WorkspaceInvitation)
├── brandKit (BrandKit — one per workspace)
├── mediaAssets (MediaAsset)
├── locations (Location)
│     └── managers (location_user → User)
├── screenDesigns (ScreenDesign)
│     ├── versions (ScreenDesignVersion)
│     ├── publishedVersion (optional FK → ScreenDesignVersion)
│     ├── sourceTemplate (optional FK → Template)
│     └── sourceTemplateVersion (optional FK → TemplateVersion)
├── playlists (Playlist)
│     ├── versions (PlaylistVersion)
│     │     └── items (PlaylistItem → ScreenDesign + ScreenDesignVersion)
│     └── publishedVersion (optional FK → PlaylistVersion)
├── screens (Screen)
│     ├── location (optional FK → Location)
│     ├── devices (ScreenDevice)
│     ├── pairingSessions (PairingSession)
│     └── deployments (Deployment)
└── deployments (Deployment)
      ├── content_type: screen_design | playlist
      ├── screen
      ├── screenDesign / screenDesignVersion (when content_type = screen_design)
      └── playlist / playlistVersion (when content_type = playlist)

Template (always platform: workspace_id null)
├── versions (TemplateVersion)
├── publishedVersion (optional FK → TemplateVersion)
└── favourites (TemplateFavourite)
```

There are **no** workspace-owned Templates. Screen Designs sit under Workspace. **Use Template** deep-copies the published Template schema at creation time and records `source_template_id` / `source_template_version_id`. Designs reuse LayoutSchema v1 and reference `MediaAsset` by stable ID (`props.mediaAssetId` / canvas `background.mediaAssetId`). Later Template versions do not mutate existing Screen Designs.

## User platform fields

| Field           | Notes                                                                        |
| --------------- | ---------------------------------------------------------------------------- |
| `platform_role` | Nullable string enum: `super_admin`, `platform_admin`                        |
| `is_admin`      | Legacy boolean; kept in sync for Super Admin only via `assignPlatformRole()` |

Not fillable / mass-assignable. Distinct from Workspace roles.

## MediaAsset

Table: `media_assets`

| Field                                    | Notes                                              |
| ---------------------------------------- | -------------------------------------------------- |
| `workspace_id`                           | Required FK; isolation boundary                    |
| `type`                                   | Enum: image, video, text, logo, document, link     |
| `name`                                   | Display name (rename does not rewrite storage key) |
| `original_filename`                      | Client filename for reference                      |
| `storage_disk`                           | Laravel disk name (`config('media.disk')`)         |
| `storage_path`                           | Workspace-scoped generated path                    |
| `mime_type` / `extension` / `size_bytes` | File metadata                                      |
| `width` / `height`                       | Image/logo dimensions when extractable             |
| `duration_seconds`                       | Nullable until FFmpeg/processing phase             |
| `text_content`                           | Text media body                                    |
| `url`                                    | Link media URL                                     |
| `metadata`                               | PostgreSQL JSONB (optional extras)                 |
| `created_by` / `updated_by`              | User FKs                                           |

Indexes: `(workspace_id, type)`, `(workspace_id, created_at)`, `(workspace_id, name)`.

### Storage

- Local/public disk: `workspaces/{workspace_id}/media/{uuid}.{ext}`
- Disk is configurable via `MEDIA_DISK` / `config/media.php` for future S3/R2
- Binary bytes are **not** stored in PostgreSQL
- Replace keeps MediaAsset ID; old file deleted only after successful new store
- Duplicate physically copies files so deletes stay independent

### Delete

- Authorised by `MediaAssetPolicy` / `can_delete_media` (Owner, Admin, Designer, Content Manager)
- UI requires confirmation before destroy
- File-backed types (image, video, logo, document): DB row deleted, then stored file removed
- Text / Link: DB-only delete (no storage file)
- Missing storage file is handled safely
- Delete is **blocked** when `MediaAsset::hasDependencies()` is true (schema in this workspace’s Screen Design versions references `mediaAssetId`) — covers deployed content as well

### Screen Design references

Screen Designs reference `media_assets.id` via LayoutSchema (`props.mediaAssetId`, canvas background `mediaAssetId`). IDs stay stable across replace. Player media URLs are scoped to the active deployment schema.

AI-generated images that are saved become ordinary `media_assets` rows (`metadata.source = ai`). Temporary AI outputs are **not** MediaAssets until the user saves.

## AiGeneration

Table: `ai_generations`

Workspace-scoped AI generation history (text / image / design; video reserved).

| Field                                       | Notes                                                               |
| ------------------------------------------- | ------------------------------------------------------------------- |
| `workspace_id`, `user_id`                   | Required                                                            |
| `type`                                      | `text` \| `image` \| `design` \| `video`                            |
| `status`                                    | `pending` \| `processing` \| `completed` \| `failed` \| `cancelled` |
| `provider`, `model`                         | Provider name + model id                                            |
| `prompt`                                    | User prompt (trimmed; length-capped)                                |
| `options`, `output`                         | Small JSON metadata (not full provider payloads)                    |
| `error_code`, `error_message`               | User-safe failure info                                              |
| `idempotency_key`                           | Unique per workspace when set                                       |
| `temp_disk`, `temp_path`                    | Temporary generated image before Save to Media                      |
| `media_asset_id`, `screen_design_id`        | Optional results                                                    |
| `usage_input_tokens`, `usage_output_tokens` | Optional provider usage when genuinely returned                     |

Retention: temp files pruned by `ai:prune-generations`; old unused output metadata trimmed after `config('ai.temp.history_days')`.

## Template

Table: `templates`

| Field                            | Notes                                                          |
| -------------------------------- | -------------------------------------------------------------- |
| `workspace_id`                   | Always **null** for live Templates (platform-owned)            |
| `name`                           | Display name                                                   |
| `slug`                           | Optional unique key for starter/platform seeds (idempotent)    |
| `description`                    | Optional                                                       |
| `category`                       | `TemplateCategory` enum                                        |
| `industry`                       | Optional string                                                |
| `orientation`                    | `landscape` \| `portrait`                                      |
| `canvas_width` / `canvas_height` | Canvas size (e.g. 1920×1080 / 1080×1920)                       |
| `theme`                          | `TemplateTheme` enum                                           |
| `status`                         | `draft` \| `published` \| `archived`                           |
| `thumbnail_path`                 | Optional                                                       |
| `created_by` / `updated_by`      | User FKs                                                       |
| `published_version_id`           | Optional FK → `template_versions` (current published snapshot) |

Indexes: `(workspace_id, status)`, `orientation`, `category`, `industry`.

Managed only by platform staff (`/admin/templates`). Customers browse published Templates under `/app/templates`.

**Starter library:** `StarterTemplateCatalog` + `PlatformTemplatesSeeder` seed curated platform Templates (Masjid, Retail, Restaurant/Café, Corporate, Education, Healthcare, Hotel, Gym, Real Estate, Events, Community, general-purpose). Each has a stable `slug`, a published `TemplateVersion`, and a real LayoutSchema v1 (elements use `name` + `props`). Re-running the seeder updates schemas without duplicating masters. Card previews render `publishedVersion.schema` via shared `LayoutRenderer` (`mode="preview"`) — not static screenshots. Screen Design cards render the latest design version schema the same way. `thumbnail_path` may exist for future optimisation but is not the source of truth.

## TemplateVersion

Table: `template_versions`

| Field            | Notes                                                |
| ---------------- | ---------------------------------------------------- |
| `template_id`    | FK; cascade on template delete                       |
| `version_number` | Integer; unique per template                         |
| `schema`         | JSONB — LayoutSchema v1                              |
| `created_by`     | Optional user FK                                     |
| `published_at`   | Null while draft; set when that version is published |

Saving a draft after publish creates a new version; it does not overwrite the published snapshot.

## TemplateFavourite

Table: `template_favourites`

| Field         | Notes                          |
| ------------- | ------------------------------ |
| `user_id`     | FK; cascade on user delete     |
| `template_id` | FK; cascade on template delete |

Unique `(user_id, template_id)`.

## ScreenDesign

Table: `screen_designs` — workspace-owned.

| Field                            | Notes                                                                            |
| -------------------------------- | -------------------------------------------------------------------------------- |
| `workspace_id`                   | Required FK; isolation boundary; cascade on workspace delete                     |
| `name`                           | Display name                                                                     |
| `orientation`                    | `landscape` \| `portrait`                                                        |
| `canvas_width` / `canvas_height` | 1920×1080 landscape / 1080×1920 portrait (blank); copied from Template when used |
| `source_template_id`             | Optional FK → `templates` (nullOnDelete); provenance only                        |
| `source_template_version_id`     | Optional FK → `template_versions` (nullOnDelete); copied snapshot                |
| `status`                         | `draft` \| `published` \| `archived`                                             |
| `thumbnail_path`                 | Optional                                                                         |
| `created_by` / `updated_by`      | User FKs                                                                         |
| `published_version_id`           | Optional FK → `screen_design_versions`                                           |

Indexes: `(workspace_id, status)`, `(workspace_id, orientation)`, `source_template_id`.

Blank create uses `LayoutSchema::blank()`. Use Template deep-copies the published Template schema into version 1 and applies workspace Brand Kit values onto semantic `brandBinding` fields (`BrandKitSchemaApplier`); it does not stay linked for live updates.

Delete is **blocked** while the design has an active `Deployment`, or while any `playlist_items` row references the design / its versions (message includes playlist count).

## ScreenDesignVersion

Table: `screen_design_versions`

| Field              | Notes                                                |
| ------------------ | ---------------------------------------------------- |
| `screen_design_id` | FK; cascade on design delete                         |
| `version_number`   | Integer; unique per screen design                    |
| `schema`           | JSONB — LayoutSchema v1                              |
| `created_by`       | Optional user FK                                     |
| `published_at`     | Null while draft; set when that version is published |

Saving a draft after Publish Design creates a new version; it does not overwrite the published snapshot. Only a published version is deployable. Publish Design is **not** TV deployment.

## Screen

Table: `screens` — workspace-owned.

| Field                | Notes                                            |
| -------------------- | ------------------------------------------------ |
| `workspace_id`       | Required FK; isolation boundary                  |
| `name`               | Display name                                     |
| `location_id`        | Nullable FK → `locations` (`nullOnDelete`)       |
| `orientation`        | Nullable `landscape` \| `portrait`               |
| `operational_status` | `active` \| `inactive` (stored Operational axis) |
| `created_by`         | Optional user FK                                 |

**Derived (not stored):**

| Axis         | Values                             | Derivation                                                                                        |
| ------------ | ---------------------------------- | ------------------------------------------------------------------------------------------------- |
| Pairing      | connected / disconnected           | Active non-revoked `ScreenDevice` present                                                         |
| Network      | online / offline                   | Active device `last_seen_at` within `screens.online_threshold_seconds` (90s default)              |
| Health       | healthy / attention / offline      | `ScreenPresence::health()` over the three axes, active Deployment, playback state and orientation |
| Content sync | up_to_date / out_of_sync / unknown | Device `reported_deployment_id` vs the active Deployment                                          |

`last_seen_at` updates on the Player heartbeat (`POST /player/api/heartbeat`) and on authenticated polls (manifest/check).

## BrandKit

Table: `brand_kits` — one brand identity row per Workspace (used by design + AI prompts).

| Field                                                                                    | Notes                                                           |
| ---------------------------------------------------------------------------------------- | --------------------------------------------------------------- |
| `workspace_id`                                                                           | Required FK; **unique** (one kit per workspace); cascade delete |
| `name` / `tagline`                                                                       | Optional brand display name and tagline                         |
| `primary_color` / `secondary_color` / `accent_color` / `background_color` / `text_color` | Hex colour strings with defaults                                |
| `heading_font` / `body_font`                                                             | Allowed font names (see `BrandKit::allowedFonts()`)             |
| `logo_media_asset_id`                                                                    | Optional FK → `media_assets` (null on media delete)             |
| `secondary_logo_media_asset_id`                                                          | Optional FK → `media_assets` (null on media delete)             |

LayoutSchema may store optional semantic `brandBinding` on canvas background and element props (e.g. `brand.logo`, `brand.primary_color`). **Use Template** / Apply Brand Kit resolve bindings through `BrandKitSchemaApplier` without mutating the master Template.

## Location

Table: `locations` — workspace-owned physical sites.

| Field                      | Notes                                    |
| -------------------------- | ---------------------------------------- |
| `workspace_id`             | Required FK; cascade on workspace delete |
| `name`                     | Display name                             |
| `address_line*`            | Optional address lines                   |
| `city`/`region`/`postcode` | Optional locality fields                 |
| `country`                  | Defaults to `UK`                         |
| `timezone`                 | IANA timezone string                     |
| `notes`                    | Optional free text                       |
| `archived_at`              | Null while active; set when archived     |
| `created_by`               | Optional user FK                         |

Pivot `location_user` assigns Location Managers (`location_id` + `user_id`, unique). Location Managers only see/manage Screens whose `location_id` is in their assignments. Owner/Admin manage all Locations in the workspace. Delete is blocked while Screens remain attached — archive instead.

## ScreenDevice

Table: `screen_devices`

| Field                                | Notes                                                                                                                        |
| ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| `screen_id`                          | FK → screens                                                                                                                 |
| `device_identifier`                  | Hardware/client identity string                                                                                              |
| `device_token_hash`                  | HMAC-SHA256 of device token with `config('app.key')`                                                                         |
| `device_name`                        | Optional                                                                                                                     |
| `platform_meta`                      | Optional JSONB; may include `offline` readiness from Player heartbeats (`cache_ready`, `package_version`, `last_sync_at`, …) |
| `paired_at`                          | When paired                                                                                                                  |
| `revoked_at`                         | Null while active; set on unpair/revoke                                                                                      |
| `last_seen_at`                       | Updated on Player heartbeat and authenticated poll                                                                           |
| `player_version`                     | Last reported Player build                                                                                                   |
| `viewport_width` / `viewport_height` | Last reported device viewport                                                                                                |
| `reported_orientation`               | Last reported `landscape` \| `portrait`                                                                                      |
| `playback_state`                     | Last reported Player state (`ready`, `no_content`, `rendering`, `inactive`, `error`, `pairing`)                              |
| `last_error_code`                    | Last reported Player error code                                                                                              |
| `reported_deployment_id`             | Deployment the device says it is rendering (content sync)                                                                    |

Plain device token is never stored. Issued once via `PairingSession.pending_device_token_ciphertext` after claim.

**Player offline package (client):** not a DB table. Built by `PlayerOfflinePackageBuilder` and stored in the Player's IndexedDB (Dexie). Contains versioned current content, precomputed Schedule windows for `player.offline_horizon_hours`, fallback Deployment, and the Media asset list for Cache Storage.

## ScreenHeartbeat

Table: `screen_heartbeats` — append-only Player check-in history for troubleshooting.

| Field                                | Notes                                   |
| ------------------------------------ | --------------------------------------- |
| `screen_id`                          | FK → screens                            |
| `screen_device_id`                   | FK → screen_devices                     |
| `recorded_at`                        | Server timestamp of the heartbeat       |
| `player_version`                     | Reported Player build                   |
| `user_agent`                         | Truncated request user agent            |
| `viewport_width` / `viewport_height` | Reported viewport                       |
| `orientation`                        | Reported `landscape` \| `portrait`      |
| `deployment_id`                      | Deployment the device reports rendering |
| `screen_design_version_id`           | Optional reported version               |
| `playback_state`                     | Reported Player state                   |
| `error_code`                         | Reported Player error code              |
| `metadata`                           | Optional JSONB                          |

Current Online/Offline reads `screen_devices.last_seen_at` — never a scan of this table. Rows older than `screens.heartbeat_retention_days` (7) are removed by `screens:prune-heartbeats` (scheduled daily).

## PairingSession

Table: `pairing_sessions`

| Field                                       | Notes                                                                    |
| ------------------------------------------- | ------------------------------------------------------------------------ |
| `public_id`                                 | ULID (26 chars); used in QR URL                                          |
| `code_hash`                                 | HMAC-SHA256 of code `DZ-XXXX` (app key); plain code not stored           |
| `expires_at`                                | 10 minutes from creation                                                 |
| `claimed_at` / `claimed_by`                 | Set on successful claim                                                  |
| `screen_id`                                 | Set when claimed                                                         |
| `device_meta` / `ip_address` / `user_agent` | Optional request metadata                                                |
| `pending_device_token_ciphertext`           | One-time encrypted token for player poll after claim; cleared after read |

Single-use; expired or already-claimed sessions are rejected. Rate limited on create/poll/claim.

## Playlist

Table: `playlists` — workspace-owned.

| Field                       | Notes                                                              |
| --------------------------- | ------------------------------------------------------------------ |
| `workspace_id`              | Required FK; isolation boundary                                    |
| `name` / `description`      | Description nullable                                               |
| `orientation`               | Nullable `landscape` \| `portrait` (set from first item / publish) |
| `status`                    | `draft` \| `published` \| `archived`                               |
| `created_by` / `updated_by` | User FKs                                                           |
| `published_version_id`      | Optional FK → `playlist_versions`                                  |
| `published_at`              | When the current published version was finalised                   |

Indexes: `(workspace_id, status)`, `orientation`.

Playlists are **single-orientation**. Items may only reference **published** `ScreenDesignVersion` rows from the same workspace. The same design may appear multiple times. Defaults: `config/playlists.php` / `PlaylistDefaults` (default duration 10s, min 1, max 3600).

Delete is **blocked** while the playlist has an active `Deployment`.

## PlaylistVersion

Table: `playlist_versions`

| Field            | Notes                                                |
| ---------------- | ---------------------------------------------------- |
| `playlist_id`    | FK; cascade on playlist delete                       |
| `version_number` | Integer; unique per playlist                         |
| `created_by`     | Optional user FK                                     |
| `published_at`   | Null while draft; set when that version is published |

Saving a draft after Publish Playlist creates a new version and copies items; it does not overwrite the published snapshot. Published versions are immutable. Schedules pin a specific published `PlaylistVersion`.

## PlaylistItem

Table: `playlist_items` — belong to a **version**, not the mutable playlist root.

| Field                      | Notes                                                      |
| -------------------------- | ---------------------------------------------------------- |
| `playlist_version_id`      | FK; cascade on version delete                              |
| `screen_design_id`         | FK; restrict on design delete                              |
| `screen_design_version_id` | FK; pinned published version; restrict on version delete   |
| `position`                 | 1-based order within the version                           |
| `duration_seconds`         | Positive integer within configured min/max (per play)      |
| `loop_count`               | How many times the item plays before advancing (1–99)      |
| `transition`               | `none` \| `fade` \| `slide_left` \| `slide_right`          |
| `transition_speed`         | `fast` \| `normal` \| `slow`                               |
| `is_active`                | Inactive items stay configured but are skipped in playback |

Runtime for an active item is `duration_seconds × loop_count` via `PlaylistRuntimeCalculator`. Transitions are not included in totals.

Indexes: `(playlist_version_id, position)`, `screen_design_id`, `screen_design_version_id`.

## Deployment

Table: `deployments`

| Field                           | Notes                                                              |
| ------------------------------- | ------------------------------------------------------------------ |
| `workspace_id`                  | Isolation boundary                                                 |
| `screen_id`                     | Target screen                                                      |
| `content_type`                  | `screen_design` (default) \| `playlist`                            |
| `screen_design_id`              | Source design (nullable when playlist)                             |
| `screen_design_version_id`      | Published design version pinned at deploy (nullable when playlist) |
| `playlist_id`                   | Source playlist (nullable when design)                             |
| `playlist_version_id`           | Published playlist version pinned at deploy (nullable when design) |
| `status`                        | `pending` \| `active` \| `failed` \| `superseded` \| `revoked`     |
| `deployed_by`                   | Optional user FK                                                   |
| `deployed_at` / `superseded_at` | Timestamps                                                         |

Only published Screen Design / Playlist versions are deployable via `PublishContentToScreens`. New Active Deployments supersede prior Active ones per screen. Later content edits do not mutate pinned versions. Offline Screens may still receive Active Deployments; Live/Updating/Waiting on `/app/publishing` comes from heartbeat sync. History **Republish** creates a new Deployment from the historical pinned version.

## Schedule

Table: `schedules` — workspace-owned. Decides **when** content plays; a `Deployment` is the always-on fallback.

| Field                       | Notes                                                                                                                             |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `workspace_id`              | Required FK; isolation boundary                                                                                                   |
| `name` / `description`      | Description nullable                                                                                                              |
| `playlist_id`               | Nullable FK → `playlists`; restrict on delete                                                                                     |
| `playlist_version_id`       | Nullable FK → `playlist_versions`; the pinned **published** version                                                               |
| `timezone`                  | IANA identifier; windows are wall-clock in this zone                                                                              |
| `start_date` / `end_date`   | Both nullable; open-ended when null                                                                                               |
| `start_time` / `end_time`   | Wall-clock times; `start_time > end_time` means the window crosses midnight                                                       |
| `days_of_week`              | JSONB list of ISO weekdays (1 = Monday … 7 = Sunday)                                                                              |
| `priority`                  | Integer **1–10**; **10 = highest**, default **5**. Bounds in `config/schedules.php`. Legacy values >10 were normalised onto 1–10. |
| `status`                    | `draft` \| `active` \| `paused` \| `archived`                                                                                     |
| `activated_at`              | Last activation; tie-breaks equal priorities                                                                                      |
| `created_by` / `updated_by` | User FKs                                                                                                                          |

Indexes: `(workspace_id, status)`, `(workspace_id, priority)`, `start_date`, `end_date`, `playlist_id`, `playlist_version_id`, `activated_at`.

Drafts may be incomplete, but activation requires a published playlist version with at least one active item, at least one screen, a valid timezone, at least one weekday, a non-zero window, and an in-range priority.

**Ended** is a **derived** display status — an `active` schedule whose local date is past `end_date`. It is never stored, and it is unrelated to a Screen being operationally Active. An ended schedule fails the date check and therefore never plays.

Pinning is deliberate: publishing playlist v3 does not reach an existing schedule until someone re-picks the playlist. Playlist delete is **blocked** while any schedule references it.

### schedule_screen

Pivot table with timestamps; unique on `(schedule_id, screen_id)`, cascade on either delete.

### Evaluation and precedence

`App\Support\Schedules\ScheduleEvaluator` is the only place that decides whether a schedule is playing. Windows are inclusive of the start minute and exclusive of the end minute (`>= start && < end`), so a 07:00–12:00 window hands over to a 12:00–15:00 window cleanly. For a window that crosses midnight, the evening portion is anchored to today's local date and the early-morning tail to **yesterday's**, so weekday and date eligibility follow the day the window opened. Precedence is highest `priority`, then the most recent `activated_at`, then the higher `id`. Overlap **warnings** (never blockers) come from `DetectScheduleConflicts` — same TV + overlapping days/dates/times; same-priority overlaps are flagged more strongly.

`App\Support\Screens\ScreenContentResolver` is the only place that ranks sources: matching Schedule → active Deployment → none. It returns a `ResolvedScreenContent` carrying the source, the pinned version, the window in UTC, allowed media asset ids, and a `versionLabel` (`sch-{id}-pv-{versionId}-{windowStart}` or `dep-{id}`) that the polling Player compares to detect a changeover.

## Workspace fields

- `name`, `slug` (unique), `industry`, `country`, `timezone` (IANA), `logo_path` nullable

## Invitation security

- Store `token_hash` (SHA-256 of plain token); never store the raw token
- Track `expires_at`, `accepted_at`, `revoked_at`
- Accept requires matching authenticated email

## JSON configuration (JSONB)

- `media_assets.metadata` — optional extras
- `template_versions.schema` — **LayoutSchema v1** (`schemaVersion`, `canvas`, `theme`, `elements[]`; optional `brandBinding` on canvas/props)
- `screen_design_versions.schema` — same LayoutSchema v1
- Widget elements: `type: "widget"` with `props.widgetType` (`clock`, `countdown`, `weather`, `news`, `calendar`, `alert`, `info_card`, `embed`) and `props.config`. Embed config may include resolved `kind` (`youtube`|`vimeo`|`hls`|`video`|`website`|`blocked`) plus playback flags; URL validation/SSRF via `EmbedUrlValidator` / `SafeRemoteUrl`. External widget payloads are **not** stored in the Design schema; Player offline packages may include a `widgetData` cache map.
- `screen_devices.platform_meta` / `pairing_sessions.device_meta` — optional device metadata

Shared across platform Template Builder / Preview, customer Screen Design Editor, and TV Player. Do not invent a second layout format.

## Phase 12 — Platform ops entities

### audit_logs

Immutable operational audit rows: `actor_user_id` (nullable), `action`, `entity_type`, `entity_id`, `workspace_id` (nullable), `metadata` (JSONB, never secrets), `created_at`. No routine edit/delete UI. Written via `App\Support\Platform\AuditLogger`.

### support_requests / support_request_notes

Customer Help (`/app/help`) creates a `SupportRequest` with workspace + user association. Admin `/admin/support` updates status/priority and may add internal notes. Statuses: open, in_progress, resolved, closed.

### feature_flags

Global toggles (`key`, `name`, `description`, `enabled`). Seeded examples only (`widgets_enabled`, `offline_player_enabled`) — do not disable production features by default. Super Admin mutates; Admin (platform) read-only. Changes audited.

### platform_settings

Typed key/value platform settings (heartbeat interval, offline threshold, widget cache defaults, support email, etc.). Secrets stay in env/config. Super Admin mutates with validation; audited.

### platform_errors

Lightweight operational error records (category, optional workspace/screen/deployment, safe message, metadata, occurred_at, resolved_at). Not a replacement for Sentry/server logs. Staff may mark resolved; history is retained.

## Phase 13 — Billing entities

### workspaces (Cashier columns)

`stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`. Workspace implements `Billable`. `stripeEmail()` uses the Owner member’s email.

### subscriptions / subscription_items

Cashier tables keyed by `workspace_id`. `quantity` = purchased Screen licences. Stripe is authoritative for payments; local rows sync via verified webhooks.

### billing_invoices

Minimal mirror of Stripe invoices for customer and Admin lists: `stripe_invoice_id`, `number`, `status`, `currency`, `total`, hosted/PDF URLs, `billed_at`. Upserted idempotently by `SyncBillingInvoice`.

### billing_plans

Persistent commercial catalog (`BillingPlan`): `key`, display name/tagline, currency, monthly/annual amounts, Stripe Price IDs, screen/storage/team limits, `features` JSON, badges, `active`/`public`, `sort_order`, `enterprise`. Seeded idempotently from `config/billing_plans.php` via `BillingPlanSeeder` (`firstOrCreate` — does not overwrite Super Admin edits). Optional `workspace_billing_overrides` for Enterprise/custom agreements.

### Entitlement

`App\Support\Billing\BillingEntitlement` is the only place that decides `canPairScreen`, `canPublish`, licence counts, storage/team limits, and `hasFeature`. Plan definitions resolve through `BillingPlanCatalog` from **`billing_plans` DB rows when present**, otherwise `config/billing_plans.php` (Starter / Business / Enterprise; no Pro). Feature keys are catalogued in `BillingFeatureCatalog`. Workspace overrides beat plan defaults when set. `config/billing.php` + `BILLING_ENFORCE` control gates; legacy `billing.catalog` amounts mirror Starter. Changing a plan’s Stripe Price creates a **new** Price reference — existing subscribers are not auto-migrated.
