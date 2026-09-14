# DZ Signage

Production foundation for the **DZ Signage** SaaS platform (Phase 0).

This phase scaffolds the application stack only. Product features (media, templates, playlists, schedules, publishing, billing, AI, widgets, screen pairing) are intentionally deferred.

## Stack

| Layer                     | Choice                                                                        |
| ------------------------- | ----------------------------------------------------------------------------- |
| Backend                   | Laravel 13, PHP 8.3+ (local dev commonly 8.5)                                 |
| Frontend                  | React 19, TypeScript, Inertia 3, Tailwind CSS 4, shadcn/ui                    |
| Auth                      | Laravel Fortify (register, login, logout, password reset, email verification) |
| Database                  | PostgreSQL 16 (Docker)                                                        |
| Cache / queues / sessions | Redis 7 (Docker) via Predis                                                   |
| Tests                     | Pest (feature/unit), Playwright (e2e smoke)                                   |

## Prerequisites

- PHP 8.3+ with `pdo_pgsql` (8.5+ recommended)
- Composer
- Node.js 22+ / npm
- Docker + Docker Compose
- Git

## Quick start

```bash
# 1. Start PostgreSQL + Redis
docker compose up -d

# 2. Backend deps + env (if cloning fresh)
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan migrate

# 3. Frontend
npm install
npm run build

# 4. Dev servers
composer run dev
```

App URL: [http://localhost:8001](http://localhost:8001) (port `8001` avoids clashes with other local Laravel apps on `8000`)

### Docker services

| Service    | Host port     | Credentials                               |
| ---------- | ------------- | ----------------------------------------- |
| `postgres` | `5433` → 5432 | db/user: `dz_signage`, password: `secret` |
| `redis`    | `6379`        | no password                               |

PostgreSQL uses host port **5433** so it does not collide with other local Postgres instances on `5432`.

## Testing

```bash
# Pest (PHP)
php artisan test

# Playwright (browser smoke — requires built assets)
npm run build
npm run test:e2e
```

## Project layout (high level)

- `app/` — Laravel application code
- `resources/js/` — React + Inertia pages, layouts, shadcn UI
- `routes/` — HTTP routes
- `e2e/` — Playwright foundation tests
- `docker-compose.yml` — local Postgres + Redis only

## Out of scope (later phases)

Stripe/Cashier, Reverb, Horizon, FFmpeg, S3/R2, AI providers, weather/news APIs, video processing, analytics.
