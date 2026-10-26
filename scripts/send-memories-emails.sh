#!/bin/bash
set -e

# Render gives preview environments a copy of every cron. A preview's database
# is seeded with test participants, so skip there rather than email them.
if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    echo "Skipping memories emails in a preview environment."
    exit 0
fi

php artisan memories:send-emails
