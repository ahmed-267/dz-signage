# DZ Signage — Development

Commands below match this repository (`package.json`, `composer.json`, `docker-compose.yml`, `.env.example`).

App URL: **http://localhost:8001** (`SERVER_PORT=8001` — avoids clashes with apps on `8000`).

After migrate, create a public storage link if needed:

```bash
php artisan storage:link
```

Workspace logos are stored on the local `public` disk (`storage/app/public/workspace-logos`).

### Phase 1 surfaces

| Path                                | Purpose                                                        |
| ----------------------------------- | -------------------------------------------------------------- |
| `/onboarding`                       | First workspace creation for verified users with no membership |
| `/app/*`                            | Customer app (requires current workspace membership)           |
| `/app/team`                         | Members + invitations                                          |
| `/app/settings/workspace`           | Workspace settings (not user profile)                          |
| `/invitations/{token}`              | Accept workspace invitations                                   |
| `/admin/workspaces`, `/admin/users` | Super Admin read-only lists (`users.is_admin`)                 |

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

# Playwright (build assets first)
npm run build
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

## 7. Not installed yet

Do not document or assume without adding packages: Horizon, Reverb, Cashier/Stripe, S3/R2 SDKs, FFmpeg pipelines, AI SDKs.
