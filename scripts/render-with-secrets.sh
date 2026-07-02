#!/usr/bin/env bash
set -euo pipefail

laravel_env_file="${LARAVEL_ENV_FILE:-/app/.env}"

if [[ $# -eq 0 ]]; then
    echo "Usage: $0 <command> [args...]" >&2
    exit 64
fi

secrets_file="${RENDER_SECRETS_ENV_FILE:-/etc/secrets/secrets.env}"
if [[ ! -f "$secrets_file" ]]; then
    echo "Missing Render secrets file: $secrets_file" >&2
    exit 66
fi

ln -sf "$secrets_file" "$laravel_env_file"

exec "$@"
