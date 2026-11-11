#!/bin/sh
# Runs php-fpm and nginx side by side (the job supervisord did in the old
# webdevops image). If either one exits, the container exits so Docker's
# restart policy can bring it back.

cd /app

# storage/app is a volume or a bind-mounted host folder (docker-compose.yml,
# API_STORAGE_PATH). A fresh host folder is owned by root, so hand it to
# www-data (uid 82) or imports cannot write their result files.
chown -R www-data:www-data storage/app

# Cache config, routes, events and views. Done here, not at build time,
# because the config comes from the container's environment. Run as www-data
# so the cached files stay writable by the app. If caching fails the API still
# starts, uncached, and the error is in `docker compose logs backend`.
if ! su www-data -s /bin/sh -c 'php artisan optimize'; then
    echo "start: php artisan optimize failed - running without caches" >&2
    su www-data -s /bin/sh -c 'php artisan optimize:clear' || true
fi

php-fpm -F &
fpm=$!
nginx -g 'daemon off;' &
web=$!

trap 'kill -QUIT $fpm $web 2>/dev/null' TERM INT QUIT

wait -n
status=$?
kill -QUIT $fpm $web 2>/dev/null
wait
exit $status
