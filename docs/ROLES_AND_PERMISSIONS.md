# RMSignage — Roles & Permissions

All authorization must be enforced **server-side**. UI hiding alone is not security.

**Current (Phase 15 + Brand Kit / Locations):** Workspace role matrix + Media/Template/Screen Design/Playlist/Schedule/Screen/Deployment/Location/Brand Kit policies; dedicated `/admin` shell (+ Screens, Screen Health, Publishing Jobs). Locations are under `/app/locations`; Brand Kit under `/app/brand-kit`.

Customer-facing copy says **Business**, not Workspace. The internal model remains `Workspace`.

Assignable Business roles in Team invites and role changes are **Owner** (not assignable; created with the Business), **Admin**, **Designer**, and **Content Manager**. `viewer` and `location_manager` remain on the enum so existing rows are not rewritten or privilege-escalated, but they are not offered in the Team UI and new invites reject them. Locations themselves stay; only the Location Manager role is deprecated from normal use.

Platform staff (`super_admin` and `platform_admin`) land on `/admin/dashboard` after login. Super Admin is the only role that can soft-delete a Business (`DELETE /admin/workspaces/{workspace}`, name confirmation, subscription `cancelNow` when one is active, device tokens revoked). Platform Admin cannot.

## Platform roles (implemented)

Enum: `App\Enums\PlatformRole` on `users.platform_role`

| Role           | Value            | User-facing label  | Access                                                                     |
| -------------- | ---------------- | ------------------ | -------------------------------------------------------------------------- |
| Super Admin    | `super_admin`    | Super Admin        | Full `/admin/*`; manage platform Templates; legacy `users.is_admin` = true |
| Platform Admin | `platform_admin` | **Platform Admin** | `/admin/*` operational access; Templates; `is_admin` remains false         |

- Assigned via `User::assignPlatformRole()`, which syncs `is_admin` **only** for Super Admin.
- Middleware `EnsureUserIsAdmin` allows any platform staff (`isPlatformStaff()`).
- Platform roles are **not** Business (Workspace) roles. **Business Admin ≠ Platform Admin.**
- Business roles **never** grant platform Template management or `/admin/*`.
- **Platform Admin** = RMSignage staff who work with/for the Super Admin. **Business Admin** = company team role.
- Production staff accounts (no Business membership):

```bash
php artisan rmsignage:create-super-admin --name="Ahmed" --email="you@example.com"
php artisan rmsignage:create-platform-admin --name="Sarah" --email="sarah@example.com"
```

Password is prompted hidden (never a CLI flag). Accounts are email-verified immediately. Re-running is idempotent; use `--reset-password` to rotate credentials. Do **not** use `LocalDevSeeder` / `admin@dz.local` in production.

### Capability split (`PlatformPermissions`)

| Capability                                                                   | Super Admin | Platform Admin |
| ---------------------------------------------------------------------------- | ----------- | -------------- |
| Access `/admin/*`                                                            | ✓           | ✓              |
| Manage Templates                                                             | ✓           | ✓              |
| View ops (TV Health, Publishing Jobs, System Health, Errors, Support, Audit) | ✓           | ✓              |
| Manage Feature Flags                                                         | ✓           | —              |
| Manage Platform Settings                                                     | ✓           | —              |
| Manage Billing Plans / Stripe catalog                                        | ✓           | view only      |
| Mutate customer subscriptions                                                | ✓           | —              |
| Soft-delete Business                                                         | ✓           | —              |
| Assign / remove platform roles                                               | ✓           | —              |

Sidebar hides Feature Flags and Settings for Platform Admin; backend policies still enforce.

## Workspace roles (implemented)

Enum: `App\Enums\WorkspaceRole` — methods drive `WorkspacePermissions` Inertia props and policies/UI.

