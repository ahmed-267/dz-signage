# Production Operations

Concise runbook for deploying and operating RMSignage in production.

## Required services

| Service                   | Role                                             |
| ------------------------- | ------------------------------------------------ |
| PHP 8.4+ / Laravel        | Application                                      |
| PostgreSQL                | Primary database                                 |
| Redis                     | Cache, sessions (recommended), queues if used    |
| Persistent object storage | Customer Media (`FILESYSTEM_DISK` not ephemeral) |
| Web server (Nginx/Caddy)  | TLS termination + PHP-FPM / Octane               |
| Cron                      | `* * * * * php artisan schedule:run`             |
| Queue worker              | Only if `QUEUE_CONNECTION` is not `sync`         |

## External services

| Service         | Env                 | Notes                      |
| --------------- | ------------------- | -------------------------- |
| Stripe          | `STRIPE_*`, Cashier | Webhook signature required |
| AI provider     | `AI_*` / OpenAI     | Server-side only; optional |
| Mail            | `MAIL_*`            | Password reset, invites    |
| Weather / feeds | Widget config       | SSRF-safe fetchers         |
| Error tracking  | Optional Sentry DSN | Do not commit secrets      |

## Health probes

| Path                   | Purpose                               |
| ---------------------- | ------------------------------------- |
| `GET /health`          | Liveness (`{"status":"ok"}`)          |
| `GET /ready`           | Readiness (DB; Redis when configured) |
| `GET /up`              | Laravel framework health              |
| `/admin/system-health` | Authenticated diagnostics (cached)    |

Do not expose System Health publicly.

## Deployment checklist

1. Backup PostgreSQL + Media storage.
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. Set production `.env` (`APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`)
5. `php artisan migrate --force`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. Restart PHP-FPM / Octane, queue workers, confirm cron.
8. Smoke: login → publish → pair Player → heartbeat → `/app/analytics`
9. Confirm Stripe webhook + billing portal in live/test mode intentionally.

## Scheduler jobs

| Command                            | Cadence         | Notes              |
| ---------------------------------- | --------------- | ------------------ |
| `screens:prune-heartbeats`         | Daily           |                    |
| `ai:prune-generations`             | Daily           |                    |
| `analytics:aggregate-daily`        | Daily           |                    |
| `analytics:prune`                  | Daily           |                    |
| `rmsignage:refresh-demo-telemetry` | Every 2 minutes | Demo Business only |
| Scheduler heartbeat cache          | Every minute    |                    |

## Demo Account

Dedicated production demo Business (**North & Bean Café**) for demos and QA.

**Do not** run `php artisan db:seed` or `dz:seed-demo` against production.

### Environment

```bash
RMSIGNAGE_DEMO_EMAIL=demo@rmsignage.com
RMSIGNAGE_DEMO_PASSWORD=...   # set in secrets; never commit
```

### Seed (idempotent, scoped)

```bash
php artisan rmsignage:seed-demo-account --allow-production
```

Without `--allow-production`, the command refuses when `APP_ENV=production`.

Safety guarantees:

- Touches **only** the designated demo email + workspace slug (`rmsignage-demo-north-bean`)
- Safe to re-run (no duplicate Media / Screens / TVs / Playlists / Schedules / analytics)
- No Stripe customers, subscriptions, invoices, or charges
- No usable Player device tokens (hashes only; `credentials_issued=false`)
- TV presence refresher (`rmsignage:refresh-demo-telemetry`) is similarly scoped

Normal sign-up still creates a clean empty Business — the mature demo is not onboarding default.

## Platform staff accounts

Production databases start empty — local `.local` staff accounts are never copied. Create platform staff explicitly.

### Laravel Cloud (non-interactive)

Set secrets / env:

```bash
RMSIGNAGE_SUPER_ADMIN_NAME=
RMSIGNAGE_SUPER_ADMIN_EMAIL=
RMSIGNAGE_SUPER_ADMIN_PASSWORD=

RMSIGNAGE_PLATFORM_ADMIN_NAME=
RMSIGNAGE_PLATFORM_ADMIN_EMAIL=
RMSIGNAGE_PLATFORM_ADMIN_PASSWORD=
```

Then run:

```bash
php artisan rmsignage:create-super-admin --from-env
php artisan rmsignage:create-platform-admin --from-env
```

`--from-env` never prompts. Missing env vars fail clearly. Password is never printed.

### Local / interactive

```bash
php artisan rmsignage:create-super-admin --name="Ahmed" --email="you@example.com"
php artisan rmsignage:create-platform-admin --name="Sarah" --email="ops@example.com"
```

Password is prompted hidden (never a CLI flag).

Behaviour (both modes):

- Accounts are email-verified immediately (no verification email)
- No Business membership is created
- Login destination: `/admin`
- Super Admin: full platform capabilities
- Platform Admin: operational `/admin` access; cannot manage plans, feature flags, platform settings, platform roles, or delete Businesses
- Only Super Admin may assign/remove platform roles; the last Super Admin cannot be demoted
- Idempotent; safe to re-run
- `php artisan db:seed` / `LocalDevSeeder` refuse production

## Analytics retention

| Data                  | Default                                           |
| --------------------- | ------------------------------------------------- |
| Raw `playback_events` | 90 days (`ANALYTICS_PLAYBACK_RETENTION_DAYS`)     |
| `screen_daily_stats`  | 400 days (`ANALYTICS_DAILY_STATS_RETENTION_DAYS`) |

Availability = heartbeat connectivity, not hardware power.

## Backups

**Recommendation (minimum):**

- PostgreSQL: daily full + continuous WAL/PITR where available
- Media disk: daily/object-versioned backups
- Retain ≥ 7–30 days depending on risk appetite
- Store secrets/config in a secrets manager (not only git)

**Restore outline (staging only):**

1. Provision empty DB + restore dump
2. Restore Media bucket/prefix
3. Point `APP_URL` / storage env at staging
4. `php artisan migrate --force` only if schema drift requires it
5. Verify login, Media URLs, Player pairing against staging Screens

Do not claim automated backups exist until the host configures them.

## Incident quick checks

### Screen Offline

Last seen on Screen detail → network → pairing still valid → Player open on device.

### Publishing stuck

Deployment status → Screen heartbeat → reported deployment id → Offline pending ≠ failed.

### Billing issue

Subscription status → Stripe Customer Portal → webhooks → `BILLING_ENFORCE`.

### Player not updating

Manifest check → Service Worker cache version → offline package → revoke/re-pair if needed.

### AI unavailable

Provider key/env → Admin/feature flags → Editor still works without AI.

## Security notes

- CSRF exempt only `player/api/*` and Stripe webhooks
- Security headers via `SecurityHeaders` middleware
- Content-Security-Policy is applied in **production** only (local Vite HMR uses another origin)
- Player device tokens hashed; never log raw tokens
- Workspace isolation enforced in policies + analytics Screen filters

## Zero / low downtime

Prefer additive migrations. Avoid dropping columns still read by old app instances. Roll app → migrate → cache in that order when safe; for destructive schema, use expand/contract.
