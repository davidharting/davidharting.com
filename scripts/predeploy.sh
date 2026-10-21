#!/bin/bash
set -e

# A PR preview's Postgres is created alongside its web service, and Render can
# start this pre-deploy before the new database accepts connections. Migrating
# straight away then fails with "Connection refused" and cancels the preview's
# first deploy, so wait for the database before touching it.
wait_seconds="${DB_WAIT_SECONDS:-300}"
deadline=$((SECONDS + wait_seconds))
until pg_isready --quiet --timeout=5 --dbname="$DATABASE_URL"; do
    if ((SECONDS >= deadline)); then
        echo "Database still not accepting connections after ${wait_seconds}s" >&2
        exit 1
    fi
    echo "Waiting for the database to accept connections..."
    sleep 5
done

php artisan migrate --force
if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    WEBHOOK_BASE="${RENDER_EXTERNAL_URL}"
else
    WEBHOOK_BASE="${APP_URL}"
fi
php artisan nutgram:hook:set "$WEBHOOK_BASE/api/telegram/webhook"
php artisan nutgram:register-commands
