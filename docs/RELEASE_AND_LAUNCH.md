# Release Checklist

Use before every production deploy.

- [ ] CI green: `php artisan test`, lint, types, `npm run build`, relevant e2e
- [ ] Migrations reviewed (no destructive lock surprises)
- [ ] PostgreSQL + Media backup available / verified recently
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL` (HTTPS)
- [ ] Trusted proxies configured if behind a load balancer
- [ ] Redis / cache / session drivers intentional
- [ ] Persistent Media disk (not ephemeral container FS)
- [ ] Stripe mode correct (test vs live); webhook endpoint + secret
- [ ] `BILLING_ENFORCE` intentional
- [ ] AI keys present only if AI enabled; otherwise AI fails closed
- [ ] Mail sender verified
- [ ] Scheduler cron running (`schedule:run`)
- [ ] Queue workers running if not `sync`
- [ ] `/health` and `/ready` green after deploy
- [ ] Admin System Health acceptable
- [ ] Smoke: auth → Media → Design publish → pair → Player → Analytics event
- [ ] No secrets in repo / logs

# Launch Readiness Checklist

High-level go/no-go for first paying customers.

- [ ] Production domain + HTTPS
- [ ] Legal pages (terms/privacy) as required by business
- [ ] Pricing page matches Stripe products
- [ ] Support flow (Help → Admin Support) works
- [ ] Billing: subscribe / change quantity / portal / cancel retain data
- [ ] Starter Templates seeded (idempotent platform seed only)
- [ ] Real TV / Chromium Player hardware checklist completed
- [ ] Offline Player + reconnect verified on target hardware
- [ ] Backups + restore drill on staging
- [ ] Error monitoring or Admin Errors + logs monitored
- [ ] SEO: public marketing only; `/app`, `/admin`, `/player` not indexed
- [ ] Multi-workspace isolation tests green
- [ ] Analytics shows real playback (no demo charts in customer accounts)
