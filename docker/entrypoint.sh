#!/bin/sh
set -e

# Everything the API needs to start lives here (not in docker-compose), so a
# plain `docker run --env-file .env.production` behaves the same as the compose stack.
#
# No migrations: the schema comes from the SQL dump in docker/db, which the
# mysql container loads on its first start (see README, Database Setup).

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set - pass the app's environment (e.g. docker run --env-file .env.production)." >&2
  exit 1
fi

wait_left="${DB_WAIT_TIMEOUT:-60}"
echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 3306), $e, $s, 2) ? 0 : 1);'; do
  wait_left=$((wait_left - 1))
  if [ "$wait_left" -le 0 ]; then
    echo "Database ${DB_HOST}:${DB_PORT:-3306} is not reachable." >&2
    exit 1
  fi
  sleep 1
done

# Cache config, routes, events and views against this container's environment.
php artisan optimize

# "queue": run the worker, with /healthz on :3012 reporting its heartbeat.
# queue:work stays the main process, so docker stop reaches it and a crash
# stops the container (restart: unless-stopped brings it back).
if [ "$1" = "queue" ]; then
  shift
  php -S 0.0.0.0:3012 docker/queue-healthz.php >/dev/null 2>&1 &
  exec php artisan queue:work --tries=1 --timeout=0 --sleep=3 "$@"
fi

exec "$@"
