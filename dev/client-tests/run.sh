#!/bin/sh
# Run the Node client checks in tests/client.
#
#   dev/client-tests/run.sh               all tests/client/test-*.js
#   dev/client-tests/run.sh <name> ...    selected tests (file name, with or without .js)
#
# Browser tests (those that require "playwright") read E2E settings from the
# gitignored dev/client-tests/.env.local; see .env.example. Install once:
#   cd dev/client-tests && npm ci && npx playwright install chromium
set -eu

here=$(cd "$(dirname "$0")" && pwd)
root=$(cd "$here/../.." && pwd)

if [ ! -d "$here/node_modules/playwright" ]; then
    echo "Run 'npm ci && npx playwright install chromium' in dev/client-tests first." >&2
    exit 2
fi

if [ -f "$here/.env.local" ]; then
    set -a
    # shellcheck disable=SC1091
    . "$here/.env.local"
    set +a
fi
export E2E_BASE_URL="${E2E_BASE_URL:-http://localhost:8080}"
[ -z "${E2E_USER:-}" ] || export E2E_USERNAME="$E2E_USER"
# Resolve a relative password file against dev/client-tests.
case "${E2E_PASSWORD_FILE:-}" in
    ''|/*) ;;
    *) E2E_PASSWORD_FILE="$here/$E2E_PASSWORD_FILE"; export E2E_PASSWORD_FILE ;;
esac
# Let tests under tests/client resolve the dev-only playwright package.
export NODE_PATH="$here/node_modules${NODE_PATH:+:$NODE_PATH}"

if [ $# -eq 0 ]; then
    set -- "$root"/tests/client/test-*.js
else
    for i in "$@"; do shift; n=${i%.js}; set -- "$@" "$root/tests/client/${n##*/}.js"; done
fi

status=0
for t in "$@"; do
    echo "== ${t#"$root"/}"
    node "$t" || status=1
done
exit $status
