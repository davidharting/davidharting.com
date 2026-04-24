---
name: render-migration
status: in-progress
---

# Render Migration

Migrating davidharting.com from a Digital Ocean droplet running Docker Compose to Render.com, driven by `render.yaml` (Blueprint-as-code).

## Why

- Simplify infra (no more VM patching, no Docker Compose orchestration on a single host).
- Go all-in on a single PaaS now that I work at Render.
- Keep secrets management, scaling, and deploy UX inside one platform.

## Target architecture

```
Internet
  └─ Render ingress (terminates TLS, region: ohio)
       └─ web service        → FrankenPHP Octane (HTTP on $PORT)
       └─ worker service     → php artisan queue:work
       └─ scheduler worker   → php artisan schedule:work
       └─ Postgres (managed) ← private network
  └─ Cloudflare R2 (public + private buckets — unchanged)
```

Reliability posture:

- Managed Postgres paid tier includes PITR + 7-day logical backups.
- Storage autoscaling (+50% / round to 5 GB) when the DB hits 90% full.
- Existing Spatie `backup:run --only-db` hourly job continues to push dumps to R2 private bucket.
- Three independent recovery paths for DB.

## Decisions

| Area | Choice |
|------|--------|
| Build source | `runtime: docker`, Render builds from repo `Dockerfile` each deploy |
| Region | `ohio` (closest to Indianapolis) |
| Postgres | `basic-1gb`, `postgresMajorVersion: "17"`, `diskSizeGB: 1`, storage autoscaling on |
| Scheduler | Long-running `worker` running `php artisan schedule:work` (NOT a Render cron — avoids CronJobV2 cold-start risk on hourly `backup:run` ticks) |
| Cache / session / queue | `database` driver, no Redis for v1 |
| Logging | `LOG_CHANNEL=stderr` only |
| Nightwatch | Dropped for v1, revisit later |
| DNS | Render-generated `*.onrender.com` URL for v1; Cloudflare custom domain is a follow-up |
| Env vars | One `envVarGroups` + per-service `DATABASE_URL` via `fromDatabase.connectionString` |
| Migrations | Web service `preDeployCommand` (Render orchestrates DB readiness, no `pg_isready` wait needed) |
| Telegram webhook | Also in web `preDeployCommand`; idempotent |
| CI gate | "After CI Checks Pass" auto-deploy in dashboard (post-provision); CI runs on `push: main` and `pull_request` |

## Env var inventory

Non-secret (in `envVarGroups.davidhartingdotcom-shared`):

```
APP_NAME, APP_ENV, APP_DEBUG, APP_URL, LOG_CHANNEL, LOG_LEVEL,
CACHE_DRIVER, SESSION_DRIVER, SESSION_LIFETIME, SESSION_SECURE_COOKIE,
QUEUE_CONNECTION, BROADCAST_DRIVER, DB_CONNECTION,
FILESYSTEM_DISK_PRIVATE, FILESYSTEM_DISK_PUBLIC,
R2_PUBLIC_BUCKET, R2_PUBLIC_URL,
MAIL_MAILER, MAIL_FROM_ADDRESS, MAIL_FROM_NAME,
OCTANE_SERVER, OCTANE_HTTPS
```

Secrets (`sync: false` — Render prompts once at blueprint creation):

```
APP_KEY                    ← paste the existing production key verbatim
MAILERSEND_API_KEY
MAILERSEND_ADMIN_ADDRESS
R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY
R2_ENDPOINT
R2_PRIVATE_BUCKET
TELEGRAM_TOKEN
TELEGRAM_DAVID_ID
ANTHROPIC_API_KEY
```

Per-service:

```
DATABASE_URL               ← fromDatabase.connectionString (web, worker, scheduler)
```

**Critical**: `APP_KEY` MUST match the existing production key. A new random one invalidates all encrypted cookies, sessions, and any encrypted DB columns.

## First-deploy steps

1. Open the Render dashboard and create a new Blueprint pointing at this repo.
2. Render parses `render.yaml`, provisions the Postgres database, and prompts for all `sync: false` secrets. Paste values from the current DO droplet's `./secrets/*.txt` files.
3. First deploy sequence:
   - DB provisions and becomes available.
   - Web service builds (Dockerfile).
   - Web `preDeployCommand` runs: `php artisan migrate --force && php artisan nutgram:hook:set "$APP_URL/api/telegram/webhook" && php artisan nutgram:register-commands`. Migrations apply to the empty DB; Telegram webhook is registered against the placeholder `APP_URL`.
   - Octane starts, listens on `$PORT`.
   - Worker and scheduler services build in parallel pipelines and come up when done.
4. In the dashboard, set **Auto-Deploy: After CI Checks Pass** on each of the three services. This makes Render wait on GitHub Actions' `CI` workflow.
5. Grab the generated hostname (e.g. `davidhartingdotcom-web.onrender.com`) and update `APP_URL` in the env group to `https://davidhartingdotcom-web.onrender.com`. Render redeploys; `preDeployCommand` re-registers the Telegram webhook at the real URL.