| Capability                   | Owner          | Workspace Admin | Designer | Content Manager | Location Manager | Viewer |
| ---------------------------- | -------------- | --------------- | -------- | --------------- | ---------------- | ------ |
| Manage workspace / team      | ✓              | ✓               | —        | —               | —                | —      |
| View billing                 | ✓              | ✓               | —        | —               | —                | —      |
| Manage billing               | ✓ (Owner only) | —               | —        | —               | —                | —      |
| View analytics               | ✓              | ✓               | ✓        | ✓               | ✓                | ✓      |
| View Media                   | ✓              | ✓               | ✓        | ✓               | ✓                | ✓      |
| Manage / delete Media        | ✓              | ✓               | ✓        | ✓               | —                | —      |
| Browse published Templates   | ✓              | ✓               | ✓        | ✓               | ✓                | ✓      |
| Manage master Templates      | —              | —               | —        | —               | —                | —      |
| Manage Screen Designs        | ✓              | ✓               | ✓        | ✓               | —                | —      |
| Manage playlists / schedules | ✓              | ✓               | —        | ✓               | —                | —      |
| Publish content (to Screens) | ✓              | ✓               | —        | ✓               | —                | —      |
| Manage screens / locations   | ✓              | ✓               | —        | —               | ✓                | —      |
| View Screens                 | ✓              | ✓               | ✓        | ✓               | ✓                | ✓      |
| Manage Brand Kit             | ✓              | ✓               | ✓        | —               | —                | —      |
| View Brand Kit               | ✓              | ✓               | ✓        | ✓               | ✓                | ✓      |

`can_manage_templates` / `can_publish_templates` are always false for every workspace role. Screen Design, Playlist, Schedule, Screen, Location, Brand Kit, and Publish-to-Screen are enforced (`ScreenDesignPolicy`, `PlaylistPolicy`, `SchedulePolicy`, `ScreenPolicy`, `LocationPolicy`, `BrandKitPolicy`, `DeploymentPolicy`). Location Managers are scoped to assigned Locations via `location_user` and `LocationAccess`.

**Designer cannot publish to TV.** Designer may manage Screen Designs (including Publish Design) and **view** Screens, but `canPublishContent` / `canManageScreens` are false.

### Owner

Can: workspace settings; invite/manage members; change non-owner roles; remove non-owner members; full Media; browse Templates; Use Template; manage Screen Designs; manage Playlists (create/edit/publish/deploy); publish to Screens; manage Screens (pair/rename/status/unpair); manage Schedules (create/edit/activate/pause/archive); manage Locations; manage Brand Kit; view + manage billing; view analytics.

Cannot: transfer ownership (blocked); leave if sole Owner; be removed by Admin; manage master Templates; access `/admin/*`.

### Admin (Workspace Admin)

Can: workspace settings; invite/manage non-owner members; full Media; browse Templates; Use Template; manage Screen Designs; manage Playlists; publish to Screens; manage Screens; manage Schedules; manage Locations; manage Brand Kit; view billing; view analytics.

Cannot: modify/remove Owner; promote to Owner; manage billing (Owner only); manage master Templates; access `/admin/*`.

User-facing label is **Workspace Admin** (enum value remains `admin`).

### Designer

Can: full Media; browse published platform Templates (preview / favourite / Use Template); manage Screen Designs (create, edit, Publish Design, duplicate, delete); **manage Brand Kit**; **view** Screens (read-only).

Cannot: publish content to Screens/TVs; manage Screens (pair/rename/status/unpair); manage team/workspace; billing; analytics; manage playlists (view only) / schedules; locations; master Templates; `/admin/*`.

Designer is **not** a Template editor. Templates stay platform-owned; customers browse/use. Screen Designs are the customer design surface.

### Content Manager

Can: full Media; browse Templates; Use Template; manage Screen Designs; manage Playlists (create/edit/publish/deploy); **publish content to Screens**; manage Schedules; **view** Brand Kit (read-only).

Cannot: manage Screens/locations; team/workspace; billing; analytics; manage Brand Kit; master Templates; `/admin/*`.

### Location Manager

