#!/usr/bin/env bash
#
# The `postgres` pitchfork daemon. Runs the mise-managed PostgreSQL 17 server
# in the foreground against the mise install's data directory (the same one
# documented in docs/development-setup.md, so the CLI and tests share a
# cluster). Initializes the data directory on first run.
set -euo pipefail

export PATH="$HOME/.local/bin:$HOME/.local/share/mise/shims:$PATH"

PG_HOME="$(mise where postgres)"
export PATH="$PG_HOME/bin:$PATH"
export PGDATA="${PGDATA:-$PG_HOME/data}"

if [ ! -d "$PGDATA" ]; then
    initdb -D "$PGDATA" -U postgres
fi

exec postgres -D "$PGDATA"
