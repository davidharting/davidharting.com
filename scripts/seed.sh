#!/bin/bash
set -e

if [[ "${RUN_DEV_SEEDER:-false}" != "true" ]]; then
    echo "RUN_DEV_SEEDER is not true, skipping seed."
    exit 0
fi

php artisan db:seed --force
