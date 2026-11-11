#!/bin/sh
# One-command checks against the running dev stack (dev only).
#
#   dev/check.sh              everything: compose config, php -l, contracts, runtime
#                             checks, PHPStan, Playwright client tests, nginx -t
#   dev/check.sh --fast       pre-commit subset: lint and PHPStan on changed files,
#                             changed contracts, no-minified and gpack contracts
#   dev/check.sh --no-browser skip the Playwright tests
#   dev/check.sh --visual     also compare screenshots of the key routes with tests/visual/baselines
#   dev/check.sh --build      also build the production images
#
# Contracts and runtime checks run one PHP process per file (one process for all
# of them runs out of memory), 40 files per `compose exec`, 60 s each. A file
# fails on a non-zero exit, a timeout, or any PHP Warning/Notice/Deprecated.
# "Changed" means modified, staged or untracked files in the working tree.
set -u

cd "$(dirname "$0")/.." || exit 2

fast=0 browser=1 build=0 visual=0
for arg in "$@"; do
    case "$arg" in
        --fast) fast=1 ;;
        --no-browser) browser=0 ;;
        --visual) visual=1 ;;
        --build) build=1 ;;
        *) echo "Usage: dev/check.sh [--fast] [--no-browser] [--visual] [--build]" >&2; exit 2 ;;
    esac
done

# The dev stack bind-mounts the checkout (compose.dev.yaml); plain compose.yaml is
# the production configuration. Set explicitly so the result never depends on .env.
export COMPOSE_FILE="${COMPOSE_FILE:-compose.yaml:compose.dev.yaml}"

if [ -z "$(docker compose ps --status running -q php 2>/dev/null)" ]; then
    echo "dev/check.sh: Docker or the dev stack is not running (docker compose up -d; first install: dev/install-world.sh). To skip a commit hook: git commit --no-verify." >&2
    exit 2
fi

tmp=$(mktemp -d "${TMPDIR:-/tmp}/travian-check.XXXXXX")
trap 'rm -rf "$tmp"' EXIT INT TERM
failed=0
: > "$tmp/summary"

# step <name> <command...>: run it, print its output only on failure.
step() {
    name=$1; shift
    echo "== $name" >&2
    start=$(date +%s)
    if "$@" > "$tmp/out" 2>&1; then result=PASS; else result=FAIL; failed=1; tail -n 60 "$tmp/out" >&2; fi
    printf '%-34s %-5s %4ss\n' "$name" "$result" $(($(date +%s) - start)) >> "$tmp/summary"
}

php_exec() { docker compose exec -T -w /travian php "$@" </dev/null; }

# php_files <files...>: one process per file, in batches.
php_files() {
    printf '%s\n' "$@" | xargs -n 40 docker compose exec -T -w /travian php sh -c '
        for f in "$@"; do
            out=$(timeout 60 php -d error_reporting=-1 -d display_errors=1 "$f" 2>&1 </dev/null) || { echo "FAIL $f"; echo "$out" | tail -n 5; continue; }
            echo "$out" | grep -Eq "(PHP )?(Warning|Notice|Deprecated): " && { echo "FAIL $f"; echo "$out" | grep -E "(Warning|Notice|Deprecated): " | head -n 3; }
        done' _ > "$tmp/php" 2>&1
    cat "$tmp/php"
    ! grep -q '^FAIL ' "$tmp/php"
}

lint() { php_exec sh -c 'for f in "$@"; do php -l "$f" >/dev/null || exit 1; done' _ "$@"; }

changed=$({ git diff --name-only --diff-filter=ACMR HEAD; git ls-files -o --exclude-standard; } | sort -u | while read -r f; do [ -f "$f" ] && echo "$f"; done)
changed_php=$(echo "$changed" | grep '\.php$' | grep -v '/vendor/' || true)

if [ "$fast" = 1 ]; then
    # shellcheck disable=SC2086
    [ -z "$changed_php" ] || step "php -l (changed)" lint $changed_php
    # shellcheck disable=SC2046
    step "contracts (guards + changed)" php_files \
        tests/contracts/test-no-minified-assets-contract.php \
        tests/contracts/test-gpack-css-assets-contract.php \
        tests/contracts/test-gpack-static-asset-contract.php \
        $(echo "$changed" | grep '^tests/contracts/test-.*\.php$')
    # shellcheck disable=SC2086
    [ -z "$changed_php" ] || step "phpstan (changed)" dev/phpstan/run.sh --files $changed_php
else
    step "compose config" sh -c '
        docker compose config --quiet &&
        COMPOSE_FILE=compose.yaml docker compose config --quiet &&
        COMPOSE_FILE=compose.yaml:compose.build.yaml docker compose config --quiet &&
        COMPOSE_FILE=compose.yaml:compose.tls.yaml TRAVIAN_DOMAIN=game.example.test docker compose config --quiet'
    step "php -l (all)" php_exec sh -c 'find . -name "*.php" ! -path "*/vendor/*" ! -path "*/node_modules/*" -print0 | xargs -0 -n1 php -l >/dev/null'
    # shellcheck disable=SC2046
    step "contracts" php_files $(ls tests/contracts/test-*.php)
    # shellcheck disable=SC2046
    step "runtime" php_files $(ls tests/runtime/test-*.php)
    step "phpstan" dev/phpstan/run.sh
    [ "$browser" = 0 ] || step "client tests" dev/client-tests/run.sh
    [ "$visual" = 0 ] || step "visual regression" dev/visual-tests/run.sh
    step "nginx -t" sh -c '
        image=$(sed -n "s/^ *image: \(nginx:stable-alpine@sha256:[0-9a-f]*\).*/\1/p" compose.dev.yaml | head -n 1)
        docker run --rm -e TRAVIAN_TRUSTED_PROXY=127.0.0.1 -e NGINX_ENVSUBST_FILTER="^TRAVIAN_" \
            -v "$PWD/docker/nginx/default.conf.template:/etc/nginx/templates/default.conf.template:ro" "$image" nginx -t'
    [ "$build" = 0 ] || step "production build" env COMPOSE_FILE=compose.yaml:compose.build.yaml docker compose build
fi

echo >&2
cat "$tmp/summary" >&2
[ "$failed" = 0 ] && echo "dev/check.sh: all passed." >&2
exit "$failed"
