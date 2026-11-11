#!/bin/sh
# chkconfig: 345 95 5
# description: Travian game engine
#
# Replace the [SERVER_*] placeholders before installing:
#   [SERVER_BASH_LOCATION]  command that runs the engine, e.g.
#                           /usr/bin/php /path/to/game/deployment/include/Automation.php
#   [SERVER_WORLD_ID]       world id, used in messages
#   [SERVER_PID_LOCATION]   file where this script records the foreground
#                           engine process ID.
. /etc/init.d/functions

action=${1:-}
# This placeholder is installer-controlled shell words, not an arbitrary runtime command string. Quoting lets an
# installer preserve an argument with whitespace; after this single expansion the program is always executed directly.
set -- [SERVER_BASH_LOCATION]
engine_bin=${1:-}
if [ -n "$engine_bin" ]; then
    shift
fi
prog="[SERVER_WORLD_ID]Daemon"
proc="[SERVER_PID_LOCATION]"
RETVAL=0

resolve_engine_bin() {
    if [ -z "$engine_bin" ]; then
        return 1
    fi
    case "$engine_bin" in
        /*) ;;
        *) engine_bin=$(command -v "$engine_bin" 2>/dev/null || true) ;;
    esac
    [ -n "$engine_bin" ] && [ -x "$engine_bin" ] || return 1
    if command -v readlink >/dev/null 2>&1; then
        resolved=$(readlink -f "$engine_bin" 2>/dev/null || true)
        [ -n "$resolved" ] && engine_bin=$resolved
    fi
}

pid_matches_engine() {
    pid=$1
	resolve_engine_bin || return 1
    case "$pid" in
        ''|*[!0-9]*) return 1 ;;
    esac
    kill -0 "$pid" >/dev/null 2>&1 || return 1
    [ -r "/proc/$pid/exe" ] || return 1
    actual=$(readlink "/proc/$pid/exe" 2>/dev/null || true)
    [ "$actual" = "$engine_bin" ]
}

read_pid() {
    [ -s "$proc" ] || return 1
    pid=$(cat "$proc" 2>/dev/null)
    case "$pid" in
        ''|*[!0-9]*) return 1 ;;
    esac
    printf '%s\n' "$pid"
}

is_running() {
    pid=$(read_pid || true)
    if [ -n "$pid" ] && pid_matches_engine "$pid"; then
        return 0
    fi
    [ -e "$proc" ] && rm -f "$proc"
    return 1
}

stop_pid() {
    pid=$1
    pid_matches_engine "$pid" || return 1
    kill -TERM "$pid" >/dev/null 2>&1 || return 1
    attempt=0
    while pid_matches_engine "$pid" && [ "$attempt" -lt 30 ]; do
        sleep 1
        attempt=$((attempt + 1))
    done
    ! pid_matches_engine "$pid"
}

start() {
    if ! resolve_engine_bin; then
        printf '%s\n' "Invalid engine command in [SERVER_BASH_LOCATION]."
        return 1
    fi
    if is_running; then
        printf '%s\n' "$prog is already running"
        return 0
    fi
    rm -f "$proc"
    printf 'Starting %s: ' "$prog"
    TRAVIAN_FOREGROUND=1 "$engine_bin" "$@" >/dev/null 2>&1 &
    pid=$!
    sleep 1
    if pid_matches_engine "$pid"; then
        printf '%s\n' "$pid" > "$proc"
        RETVAL=0
    else
        RETVAL=1
    fi
    printf '\n'
    return "$RETVAL"
}

stop() {
    printf 'Stopping %s: ' "$prog"
    RETVAL=1
    pid=$(read_pid || true)
    if [ -n "$pid" ] && stop_pid "$pid"; then
        rm -f "$proc"
        RETVAL=0
    elif [ -e "$proc" ]; then
        # Never signal an unverified or reused PID; only discard this script's stale marker.
        rm -f "$proc"
    fi
    printf '\n'
    return "$RETVAL"
}

restart() {
    stop
	start "$@"
}

status_at() {
    if is_running; then
        printf '%s\n' "$prog is running"
        RETVAL=0
    else
        printf '%s\n' "$prog is stopped"
        RETVAL=3
    fi
}

case "$action" in
start)
	start "$@"
    ;;
stop)
    stop
    ;;
reload|restart)
	restart "$@"
    ;;
condrestart)
    if is_running; then
		restart "$@"
    fi
    ;;
status)
    status_at
    ;;
*)
    printf '%s\n' "Usage: $0 {start|stop|restart|condrestart|status}"
    exit 1
    ;;
esac

exit "$RETVAL"
