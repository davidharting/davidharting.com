#!/bin/bash
set -e

php artisan migrate --force
if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    WEBHOOK_BASE="${RENDER_EXTERNAL_URL}"
    # Only seed on the first deploy of this PR (DB persists across pushes).
    # exit(0) = users exist = already seeded; exit(1) = empty = seed now.
    php artisan tinker --execute='exit(\App\Models\User::count() > 0 ? 0 : 1);' > /dev/null 2>&1 || php artisan db:seed --force
else
    WEBHOOK_BASE="${APP_URL}"
fi
php artisan nutgram:hook:set "$WEBHOOK_BASE/api/telegram/webhook"
php artisan nutgram:register-commands
