#!/bin/sh
# One-command checks against the running dev stack (dev only).
#
#   dev/check.sh              everything: compose config, php -l, contracts, runtime
#                             checks, PHPStan, Playwright client tests, nginx -t
#   dev/check.sh --fast       pre-commit subset: lint and PHPStan on changed files,
#                             changed contracts, no-minified and gpack contracts
#   dev/check.sh --no-browser skip the Playwright tests
#   dev/check.sh --visual     also compare screenshots of the key routes with tests/visual/baselines
#   dev/check.sh --build      also build the production images (CI does that in its own job)
#
# Contracts and runtime checks run one PHP process per file (one process for all
# of them runs out of memory), CHECK_BATCH files (default 10) per `compose exec`,
# CHECK_JOBS batches at once (default 4), 60 s per file. A file fails on a
# non-zero exit, a timeout, or any PHP Warning/Notice/Deprecated. Tests that share
# world state run one at a time, after the parallel ones: they are listed, with
# the reason, in dev/check-serial-contracts.txt and dev/check-serial-runtime.txt.
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

jobs=${CHECK_JOBS:-4}
batch=${CHECK_BATCH:-10}

# The per-file check, run inside the container. Kept in a variable so xargs can hand it to every batch.
php_batch='
    for f in "$@"; do
        out=$(timeout 60 php -d error_reporting=-1 -d display_errors=1 "$f" 2>&1 </dev/null) || { echo "FAIL $f"; echo "$out" | tail -n 5; continue; }
        echo "$out" | grep -Eq "(PHP )?(Warning|Notice|Deprecated): " && { echo "FAIL $f"; echo "$out" | grep -E "(Warning|Notice|Deprecated): " | head -n 3; }
    done'

# run_batches <jobs> <files...>: one process per file, in batches of $batch, <jobs> batches
# at once. Every batch writes its own file, so output from parallel batches never
# interleaves mid-line; the files are printed together afterwards.
run_batches() {
    n=$1; shift
    dir=$(mktemp -d "$tmp/php.XXXXXX")
    printf '%s\n' "$@" | xargs -n "$batch" -P "$n" sh -c '
        dir=$1 script=$2; shift 2
        docker compose exec -T -w /travian php sh -c "$script" _ "$@" > "$(mktemp "$dir/batch.XXXXXX")" 2>&1' _ "$dir" "$php_batch"
    cat "$dir"/batch.*
    ! cat "$dir"/batch.* | grep -q '^FAIL '
}
php_files() { run_batches "$jobs" "$@"; }
php_files_serial() { run_batches 1 "$@"; }

# serial_list <file>: the paths in a serial list, without comments and blank lines.
serial_list() { grep -v '^[[:space:]]*\(#\|$\)' "$1"; }

# split_tests <dir> <serial list>: sets parallel_tests and serial_tests for the tests in <dir>.
# A listed file that no longer exists fails the run, so the lists cannot go stale unnoticed.
split_tests() {
    all=$(ls "$1"/test-*.php)
    serial_tests=$(serial_list "$2")
    for f in $serial_tests; do
        echo "$all" | grep -qxF "$f" || { echo "dev/check.sh: $2 lists $f, which does not exist." >&2; exit 2; }
    done
    parallel_tests=$(echo "$all" | grep -vxF "$serial_tests")
}

# php -l takes many files and keeps going after an error, but exits 255, which would
# make xargs stop starting batches; report the errors and exit 1 instead.
lint_batch='out=$(php -l "$@") || { echo "$out" | grep -v "^No syntax errors"; exit 1; }'

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
    step "php -l (all)" php_exec sh -c '
        find . -name "*.php" ! -path "*/vendor/*" ! -path "*/node_modules/*" -print0 | xargs -0 -n 50 -P "$1" sh -c "$2" _' _ "$jobs" "$lint_batch"
    split_tests tests/contracts dev/check-serial-contracts.txt
    # shellcheck disable=SC2086
    step "contracts" php_files $parallel_tests
    # shellcheck disable=SC2086
    [ -z "$serial_tests" ] || step "contracts (serial)" php_files_serial $serial_tests
    split_tests tests/runtime dev/check-serial-runtime.txt
    # shellcheck disable=SC2086
    step "runtime" php_files $parallel_tests
    # shellcheck disable=SC2086
    [ -z "$serial_tests" ] || step "runtime (serial)" php_files_serial $serial_tests
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
