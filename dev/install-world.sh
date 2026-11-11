#!/bin/sh
# Install a local world headlessly with the installer service (dev and CI only).
#
#   dev/install-world.sh [--reset]
#
# Needs the stack up (docker compose up -d --wait). Idempotent: an installed world
# is left alone. --reset clears non-empty account/world databases first
# (destructive). Creates starter player "player" with a random password kept in
# dev/client-tests/.password.local (an existing file keeps its value; the world is
# not exposed) with no email delivery, and writes dev/client-tests/.env.local when
# it is missing.
set -eu
cd "$(dirname "$0")/.."

player=player
dir=dev/client-tests
if [ -f "$dir/.password.local" ]; then
    password=$(cat "$dir/.password.local")
else
    password=$(openssl rand -hex 12)
    (umask 077; printf '%s' "$password" > "$dir/.password.local")
fi

case "${1:-}" in
    --reset) reset=--reset ;;
    '') reset= ;;
    *) echo "Usage: dev/install-world.sh [--reset]" >&2; exit 2 ;;
esac

# shellcheck disable=SC2086
docker compose run --rm -T \
    -e "TRAVIAN_INSTALL_MULTIHUNTER_PASSWORD=$password" \
    -e "TRAVIAN_INSTALL_PLAYER_NAME=$player" \
    -e "TRAVIAN_INSTALL_PLAYER_PASSWORD=$password" \
    installer install $reset

port=$(sed -n 's/^APP_PORT=//p' .env 2>/dev/null | tail -n 1)
# A reset moves the starter village, so the recorded id must be rewritten too.
if [ -n "$reset" ] || [ ! -f "$dir/.env.local" ]; then
    village=$(docker compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -N travian_world -e "$0"' \
        "SELECT v.kid FROM vdata v JOIN users u ON u.id = v.owner WHERE u.name = '$player' ORDER BY v.capital DESC LIMIT 1" | tr -d '\r\n')
    printf 'E2E_BASE_URL=http://localhost:%s\nE2E_USER=%s\nE2E_PASSWORD_FILE=.password.local\nE2E_VILLAGE_ID=%s\n' "${port:-8080}" "$player" "$village" > "$dir/.env.local"
fi
