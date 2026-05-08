This is a Laravel project for my personal website, davidharting.com.

## Development Setup

**If commands fail due to missing dependencies** (e.g., `vendor/autoload.php` not found, missing node_modules), you are in a fresh environment. Follow [docs/development-setup.md](docs/development-setup.md) to bootstrap before proceeding.

Quick check: if `php artisan test` fails with autoload errors, run the setup.

## Architecture overview

The site runs on Render.com from `render.yaml`.

```
Internet
  -> Render ingress
  -> web service: FrankenPHP Octane on $PORT
  -> Render Postgres on the private network
```

Supporting services:

- `davidhartingdotcom-worker`: runs `php artisan queue:work`.
- `davidhartingdotcom-backup-run`: Render cron job that runs database backups.
- `davidhartingdotcom-backup-clean`: Render cron job that prunes old backups.
- Cloudflare DNS points `davidharting.com` at Render. Cloudflare R2 stores public and private objects.

Operational notes:

- Render terminates TLS; the container listens on plain HTTP at `$PORT`.
- `render.yaml` owns service, database, worker, and cron definitions.
- Secrets are managed in Render, not committed to git. Prefer an IaC-friendly path, such as Render secret files, over long-term dashboard-only configuration.
- The web service `preDeployCommand` runs migrations and Telegram webhook registration before new web instances receive traffic.
- Historical note: this previously ran on a Digital Ocean droplet with Docker Compose. See `docs/projects/render-migration.md` for migration history.

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
