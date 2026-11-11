#!/bin/sh
# Starts Redis with the default user locked to REDIS_PASSWORD. The password reaches
# Redis as a SHA-256 hash in a mode 600 ACL file on tmpfs, never on the command line,
# and dangerous and administrative commands (CONFIG, FLUSHALL, FLUSHDB, DEBUG, KEYS,
# SHUTDOWN and the like) are denied. The game uses only plain key commands and Lua EVAL.
set -eu

: "${REDIS_PASSWORD:?REDIS_PASSWORD is not set}"

hash=$(printf '%s' "$REDIS_PASSWORD" | sha256sum | cut -d' ' -f1)
(
    umask 077
    printf 'user default on #%s ~* &* +@all -@dangerous -@admin +eval\n' "$hash" > /tmp/redis-users.acl
)
chown redis:redis /tmp/redis-users.acl

# The image entrypoint drops from root to the redis user.
exec docker-entrypoint.sh redis-server /usr/local/etc/redis/redis.conf
