#!/bin/bash
# Bootstraps a Claude Code cloud sandbox: PHP 8.4 with PCOV, Node 24, libvips,
# exiftool, vendor/, node_modules/, .env and PostgreSQL. Point the cloud environment's setup
# script at this file. Safe to re-run.
#
# The sandbox's GitHub proxy refuses Composer's dist zip downloads for every
# repository but this one, so a plain `composer install` falls back to cloning
# each package. Instead, vendor/ comes prebuilt from the newest
# `dev-sandbox-vendor` artifact that .github/workflows/sandbox-bundle.yml
# uploaded from SANDBOX_BUNDLE_BRANCH (main by default), and
# `composer install` afterwards only fetches whatever the lockfile changed.
# The sandbox proxy supplies the GitHub credentials the artifacts API needs.
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BIN_DIR="$HOME/.local/bin"
BUNDLE_BRANCH="${SANDBOX_BUNDLE_BRANCH:-main}"
ARTIFACTS_API="https://api.github.com/repos/davidharting/davidharting.com/actions/artifacts"

# ~/.local/bin comes before the image's own PHP and Node on the sandbox PATH.
mkdir -p "$BIN_DIR"

# PHP 8.4 comes from Ondřej Surý's PPA, the same packages CI's setup-php
# installs, rather than the Herd Lite static binary: Herd Lite's Linux build is
# static musl and cannot load extensions, and Pest's --tia needs the PCOV
# coverage driver.
echo "Installing PHP 8.4 with PCOV (ondrej/php PPA)"
rm -f "$BIN_DIR/php" # Herd Lite, from earlier runs of this script
PHP_KEYRING=/usr/share/keyrings/ondrej-php.gpg
[ -f "$PHP_KEYRING" ] || curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C' \
    | gpg --dearmor -o "$PHP_KEYRING"
echo "deb [signed-by=$PHP_KEYRING] https://ppa.launchpadcontent.net/ondrej/php/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") main" \
    > /etc/apt/sources.list.d/ondrej-php.list

NODE_VERSION="$(curl -fsSL https://nodejs.org/dist/index.json | grep -oE '"version":"v24\.[0-9]+\.[0-9]+"' | head -1 | cut -d'"' -f4)"
echo "Installing Node $NODE_VERSION"
curl -fsSL "https://nodejs.org/dist/$NODE_VERSION/node-$NODE_VERSION-linux-x64.tar.xz" | tar -xJ -C /opt
for bin in node npm npx; do
    ln -sf "/opt/node-$NODE_VERSION-linux-x64/bin/$bin" "$BIN_DIR/$bin"
done
export PATH="$BIN_DIR:$PATH"

# vipsthumbnail processes memory photos; exiftool lets tests inspect the result.
echo "Installing PHP, libvips and exiftool"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends \
    php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-gmp php8.4-intl \
    php8.4-mbstring php8.4-pgsql php8.4-sqlite3 php8.4-xml php8.4-zip php8.4-pcov \
    libvips-tools libheif-plugin-libde265 libimage-exiftool-perl
# The image ships PHP 8.3 as /usr/bin/php.
update-alternatives --set php /usr/bin/php8.4

cd "$REPO_DIR"

echo "Unpacking prebuilt vendor/ from $BUNDLE_BRANCH"
BUNDLE_DIR="$(mktemp -d)"
ARTIFACT_URL="$(curl -fsSL "$ARTIFACTS_API?name=dev-sandbox-vendor&per_page=100" \
    | jq -r --arg branch "$BUNDLE_BRANCH" '[.artifacts[] | select(.workflow_run.head_branch == $branch and (.expired | not))][0].archive_download_url // empty')" || true
if [ -n "$ARTIFACT_URL" ] \
    && curl -fsSL -o "$BUNDLE_DIR/bundle.zip" "$ARTIFACT_URL" \
    && unzip -q "$BUNDLE_DIR/bundle.zip" -d "$BUNDLE_DIR" \
    && tar -xzf "$BUNDLE_DIR/dev-sandbox-vendor.tar.gz"; then
    echo "Unpacked $ARTIFACT_URL"
else
    echo "No usable dev-sandbox-vendor artifact; composer install will build vendor/ from scratch."
fi
rm -rf "$BUNDLE_DIR"

COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress
npm ci --no-audit --no-fund
# Feature tests render layouts that read the Vite manifest.
npm run build

echo "Configuring the app"
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --no-interaction
[ -f secrets/oauth/oauth-private.key ] || { mkdir -p secrets/oauth && php artisan passport:keys --no-interaction; }

# Same roles as scripts/dev/db-bootstrap.sh: `root` matches .env for the dev
# database, and `david` matches the DB_USERNAME phpunit.xml hardcodes, with no
# password. Postgres can't check an empty password, so this throwaway sandbox
# trusts local TCP connections. SUPERUSER lets `--parallel` create its
# laravel_test_test_N databases.
echo "Configuring PostgreSQL"
PG_HBA="$(ls /etc/postgresql/*/main/pg_hba.conf | head -1)"
sed -i -E 's/^(host\s+all\s+all\s+(127\.0\.0\.1\/32|::1\/128)\s+)\S+$/\1trust/' "$PG_HBA"
service postgresql restart
psql_su() { su postgres -c "psql -X -v ON_ERROR_STOP=1 -tA $(printf '%q ' "$@")"; }
[ "$(psql_su -c "SELECT 1 FROM pg_roles WHERE rolname='root'")" = "1" ] || psql_su -c "CREATE ROLE root WITH LOGIN CREATEDB PASSWORD 'password';"
[ "$(psql_su -c "SELECT 1 FROM pg_roles WHERE rolname='david'")" = "1" ] || psql_su -c "CREATE ROLE david WITH LOGIN SUPERUSER;"
[ "$(psql_su -c "SELECT 1 FROM pg_database WHERE datname='laravel'")" = "1" ] || psql_su -c "CREATE DATABASE laravel OWNER root;"
[ "$(psql_su -c "SELECT 1 FROM pg_database WHERE datname='laravel_test'")" = "1" ] || psql_su -c "CREATE DATABASE laravel_test OWNER david;"
php artisan migrate --force --no-interaction

php --version | head -1
node --version
