#!/usr/bin/env bash
#
# The `postgres` daemon's readiness probe (see pitchfork.toml). Pitchfork does
# not mark postgres "ready" until this exits 0, and every app daemon depends on
# postgres -- so this gates the whole stack on a fully prepared, migrated DB
# regardless of which daemon you start.
#
# Idempotent: creates the roles/databases from docs/development-setup.md if they
# are missing, then runs migrations. Safe to re-run on every startup.
set -euo pipefail

export PATH="$HOME/.local/bin:$HOME/.local/share/mise/shims:$PATH"

PG_HOME="$(mise where postgres)"
export PATH="$PG_HOME/bin:$PATH"

# Server must be accepting connections. If not, exit non-zero so pitchfork
# retries this readiness probe (~every 500ms) until the server is up.
pg_isready -q -h 127.0.0.1 || exit 1

psql_su() { psql -U postgres -h 127.0.0.1 -d postgres -v ON_ERROR_STOP=1 "$@"; }
role_exists() { [ "$(psql_su -tAc "SELECT 1 FROM pg_roles WHERE rolname='$1'")" = "1" ]; }
db_exists() { [ "$(psql_su -tAc "SELECT 1 FROM pg_database WHERE datname='$1'")" = "1" ]; }

# `david` matches the DB_USERNAME hardcoded in phpunit.xml for the test database;
# `laravel` matches .env for dev. See docs/development-setup.md.
role_exists david || psql_su -c "CREATE ROLE david WITH LOGIN SUPERUSER;"
role_exists laravel || psql_su -c "CREATE ROLE laravel WITH LOGIN PASSWORD 'password';"

db_exists laravel || psql_su -c "CREATE DATABASE laravel OWNER laravel;"
db_exists laravel_test || psql_su -c "CREATE DATABASE laravel_test OWNER david;"
psql_su -c "GRANT ALL PRIVILEGES ON DATABASE laravel TO laravel;"

# Always migrate the dev database on startup.
cd "$(dirname "$0")/../.."
exec php artisan migrate --force
