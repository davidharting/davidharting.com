#!/bin/bash
# Bootstraps a Claude Code cloud sandbox: PHP 8.4, Node 24, vendor/ and
# node_modules/. Point the cloud environment's setup script at this file.
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

echo "Installing PHP 8.4 (Herd Lite static binary)"
curl -fsSL -o "$BIN_DIR/php" https://download.herdphp.com/herd-lite/linux/x64/8.4/php
chmod +x "$BIN_DIR/php"

NODE_VERSION="$(curl -fsSL https://nodejs.org/dist/index.json | grep -oE '"version":"v24\.[0-9]+\.[0-9]+"' | head -1 | cut -d'"' -f4)"
echo "Installing Node $NODE_VERSION"
curl -fsSL "https://nodejs.org/dist/$NODE_VERSION/node-$NODE_VERSION-linux-x64.tar.xz" | tar -xJ -C /opt
for bin in node npm npx; do
    ln -sf "/opt/node-$NODE_VERSION-linux-x64/bin/$bin" "$BIN_DIR/$bin"
done
export PATH="$BIN_DIR:$PATH"

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

php --version | head -1
node --version
