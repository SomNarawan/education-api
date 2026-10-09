#!/bin/sh
# Entrypoint for the app image.
#
#   docker-entrypoint migrate   wait for DB -> locked `php artisan migrate --force` -> exit
#   docker-entrypoint <cmd...>  cache config/routes/views/events -> wait for DB -> exec <cmd>
#                               (php-fpm for laravel-app, php artisan queue:work for laravel-queue)
#
# Every stage is recorded with status.php (keyed by CONTAINER_ROLE), so the
# state of each container can be read over HTTP at /_status.
#
# Env:
#   LARAVEL_OPTIMIZE=true  run the artisan cache commands before <cmd>
#   WAIT_FOR_DB=true       wait for the database before <cmd>
#   DB_WAIT_TIMEOUT=60     seconds to wait for the database
#   DB_HOST                127.0.0.1 / localhost / ::1 become host.docker.internal
set -eu

log() { echo "[entrypoint] $*" >&2; }
status() { php /usr/local/lib/laravel/status.php "$@" || true; }

# The same .env serves `php artisan serve` (MySQL on 127.0.0.1) and this
# container, where 127.0.0.1 is the container itself and never has MySQL.
# Point loopback at the Docker host instead (resolves on Linux via
# extra_hosts host-gateway in docker-compose.yml).
case "${DB_HOST:-}" in
    127.0.0.1 | localhost | ::1)
        log "DB_HOST=${DB_HOST} is this container; using host.docker.internal instead"
        export DB_HOST=host.docker.internal
        ;;
esac

# Runs `php artisan <args>`; on failure records the output tail and exits.
artisan_step() {
    if ! output=$(php artisan "$@" 2>&1); then
        echo "$output" >&2
        status boot_failed "php artisan $* failed" "$(printf '%s\n' "$output" | tail -n 40)"
        exit 1
    fi
    echo "$output"
}

if [ "${1:-}" = "migrate" ]; then
    exec php /usr/local/lib/laravel/db.php migrate
fi

status booting "starting container"

if [ "${LARAVEL_OPTIMIZE:-true}" = "true" ]; then
    # These caches depend on runtime env vars (config:cache bakes them in),
    # so they are built at container start, never in the image.
    log "caching config, routes, views and events"
    artisan_step config:cache
    artisan_step route:cache
    artisan_step view:cache
    artisan_step event:cache
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    php /usr/local/lib/laravel/db.php wait
fi

log "starting: $*"
status running "started: $*"
exec "$@"
