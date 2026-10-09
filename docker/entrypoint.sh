#!/bin/sh
# Entrypoint for the app and standalone images.
#
#   docker-entrypoint migrate     wait for DB -> locked `php artisan migrate --force` -> exit
#   docker-entrypoint <cmd...>    cache config/routes/views/events -> wait for DB -> exec <cmd>
#                                 (php-fpm for laravel-app, php artisan queue:work for laravel-queue)
#
# Standalone image (make build / make cd, see docker/standalone/supervisord.conf):
#   docker-entrypoint standalone  render the nginx config -> exec supervisord,
#                                 which starts nginx at once and then `boot`
#   docker-entrypoint boot        cache -> wait for DB + migrate -> start php-fpm
#                                 and the queue worker. On failure it exits and
#                                 nginx keeps answering 503; /_status says why.
#
# Every stage is recorded with status.php (keyed by CONTAINER_ROLE), so the
# state of each container can be read over HTTP at /_status.
#
# Env:
#   LARAVEL_OPTIMIZE=true  run the artisan cache commands before <cmd> / in boot
#   WAIT_FOR_DB=true       wait for the database before <cmd>
#   RUN_MIGRATIONS=true    boot: run migrations (false = only wait for the database)
#   DB_WAIT_TIMEOUT=60     seconds to wait for the database
#   DB_HOST                127.0.0.1 / localhost / ::1 become host.docker.internal
set -eu

log() { echo "[entrypoint] $*" >&2; }
status() { php /usr/local/lib/laravel/status.php "$@" || true; }

# The same .env serves `php artisan serve` (MySQL on 127.0.0.1) and this
# container, where 127.0.0.1 is the container itself and never has MySQL.
# Point loopback at the Docker host instead (resolves on Linux via
# extra_hosts host-gateway in docker-compose.yml, or via
# --add-host=host.docker.internal:127.0.0.1 with --network=host in the Makefile).
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

optimize() {
    if [ "${LARAVEL_OPTIMIZE:-true}" = "true" ]; then
        # These caches depend on runtime env vars (config:cache bakes them in),
        # so they are built at container start, never in the image.
        log "caching config, routes, views and events"
        artisan_step config:cache
        artisan_step route:cache
        artisan_step view:cache
        artisan_step event:cache
    fi
}

# Fills ${HEALTH_TOKEN} into the standalone nginx server block (the compose
# nginx image does the same with envsubst). The value lands inside an nginx
# string, so only token characters are accepted (openssl rand -hex 32).
render_nginx_config() {
    token=${HEALTH_TOKEN:-}
    case "$token" in
        *[!A-Za-z0-9_-]*)
            log "HEALTH_TOKEN may only contain A-Z a-z 0-9 _ -; /_status is disabled"
            token=
            ;;
    esac
    sed "s/[$]{HEALTH_TOKEN}/${token}/g" /etc/nginx/templates/default.conf.template \
        > /run/nginx/conf.d/default.conf
}

case "${1:-}" in
    migrate)
        exec php /usr/local/lib/laravel/db.php migrate
        ;;

    standalone)
        status booting "starting nginx; php-fpm and the queue worker start once boot finishes"
        render_nginx_config
        log "starting supervisord: nginx now, php-fpm and queue worker after boot"
        exec supervisord -c /etc/supervisord.conf
        ;;

    boot)
        optimize
        if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
            step=migrate
        else
            step=wait
        fi
        # db.php records its own failure (db_unreachable, db_misconfigured,
        # migrate_failed) in the status file.
        if ! php /usr/local/lib/laravel/db.php "$step"; then
            log "boot stopped at 'db.php $step'; nginx answers 503 until it is fixed (see /_status)."
            log "retry without redeploying: docker exec <container> supervisorctl start boot"
            exit 1
        fi
        if ! output=$(supervisorctl -c /etc/supervisord.conf start php-fpm queue-worker 2>&1); then
            echo "$output" >&2
            status boot_failed "php-fpm or the queue worker did not start" "$output"
            exit 1
        fi
        echo "$output"
        log "boot finished: nginx, php-fpm and queue worker are running"
        status running "nginx, php-fpm and queue worker running"
        exit 0
        ;;
esac

status booting "starting container"
optimize

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    php /usr/local/lib/laravel/db.php wait
fi

log "starting: $*"
status running "started: $*"
exec "$@"