Can: view Media (read-only); browse Templates; view Screen Designs (list + editor read-only); **manage Screens** at assigned Locations (pair/rename/status/unpair); manage assigned Locations; **view** Brand Kit (read-only — `canViewBrandKit` is true for all workspace roles).

Cannot: manage Media; publish to Screens; team; billing; create/edit/publish/delete Screen Designs; manage playlists (view only); manage Brand Kit; master Templates; `/admin/*`.

### Viewer

Read-only: view Media; browse published Templates; favourite; view Screen Designs (list + editor read-only); view Playlists (list + editor read-only); view Screens; view Brand Kit.

Cannot: modify workspace/team/Media; billing; create/edit/publish/delete Screen Designs or Playlists; manage Screens; manage Brand Kit; or publish-to-screen actions.

## Shared Inertia props (`workspace.permissions`)

From `App\Support\WorkspacePermissions`:

- Team/workspace: `can_manage_workspace`, `can_manage_team`, `can_invite`, `can_update_roles`, `can_remove_members`, `can_leave`
- Billing / insights: `can_view_billing`, `can_manage_billing`, `can_view_analytics` (all Workspace roles may view Analytics; Location Manager included for Screen ops visibility)
- Media: `can_view_media`, `can_manage_media`, `can_delete_media`
- AI: `can_use_ai` (Owner, Admin, Designer, Content Manager — manage media **or** manage screen designs). Viewer / Location Manager cannot generate.
- Templates: `can_view_templates` (all roles); `can_manage_templates` / `can_publish_templates` always **false**
- Screen Designs: `can_manage_screen_designs` (Owner, Admin, Designer, Content Manager). All members can **view** list/editor (`viewAny` / `view`).
- Playlists: `can_manage_playlists` (Owner, Admin, Content Manager) — Designer/Location Manager/Viewer are read-only on playlists
- Screens / publishing: `can_manage_screens` (Owner, Admin, Location Manager), `can_publish_content` (Owner, Admin, Content Manager)
- Schedules: `can_manage_schedules` (Owner, Admin, Content Manager) — mirrors `can_manage_playlists`; every member may view
- Locations: `can_manage_locations` (same as screens); Location Manager scoped to `location_user` assignments
- Brand Kit: `can_manage_brand_kit` (Owner, Admin, Designer). View is all roles via `canViewBrandKit()` (always true) / `BrandKitPolicy::view`

Customer sidebar filters by workspace permissions: **Settings** (hub with Billing tab), **Analytics**, **Create Design** CTA, and Display items (**Playlists**, **Publishing**, **Schedules**, **Paired TVs**, **Locations**). Billing is not a separate sidebar item — it lives under Settings → Billing. **Admin Portal** appears only for platform staff (`platform_role` / `is_admin`).

## Media permissions (Phase 2)

Enforced by `MediaAssetPolicy` + Form Requests. Cross-workspace Media IDs resolve as **404** where practical.

| Action                                    | Who                                     |
| ----------------------------------------- | --------------------------------------- |
| View                                      | All workspace roles                     |
| Create / edit / replace / duplicate       | Owner, Admin, Designer, Content Manager |
| Delete (with confirmation)                | Owner, Admin, Designer, Content Manager |
| Delete blocked if used in a Screen Design | Same roles; validation error, not 403   |
| Download (file-backed only)               | Anyone who can view + asset has a file  |

## Template permissions (Phase 3)

Templates are **platform-owned**. Customers browse/use only — no Create / My Templates / customer builder.

| Action                                               | Who                                                                                                                 |
| ---------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Browse / search / filter / preview published         | Any workspace member (`can_view_templates`)                                                                         |
| Favourite                                            | Anyone who can view                                                                                                 |
| Use Template → Screen Design                         | Owner, Admin, Designer, Content Manager (`useTemplate` + `create` Screen Design); published platform Templates only |
| Create / edit / publish / archive / delete Templates | Super Admin, Admin only (`/admin/templates`)                                                                        |

