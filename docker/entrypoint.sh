#!/bin/sh
# Entrypoint for the app image.
#
#   docker-entrypoint migrate   wait for DB -> locked `php artisan migrate --force` -> exit
#   docker-entrypoint <cmd...>  cache config/routes/views/events -> wait for DB -> exec <cmd>
#                               (php-fpm for laravel-app, php artisan queue:work for laravel-queue)
#
# Env:
#   LARAVEL_OPTIMIZE=true  run the artisan cache commands before <cmd>
#   WAIT_FOR_DB=true       wait for the database before <cmd>
#   DB_WAIT_TIMEOUT=60     seconds to wait for the database
set -eu

log() { echo "[entrypoint] $*" >&2; }

if [ "${1:-}" = "migrate" ]; then
    exec php /usr/local/lib/laravel/db.php migrate
fi

if [ "${LARAVEL_OPTIMIZE:-true}" = "true" ]; then
    # These caches depend on runtime env vars (config:cache bakes them in),
    # so they are built at container start, never in the image.
    log "caching config, routes, views and events"
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
    php /usr/local/lib/laravel/db.php wait
fi

log "starting: $*"
exec "$@"
