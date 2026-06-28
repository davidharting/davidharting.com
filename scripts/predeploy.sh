#!/bin/bash
set -e

php artisan migrate --force
if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    WEBHOOK_BASE="${RENDER_EXTERNAL_URL}"
    php artisan db:seed --force
else
    WEBHOOK_BASE="${APP_URL}"
fi
php artisan nutgram:hook:set "$WEBHOOK_BASE/api/telegram/webhook"
php artisan nutgram:register-commands
