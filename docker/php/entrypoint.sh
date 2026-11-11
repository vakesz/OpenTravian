#!/bin/sh
# php-fpm starts as root and drops workers to www-data. Pool size, the Redis
# session password and the dev OPcache switch are written here so the image
# stays read-only and the password never lands in it. The engine runs as
# www-data and does not host FPM, so it keeps the image defaults.
set -e
if [ "$(id -u)" = "0" ]; then
    # /usr/local/etc/php-fpm.d/zz-travian-tuning.conf is a symlink into /run/travian
    # (a tmpfs when the root filesystem is read-only). PHP_INI_SCAN_DIR, set on
    # the php service, reads the ini files written here after the image ini.
    max_children="${TRAVIAN_FPM_MAX_CHILDREN:-8}"
    start_servers="${TRAVIAN_FPM_START_SERVERS:-3}"
    min_spare_servers="${TRAVIAN_FPM_MIN_SPARE_SERVERS:-2}"
    max_spare_servers="${TRAVIAN_FPM_MAX_SPARE_SERVERS:-5}"
    max_requests="${TRAVIAN_FPM_MAX_REQUESTS:-500}"

    for value in "$max_children" "$start_servers" "$min_spare_servers" "$max_spare_servers" "$max_requests"; do
        case "$value" in
            ''|*[!0-9]*)
                echo "FPM tuning values must be positive integers." >&2
                exit 1
                ;;
        esac
    done

    if [ "$max_children" -lt 1 ] || [ "$start_servers" -lt "$min_spare_servers" ] \
        || [ "$start_servers" -gt "$max_spare_servers" ] || [ "$max_spare_servers" -gt "$max_children" ] \
        || [ "$max_requests" -lt 1 ]; then
        echo "Invalid FPM pool sizing." >&2
        exit 1
    fi

    mkdir -p /run/travian
    cat > /run/travian/fpm-tuning.conf <<EOF
[www]
pm.max_children = $max_children
pm.start_servers = $start_servers
pm.min_spare_servers = $min_spare_servers
pm.max_spare_servers = $max_spare_servers
pm.max_requests = $max_requests
EOF

    # Password stays in this root-only file on tmpfs. Hex passwords from
    # bootstrap-env.sh are URL-safe; anything else is refused rather than
    # written into the save path.
    redis_password="${TRAVIAN_REDIS_PASSWORD:-}"
    case "$redis_password" in
        ''|*[!A-Za-z0-9._~-]*)
            echo "TRAVIAN_REDIS_PASSWORD must be URL-safe so it can go in the session save path." >&2
            exit 1
            ;;
    esac
    redis_host="${TRAVIAN_REDIS_HOST:-redis}"
    redis_port="${TRAVIAN_REDIS_PORT:-6379}"
    case "$redis_port" in
        ''|*[!0-9]*)
            echo "TRAVIAN_REDIS_PORT must be a port number." >&2
            exit 1
            ;;
    esac
    umask 077
    cat > /run/travian/session.ini <<EOF
session.save_handler = redis
session.save_path = "tcp://${redis_host}:${redis_port}?auth=${redis_password}&persistent=1&timeout=1&read_timeout=2&prefix=travian_session:"
EOF
    # Bind-mounted checkouts change under the process. Production leaves this off.
    if [ "${TRAVIAN_DEV_TOOLS:-}" = "1" ]; then
        cat > /run/travian/opcache.ini <<EOF
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
EOF
    fi
    umask 022
fi

# `docker compose run --rm php install` runs the headless installer (see
# tools/install.php) as the unprivileged user that owns the state volume.
if [ "${1:-}" = "install" ]; then
    shift
    set -- php /travian/tools/install.php "$@"
    if [ "$(id -u)" = "0" ]; then
        set -- runuser -u www-data -- "$@"
    fi
fi
exec docker-php-entrypoint "$@"
