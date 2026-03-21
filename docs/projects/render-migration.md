---
name: render-migration
status: in-progress
---

# Render Migration

Move the production stack from a self-managed Digital Ocean droplet to Render.com to eliminate infrastructure maintenance overhead.

## Current Architecture

```
Cloudflare → DO Droplet (Docker Compose) → Caddy/FrankenPHP → Postgres
```

## Target Architecture

```
Cloudflare → Render Web Service (Docker) → Render Postgres
```

## Open Questions

- **Nightwatch:** The `nightwatch-agent` runs as a Docker sidecar communicating over localhost. Render services are isolated — this pattern doesn't translate. Options: run as a separate Background Worker service (may not be able to reach the web service), or drop Nightwatch and use Render's built-in log streaming.
- **Cost:** Render bills per-service (web + worker + cron + postgres). Confirm this is acceptable vs. the current single DO droplet bill before cutting over.

---

## Milestones

### 1 — Code prep

Changes needed before Render can run the app.

- [x] Unified `app:post-deploy` command (migrations + telegram setup)
- [ ] `Caddyfile.render` — listen on `$PORT` over plain HTTP (Render terminates TLS at the edge, no cert management needed)
- [ ] `render.yaml` — define web service, worker, cron job, and postgres
- [ ] `config/database.php` — add `DB_SSLMODE` support (Render Postgres requires SSL)
- [ ] Update `.env.example` with Render-relevant vars (`DB_SSLMODE`, etc.)

### 2 — Provision Render stack

Stand up services on Render without touching DNS. Everything should be reachable via `*.onrender.com` URLs.

- [ ] Create Render project
- [ ] Provision Render Postgres instance
- [ ] Deploy web service from Docker image (GHCR or build-from-source)
- [ ] Deploy queue worker Background Worker
- [ ] Deploy scheduler Cron Job (`php artisan schedule:run` every minute)
- [ ] Set all environment variables and secrets in Render dashboard

### 3 — Data migration

Move production Postgres data from DO to Render.

- [ ] `pg_dump` from DO: `docker exec <db> pg_dump -U laravel laravel > backup.sql`
- [ ] Restore into Render Postgres: `psql <render-connection-string> < backup.sql`
- [ ] Verify row counts and spot-check data

### 4 — Smoke test on Render URL

Verify the full stack works before cutting DNS.

- [ ] Site loads on Render URL
- [ ] Queued job processes (trigger one, confirm it runs)
- [ ] Scheduler fires (check logs after a minute)
- [ ] File upload reaches R2
- [ ] Telegram bot responds (webhook will need temporary reconfiguration)

### 5 — DNS cutover

- [ ] Update Cloudflare DNS to point to Render hostname
- [ ] Confirm HTTPS works via Cloudflare + Render
- [ ] Monitor logs for errors in first hour

### 6 — Decommission DO

Clean up after a stable cutover period.

- [ ] Decide on Nightwatch (keep with alternative integration, or drop in favor of Render logs)
- [ ] Replace SSH-based Taskfile prod tasks with Render CLI equivalents (`render ssh`, `render run`)
- [ ] Remove `prod:build` tar-file workflow if switching to Render build-from-source
- [ ] Delete the DO droplet
- [ ] Archive or remove DO-specific secrets and config