## Data migration (during cutover window)

1. From the DO droplet:
   ```
   docker exec <db-container> pg_dump -U laravel -Fc laravel > dump.dump
   scp dump.dump $HOME/
   ```
2. Copy the external Postgres connection string from Render's DB dashboard.
3. Restore:
   ```
   pg_restore --clean --if-exists --no-owner -d "$RENDER_DB_URL" dump.dump
   ```
4. Re-run smoke tests (below).

## Local Dockerfile smoke test

To validate the image locally before pushing:

```
docker build -t davidhartingdotcom:local .
docker run --rm -d --name render-test \
  -e PORT=9090 -p 9090:9090 \
  -e APP_KEY=base64:... -e APP_ENV=production -e APP_DEBUG=false \
  -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e CACHE_DRIVER=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
  -e LOG_CHANNEL=stderr \
  davidhartingdotcom:local
curl -i http://localhost:9090/healthz   # expect 200 OK, body "OK", no Set-Cookie
```

**Pick a known-free port** and check `lsof -iTCP:<port> -sTCP:LISTEN` first. Common ports (8080, 8000) are often squatted by other dev tools (tilt, ssh tunnels, forwarded Docker Desktop mappings) that intercept traffic and can surface as unrelated-looking errors (e.g. spurious HTTPS redirects from whatever's actually listening).

## Smoke tests

After first deploy and after `APP_URL` update:

1. `curl -i https://<name>.onrender.com/healthz` → `200 OK`, **no `Set-Cookie` header** (proves /healthz is outside web middleware).
2. `curl -I https://<name>.onrender.com/` → `200`, `Content-Type: text/html; charset=UTF-8`.
3. Browser to `/backend` → auth redirect; DevTools shows session cookie has `Secure` flag (proves TrustProxies + SESSION_SECURE_COOKIE).
4. Render web logs show structured stderr output and no "Log channel [stack]..." errors.
5. Telegram: send `/whoami` → bot responds. Confirms webhook re-registered and worker is processing nutgram.
6. `curl "https://api.telegram.org/bot$TELEGRAM_TOKEN/getWebhookInfo"` → URL matches `$APP_URL/api/telegram/webhook`.
7. From web shell: `php artisan backup:run --only-db` → completes, dump lands in R2 private bucket.
8. Postgres shell: `SELECT count(*) FROM sessions;` — grows with human traffic, not healthcheck polls.
9. Scheduler logs show `schedule:work` alive and ticking each minute; `backup:run` fires once per hour inside that.
10. Worker: `php artisan tinker` → dispatch a test job → worker log shows it processed. `failed_jobs` stays empty over 5 minutes.
11. Browse the site; confirm R2 CDN assets load from `cdn.davidharting.com`.

## DNS cutover (follow-up, not v1)

1. Add `davidharting.com` as a custom domain in Render web service.
2. In Cloudflare DNS, set the apex record as DNS-only (grey cloud) pointing at Render's CNAME target. Wait for Render to issue a cert.
3. After cert issued, optionally flip to proxied (orange cloud, Orange-to-Orange).
4. Update `APP_URL` in the env group to `https://davidharting.com`. Redeploy — preDeploy re-registers the Telegram webhook at the new URL.
5. Re-run smoke tests 1–6 against `https://davidharting.com`.

## Rollback

If the new deploy misbehaves before DNS cutover:

- **Code rollback**: click "Rollback" on any service in the Render dashboard. Render re-promotes the previous deploy.
- **Full fallback**: the Digital Ocean droplet and its Postgres are still running during the overlap window. Reverting Cloudflare DNS to the droplet IP (if not yet flipped) returns traffic to the old stack. If DNS has been flipped, take down the Render service / redirect Cloudflare back to DO.
- **DB rollback** (after data migration): Render Postgres supports PITR. From the dashboard, trigger a point-in-time recovery to just before the cutover.

## Deferred to v2

- Nightwatch re-introduction via Render log drains / metrics
- Custom domain + Cloudflare orange cloud (Orange-to-Orange)
- Consolidating worker + scheduler into fewer paid containers if cost matters
- Switching cache/session to Render Key Value (Redis) if DB contention shows up
- Decommissioning the DO droplet after a monitoring window (keep it running ~2 weeks as a safety net)

## Status

- [x] Phase 1: Code changes — TrustProxies, /healthz, Caddyfile, Dockerfile, CI, CLAUDE.md
- [ ] Phase 2: Write `render.yaml`
- [ ] Phase 3: Provision Render Blueprint + first deploy
- [ ] Phase 4: Data migration + smoke tests
- [ ] DNS cutover (follow-up)
- [ ] Decommission Digital Ocean droplet
