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

#### macOS via mise

There's no system Postgres service on macOS, so run it directly from the mise-installed binaries. `postgres = "17"` should be in your mise config (e.g. `~/repos/.config/mise/config.toml` or a project-local `mise.toml`).

Locate the install and put its `bin/` on `PATH` for these commands:

```bash
PG_BIN="$(mise where postgres)/bin"
PGDATA="$(mise where postgres)/data"
export PATH="$PG_BIN:$PATH"
```

Initialize the data directory (first time only):

```bash
initdb -D "$PGDATA" -U postgres
```

Start the server:

```bash
pg_ctl -D "$PGDATA" -l "$PGDATA/logfile" start
```

Create databases and users. `laravel` matches `.env`; `david` matches the `DB_USERNAME` hardcoded in `phpunit.xml` for the test database — substitute your own username if different:

```bash
psql -U postgres -d postgres -c "CREATE ROLE david WITH LOGIN SUPERUSER;"
psql -U postgres -d postgres -c "CREATE ROLE laravel WITH LOGIN PASSWORD 'password';"
psql -U postgres -d postgres -c "CREATE DATABASE laravel OWNER laravel;"
psql -U postgres -d postgres -c "CREATE DATABASE laravel_test OWNER david;"
psql -U postgres -d postgres -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO laravel;"
```

Stop the server when you're done:

```bash
pg_ctl -D "$PGDATA" stop
```

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
