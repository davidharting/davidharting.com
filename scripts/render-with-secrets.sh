#!/usr/bin/env bash
set -euo pipefail

laravel_env_file="${LARAVEL_ENV_FILE:-/app/.env}"

if [[ $# -eq 0 ]]; then
    echo "Usage: $0 <command> [args...]" >&2
    exit 64
fi

# RENDER_SECRETS_PREFIX names the file prefix expected for all /etc/secrets/<prefix><filename>
# in this environment.
#
# It must be *set*, even when empty. An absent variable means the env var group
# never reached this service, and defaulting to the empty prefix would
# incorrectly grant an environment production's secrets.
if [[ -z "${RENDER_SECRETS_PREFIX+set}" ]]; then
    echo "RENDER_SECRETS_PREFIX is not set" >&2
    exit 78
fi

secrets_dir="${RENDER_SECRETS_DIR:-/etc/secrets}"
secrets_file="$secrets_dir/${RENDER_SECRETS_PREFIX}secrets.env"

if [[ ! -f "$secrets_file" ]]; then
    echo "Missing Render secrets file: $secrets_file" >&2
    exit 66
fi

ln -sf "$secrets_file" "$laravel_env_file"

# Passport signs OAuth tokens with the keypair at base_path('secrets/oauth'), a
# path AppServiceProvider pins for every environment. Render delivers those
# keys as secret files, so they have to be materialised there on boot.
private_key_source="$secrets_dir/${RENDER_SECRETS_PREFIX}oauth-private.key"
public_key_source="$secrets_dir/${RENDER_SECRETS_PREFIX}oauth-public.key"

for key_source in "$private_key_source" "$public_key_source"; do
    if [[ ! -f "$key_source" ]]; then
        echo "Missing Passport signing key: $key_source" >&2
        exit 66
    fi
done

# Copy rather than symlink, and force the mode: league/oauth2-server rejects any
# key file outside mode {400,440,600,640,660}, reporting it with
# trigger_error(E_USER_NOTICE) -- which Laravel's error handler turns into a
# thrown ErrorException the first time a token is verified. Forcing 600 here
# makes whatever mode Render mounts with irrelevant.
key_dir="$(dirname "$laravel_env_file")/secrets/oauth"
install -d -m 700 "$key_dir"
install -m 600 "$private_key_source" "$key_dir/oauth-private.key"
install -m 600 "$public_key_source" "$key_dir/oauth-public.key"

exec "$@"
