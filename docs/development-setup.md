# Development Environment Setup

Bootstrap a fresh development environment to run tests. This guide is designed for automated Claude Code and git worktree workflows.

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js and npm
- PostgreSQL 15 or higher

## Setup Steps

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. PostgreSQL Setup

Pick the path for your environment. **Remote / CI / sandboxed Linux containers** (including cloud coding agents) use the Linux section and drive PostgreSQL directly. **Local macOS** development uses the pitchfork-managed workflow. mise + pitchfork are a macOS-local convenience, not a requirement anywhere else.

#### Linux / sandboxed environments

Start PostgreSQL:

```bash
sudo service postgresql start
```

Create databases and user:

```bash
sudo -u postgres psql -c "CREATE DATABASE laravel;"
sudo -u postgres psql -c "CREATE DATABASE laravel_test;"
sudo -u postgres psql -c "CREATE USER root WITH PASSWORD 'password';"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO root;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE laravel_test TO root;"
sudo -u postgres psql -d laravel -c "GRANT ALL ON SCHEMA public TO root;"
sudo -u postgres psql -d laravel_test -c "GRANT ALL ON SCHEMA public TO root;"
```

#### macOS (local dev via pitchfork)

On macOS, PostgreSQL is managed by [pitchfork](https://pitchfork.jdx.dev) as the `postgres` daemon in `pitchfork.toml` — you do **not** start it by hand. On first run the daemon initializes the mise-installed PostgreSQL 17 data directory, and its readiness script (`scripts/dev/db-bootstrap.sh`) idempotently creates the roles and databases below and runs migrations. `postgres = "17"` must be in your mise config (e.g. `~/repos/.config/mise/config.toml`).

Install tools and dependencies (one-off):

```bash
mise run setup
```

Bring up the stack — starts postgres, creates roles/databases, migrates, then starts octane/queue/vite:

```bash
mise run dev
```

The roles and databases created match `.env` and `phpunit.xml`: `laravel` for dev, and `laravel_test` owned by `david` — the `DB_USERNAME` hardcoded in `phpunit.xml` for the test database. Substitute your own username if different (see `scripts/dev/db-bootstrap.sh`).

If you only need the database (e.g. to run tests), `pitchfork start postgres` is enough. Stop everything with `mise run down`.

#### Both environments

`laravel` is for dev; `laravel_test` is where `php artisan test` runs (configured in `phpunit.xml`) so tests never clobber dev data.

Run migrations on the dev database:

```bash
php artisan migrate
```

### 4. Create Storage Symlink

```bash
php artisan storage:link
```

This creates a symlink from `public/storage` to `storage/app/public` so the local-public disk is accessible via the web server.

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Run Tests

```bash
php artisan test
```

All tests should pass.
