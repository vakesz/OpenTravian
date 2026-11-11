#!/bin/sh
# Visual regression screenshots of the key local routes (opt-in; needs the dev stack).
#
#   dev/visual-tests/run.sh                compare against tests/visual/baselines
#   dev/visual-tests/run.sh --update       rewrite the baselines
#   dev/visual-tests/run.sh <name> ...     only these routes (names in tests/visual/routes.json)
#
# Same setup as dev/client-tests: npm ci && npx playwright install chromium there.
set -eu

here=$(cd "$(dirname "$0")" && pwd)
root=$(cd "$here/../.." && pwd)
client="$root/dev/client-tests"

if [ ! -d "$client/node_modules/playwright" ]; then
    echo "Run 'npm ci && npx playwright install chromium' in dev/client-tests first." >&2
    exit 2
fi
if [ -f "$client/.env.local" ]; then
    set -a
    # shellcheck disable=SC1091
    . "$client/.env.local"
    set +a
fi
export E2E_BASE_URL="${E2E_BASE_URL:-http://localhost:8080}"
export COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml:compose.dev.yaml}"
export NODE_PATH="$client/node_modules${NODE_PATH:+:$NODE_PATH}"

cd "$root"
exec node tests/visual/run.js "$@"
