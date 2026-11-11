#!/bin/sh
# Run PHPStan inside the php container.
#
#   dev/phpstan/run.sh [target ...] [-- extra phpstan args]
#
#   dev/phpstan/run.sh --files <path.php> ...   only these files (repo-relative)
#
# Targets: game, api, tests, all (default; runs each in turn and fails if any
# fails). Each target has its own config (game.neon, api.neon, tests.neon).
# With --files, each path is analysed under the target that owns it (game/ ->
# game, services/api/ -> api, tests/ -> tests); targets without files are
# skipped and paths outside every target are ignored.
# Environment:
#   PHPSTAN_LEVEL   analysis level (default: the level in phpstan.neon)
#   PHPSTAN_MEMORY  PHP memory_limit (default 3G)
set -eu

root=$(cd "$(dirname "$0")/../.." && pwd)
cd "$root"

if [ ! -f dev/phpstan/phpstan.phar ]; then
    echo "dev/phpstan/phpstan.phar is missing; run dev/phpstan/fetch.sh first." >&2
    exit 2
fi

targets=""
files=""
while [ $# -gt 0 ]; do
    case "$1" in
        --) shift; break ;;
        --files) shift; files="$*"; break ;;
        game|api|tests) targets="$targets $1" ;;
        all) targets="game api tests" ;;
        *) echo "Unknown target: $1 (use game, api, tests, all)" >&2; exit 2 ;;
    esac
    shift
done
[ -n "$targets" ] || targets="game api tests"

level_args=""
if [ -n "${PHPSTAN_LEVEL:-}" ]; then level_args="--level=$PHPSTAN_LEVEL"; fi

status=0
for target in $targets; do
    paths=""
    if [ -n "$files" ]; then
        case "$target" in
            game) prefix="game/" ;;
            api) prefix="services/api/" ;;
            tests) prefix="tests/" ;;
        esac
        for f in $files; do
            case "$f" in
                */vendor/*|*/Templates/Cache/*|*.local.php|game/include/Plugins/securimage/*|game/include/jdf.php) continue ;;
                "$prefix"*.php) [ -f "$f" ] && paths="$paths $f" ;;
            esac
        done
        [ -n "$paths" ] || continue
    fi
    echo "== phpstan: $target${paths:+ (files)}" >&2
    # shellcheck disable=SC2086
    docker compose exec -T -w /travian php php -d "memory_limit=${PHPSTAN_MEMORY:-3G}" \
        dev/phpstan/phpstan.phar analyse \
        --configuration="dev/phpstan/$target.neon" --no-progress --no-ansi $level_args $paths "$@" \
        || status=1
done
exit $status
