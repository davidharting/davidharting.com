#!/usr/bin/env bash
set -euo pipefail

laravel_env_file="${LARAVEL_ENV_FILE:-/app/.env}"

if [[ $# -eq 0 ]]; then
    echo "Usage: $0 <command> [args...]" >&2
    exit 64
fi

if [[ "${IS_PULL_REQUEST:-false}" == "true" ]]; then
    # Preview environments: decrypt staging.secrets.env.age using age.
    # AGE_SECRET_KEY is a plain env var in the preview env group — it survives
    # the PR env group copy that strips secret files.
    if [[ -z "${AGE_SECRET_KEY:-}" ]]; then
        echo "AGE_SECRET_KEY is not set" >&2
        exit 66
    fi
    key_file=$(mktemp)
    trap 'rm -f "$key_file"' EXIT
    echo "$AGE_SECRET_KEY" > "$key_file"
    age --decrypt -i "$key_file" /app/staging.secrets.env.age > "$laravel_env_file"
else
    # Production: secrets are mounted as a Render secret file.
    secrets_file="${RENDER_SECRETS_ENV_FILE:-/etc/secrets/secrets.env}"
    if [[ ! -f "$secrets_file" ]]; then
        echo "Missing Render secrets file: $secrets_file" >&2
        exit 66
    fi
    ln -sf "$secrets_file" "$laravel_env_file"
fi

exec "$@"
