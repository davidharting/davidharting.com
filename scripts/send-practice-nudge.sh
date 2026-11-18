#!/bin/bash
set -e

# Render gives preview environments a copy of every cron. A preview shares the
# real Telegram bot and would message David about its seeded words, so skip.
if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    echo "Skipping the practice nudge in a preview environment."
    exit 0
fi

php artisan words:send-practice-nudge
