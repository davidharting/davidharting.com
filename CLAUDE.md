This is a Laravel project for my personal website, davidharting.com.

## Development Setup

**If commands fail due to missing dependencies** (e.g., `vendor/autoload.php` not found, missing node_modules), you are in a fresh environment. Follow [docs/development-setup.md](docs/development-setup.md) to bootstrap before proceeding.

Quick check: if `php artisan test` fails with autoload errors, run the setup.

## Architecture overview

The site runs on Render.com, provisioned from `render.yaml` (Blueprint-as-code).

```
Internet
  └─ Render ingress (terminates TLS, region: ohio)
       └─ web service        → FrankenPHP Octane (HTTP on $PORT)
       └─ worker service     → php artisan queue:work
       └─ scheduler worker   → php artisan schedule:work
       └─ Postgres (managed) ← private network
  └─ Cloudflare R2 (public + private buckets)
```

- All services build from the repo `Dockerfile` (`runtime: docker`). Render does not share builds across services, so each of the three services builds the image separately; Dockerfile layer ordering is tuned so dependency layers stay cached.
- Render terminates TLS at the ingress — the container listens on plain HTTP at `$PORT`. The `Caddyfile` binds `:{$PORT:80}`.
- Shared env vars live in an `envVarGroups` block; secrets use `sync: false` (prompted once at blueprint creation, then managed via the Render dashboard).
- Database wiring is a single `DATABASE_URL` sourced from the managed Postgres via `fromDatabase.connectionString`.
- Migrations + Telegram webhook registration run in the web service `preDeployCommand`. If preDeploy fails, Render keeps the prior version live (zero-downtime).
- Cloudflare DNS is a follow-up — for now the site serves on a generated `*.onrender.com` URL.
- Historical note: was previously Cloudflare (orange-cloud) → Digital Ocean droplet running Docker Compose. See `docs/projects/render-migration.md` for migration history and follow-ups.

## Commands

- Use `ripgrep` to search files and `fd` to find files
- Use `php artisan` for Laravel commands
- Run tests: `php artisan test` (pass a file path to run one file)
- Run tests with previously failed tests first, stopping on first failure: `php artisan test --compact --retry --bail`
- Format code: `task format`

## Rules

### Way of working

- Work on only what I ask you to do, and one thing at a time. Focus changes to just the task at hand. Ask about refactors before doing them.
- Make atomic commits with detailed messages
- Include tests as you go rather than at the end. Tests should be committed with the relevant application changes.

### Testing

- Write tests for all changes
- Focus on feature tests to get more leverage