## Screen Design permissions (Phase 4)

Enforced by `ScreenDesignPolicy` + Form Requests. Cross-workspace IDs resolve as **404**.

| Action                                                           | Who                                                                   |
| ---------------------------------------------------------------- | --------------------------------------------------------------------- |
| View list / open editor (read)                                   | All workspace members                                                 |
| Create blank / Use Template / edit / rename / duplicate / delete | Owner, Admin, Designer, Content Manager (`can_manage_screen_designs`) |
| Publish Design (finalise version; not send to TV)                | Same manage roles                                                     |
| Delete blocked while actively deployed                           | Same manage roles; validation error                                   |

Location Manager and Viewer: read-only. `can_edit` is false in the editor.

## Screen & publish permissions (Phase 5)

Enforced by `ScreenPolicy` / `DeploymentPolicy` + actions. Cross-workspace IDs resolve as **404**.

| Action                                            | Who                                                            |
| ------------------------------------------------- | -------------------------------------------------------------- |
| View Screens list / detail                        | All workspace members                                          |
| Pair / rename / set status / unpair (manage)      | Owner, Admin, Location Manager (`can_manage_screens`)          |
| Publish to Screens (design-first or screen-first) | Owner, Admin, Content Manager (`can_publish_content`)          |
| Designer                                          | View Screens only — **cannot** publish to TV or manage pairing |

**Publish Design ≠ Publish to Screen.** Publish Design finalises a version; Publish to Screen creates/activates a `Deployment` of a published version only.

## Playlist permissions (Phase 7)

Enforced by `PlaylistPolicy` + actions. Cross-workspace IDs resolve as **404**.

| Action                                                                | Who                                                    |
| --------------------------------------------------------------------- | ------------------------------------------------------ |
| View list / open editor (read) / preview                              | All workspace members                                  |
| Create / edit draft / reorder / duplicate / archive / delete          | Owner, Admin, Content Manager (`can_manage_playlists`) |
| Publish Playlist (finalise version; not send to TV)                   | Same manage roles                                      |
| Publish Playlist to Screens                                           | Owner, Admin, Content Manager (`can_publish_content`)  |
| Delete blocked while actively deployed                                | Same manage roles; validation error                    |
| Screen Design delete blocked while referenced by any playlist version | Design manage roles; message includes playlist count   |

Designer, Location Manager, and Viewer: read-only on playlists. Designer may manage Screen Designs but **not** playlists (matches `canManagePlaylists`).

**Publish Playlist ≠ Publish Playlist to Screen ≠ Schedule.** Publishing a playlist finalises a version, Publish to Screen deploys it always-on, and a Schedule plays it only inside a timing window.

## Schedules (`/app/schedules`)

`SchedulePolicy` mirrors `PlaylistPolicy`: every workspace member may view schedules, and all mutations require `canManageSchedules()`.

| Capability                                                   | Roles                                                  |
| ------------------------------------------------------------ | ------------------------------------------------------ |
| View schedules library, calendar, preview, conflict warnings | All workspace members                                  |
| Create / edit / duplicate schedules                          | Owner, Admin, Content Manager (`canManageSchedules`)   |
| Activate / pause / archive / delete schedules                | Same manage roles                                      |
| Delete blocked while the schedule is live                    | Same manage roles; pause or archive first              |
| Playlist delete blocked while referenced by any schedule     | Playlist manage roles; message includes schedule count |

Designer, Location Manager, and Viewer: read-only on schedules. Designer may manage Screen Designs but **not** schedules (matches `canManageSchedules`).

## Brand Kit permissions

Enforced by `BrandKitPolicy` + `UpdateBrandKitRequest`. One kit per workspace; cross-workspace IDs resolve as **404**.

| Action                                      | Who                                                   |
| ------------------------------------------- | ----------------------------------------------------- |
| View `/app/brand-kit`                       | All workspace roles (`canViewBrandKit` — always true) |
| Update brand name / colours / fonts / logos | Owner, Admin, Designer (`canManageBrandKit`)          |

