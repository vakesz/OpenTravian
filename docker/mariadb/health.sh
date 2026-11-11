#!/bin/sh
# Health check of the db service plus rotation of the MariaDB slow-query log.
#
# Docker runs this every few seconds inside the db container, as root, with the container's
# environment. The health check is the image's own healthcheck.sh. Rotation needs the database
# administrator (FLUSH SLOW LOGS), which only this container holds, so it lives here rather than
# in a service that would need the administrator password: once slow.log is larger than
# TRAVIAN_MARIADB_SLOW_LOG_MAX_MB it is renamed to slow.log.1 (older generations shift up, at most
# TRAVIAN_MARIADB_SLOW_LOG_KEEP are kept) and the server is told to reopen a fresh slow.log.
#
# "rotate-only" skips the health check and TRAVIAN_SLOW_LOG_FILE / TRAVIAN_SLOW_LOG_FLUSH replace
# the log path and the flush command; tests/contracts/test-slow-log-rotation-contract.php uses them.

if [ "$1" != "rotate-only" ]; then
    healthcheck.sh --connect --innodb_initialized || exit 1
fi

log="${TRAVIAN_SLOW_LOG_FILE:-/var/lib/mysql/slow.log}"
max_mb="${TRAVIAN_MARIADB_SLOW_LOG_MAX_MB:-50}"
keep="${TRAVIAN_MARIADB_SLOW_LOG_KEEP:-3}"

[ -f "$log" ] || exit 0
size=$(stat -c %s "$log" 2>/dev/null || echo 0)
[ "$size" -gt $((max_mb * 1048576)) ] || exit 0

i="$keep"
rm -f "$log.$i"
while [ "$i" -gt 1 ]; do
    previous=$((i - 1))
    [ -f "$log.$previous" ] && mv "$log.$previous" "$log.$i"
    i="$previous"
done
mv "$log" "$log.1" || exit 0

if [ -n "$TRAVIAN_SLOW_LOG_FLUSH" ]; then
    sh -c "$TRAVIAN_SLOW_LOG_FLUSH"
else
    # The password stays in the environment, never on a command line.
    MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot -e 'FLUSH SLOW LOGS'
fi
exit 0
