#!/bin/bash
set -e

php artisan migrate --force
php artisan nutgram:hook:set "$APP_URL/api/telegram/webhook"
php artisan nutgram:register-commands