Content Manager, Location Manager, and Viewer are view-only. Location Manager scope via `location_user` does **not** restrict Brand Kit view — Brand Kit is workspace-wide.

## Platform Admin surface (`/admin`)

Dedicated **AdminSidebar** (not customer `AppSidebar`): amber **RMSignage Admin** branding, role label (**Super Admin** / **Platform Admin**), **Back to App**, Figma-aligned nav.

### Super Admin vs Platform Admin

| Capability                                            | Super Admin | Platform Admin  |
| ----------------------------------------------------- | ----------- | --------------- |
| Overview, Workspaces, Users, Screens (inspect)        | Yes         | Yes             |
| Templates CRUD + Builder                              | Yes         | Yes             |
| Screen Health, Publishing Jobs, System Health, Errors | Yes         | Yes             |
| Support Requests (read/update)                        | Yes         | Yes             |
| Audit Log (read-only)                                 | Yes         | Yes             |
| Feature Flags (mutate)                                | Yes         | No (nav hidden) |
| Platform Settings (mutate)                            | Yes         | No (nav hidden) |
| Assign / remove platform roles                        | Yes         | No              |
| Subscriptions / Invoices (inspect)                    | Yes         | Yes             |
| Change Workspace plan (Starter/Business via Cashier)  | Yes         | No              |
| Edit plan catalog (`/admin/subscriptions/plans`)      | Yes         | Read-only       |
| Sync Stripe Prices / migrate subscribers to new Price | Yes         | No              |

Gates live in `App\Support\Platform\PlatformPermissions`. Middleware `EnsureUserIsAdmin` blocks non-staff from all `/admin/*`. Business Owner/Admin never grants Platform Admin.

### Workspace billing permissions

| Capability                                     | Owner | Workspace Admin | Others |
| ---------------------------------------------- | ----- | --------------- | ------ |
| View `/app/billing`                            | Yes   | Yes             | No     |
| Checkout / Portal / quantity / cancel / resume | Yes   | No              | No     |

### Implemented (Phase 12–13)

- Overview — real counts + attention screens, recent deployments/workspaces/audit (no MRR/ARR)
- Customers — Workspaces / Users / Screens list + detail (Workspace detail shows subscription summary)
- Content — Templates
- Operations — Screen Health, Publishing Jobs, System Health, Errors
- Support — Support Requests, Audit Log
- Platform — Feature Flags, Settings
- Billing — `/admin/subscriptions/plans` (Super Admin edits catalog; Platform Admin read-only), `/admin/subscriptions` and `/admin/invoices` use Cashier + mirrored Stripe invoices; truthful empty when Stripe is unconfigured. Super Admin may change Starter/Business subscriptions via Cashier when Stripe is configured; Enterprise remains Contact Sales. Price catalog changes do **not** auto-rebill existing subscribers.

Critical mutations (platform roles, flags, settings, support status, template publish, billing changes) write immutable `audit_logs` via `AuditLogger`. Device tokens / password hashes / secrets / card data are never exposed in Admin payloads.

Do **not** grant `/admin/*` via Workspace Owner or Workspace Admin. Global platform Media management is **not** implemented.

## Isolation rules

1. Workspace members only act within membership-validated current workspace.
2. Switching workspace requires membership.
3. Invitation acceptance requires email match + valid pending token.
4. MediaAsset rows are always scoped by `workspace_id`.
5. Templates are platform-scoped (`workspace_id` null); customers only see **published** ones.
6. ScreenDesign / Playlist / Screen / Deployment rows are always scoped by `workspace_id`.
7. Playlist items may only pin published Screen Design versions from the same workspace.
8. Player media access is scoped to the active deployment content (design schema or playlist item schemas) for that device’s screen.
9. Location Managers are scoped to assigned Locations (`location_user`) for Location and Screen access.
