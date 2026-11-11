#!/bin/sh
# Create or complete .env: copies .env.example when .env is missing, generates the
# required secrets and fills the host user and group ids. A value that is already
# set is never changed, so this is safe to run again.
#
#   sh config/bootstrap-env.sh          production configuration
#   sh config/bootstrap-env.sh --dev    also select the development layer
set -eu
cd "$(dirname "$0")/.."

dev=0
case "${1:-}" in
    --dev) dev=1 ;;
    '') ;;
    *) echo "Usage: sh config/bootstrap-env.sh [--dev]" >&2; exit 2 ;;
esac

command -v openssl >/dev/null 2>&1 || { echo "openssl is required to generate secrets." >&2; exit 1; }

if [ ! -f .env ]; then
    (umask 077; cp .env.example .env)
    echo "Created .env from .env.example."
fi
chmod 600 .env

# set_value KEY VALUE: fill KEY only when it is missing or empty.
set_value() {
    current=$(sed -n "s/^$1=//p" .env | tail -n 1)
    [ -z "$current" ] || return 0
    tmp=$(mktemp .env.XXXXXX)
    if grep -q "^$1=" .env; then
        awk -v key="$1" -v value="$2" 'BEGIN { FS = OFS = "=" } $1 == key { print key "=" value; next } { print }' .env > "$tmp"
    else
        cat .env > "$tmp"
        printf '%s=%s\n' "$1" "$2" >> "$tmp"
    fi
    chmod 600 "$tmp"
    mv "$tmp" .env
    echo "Set $1."
}

set_value MARIADB_ROOT_PASSWORD "$(openssl rand -hex 32)"
set_value REDIS_PASSWORD "$(openssl rand -hex 32)"
set_value TRAVIAN_SETUP_TOKEN "$(openssl rand -hex 32)"
set_value APP_UID "$(id -u)"
set_value APP_GID "$(id -g)"
if [ "$dev" = 1 ]; then
    set_value COMPOSE_FILE "compose.yaml:compose.dev.yaml"
fi

echo
echo "Setup token (the wizard asks for it): $(sed -n 's/^TRAVIAN_SETUP_TOKEN=//p' .env | tail -n 1)"
