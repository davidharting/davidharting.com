#!/usr/bin/env bash
#
# Wrapper used by pitchfork daemons (see pitchfork.toml).
#
# Pitchfork daemons are spawned by the background supervisor, so they inherit
# the supervisor's environment rather than your interactive shell's. Our
# toolchain is mixed: PHP comes from Herd (not mise), while node/postgres come
# from mise. Prepend both so daemons resolve their tools deterministically no
# matter what env the supervisor was launched from, then `exec` so pitchfork
# tracks the real process and SIGTERM reaches it on stop.
set -euo pipefail

export PATH="$HOME/.config/herd-lite/bin:$HOME/.local/bin:$HOME/.local/share/mise/shims:$PATH"

exec "$@"
