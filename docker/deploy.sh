#!/bin/sh
# Production deploy: build -> migrate (gate) -> roll out.
#
#   docker/deploy.sh [env-file]        default env-file: .env.production
#
# Migrations run in a one-off container BEFORE any running container is
# replaced. If they fail the script stops and the current version keeps
# serving. (A plain `docker compose up -d` would stop the old app/queue
# first and leave nothing running when the migration fails.)
#
# Rollback to the image deployed before this one:
#   APP_TAG=previous docker compose --env-file .env.production up -d --wait
set -eu

cd "$(dirname "$0")/.."
ENV_FILE="${1:-.env.production}"
IMAGE="${APP_IMAGE:-education-api}"

compose() { docker compose --env-file "$ENV_FILE" "$@"; }

echo "==> [1/4] keep current images as :previous (for rollback)"
for img in "$IMAGE" "$IMAGE-nginx"; do
    if docker image inspect "$img:latest" >/dev/null 2>&1; then
        docker tag "$img:latest" "$img:previous"
    fi
done

echo "==> [2/4] build images"
compose build laravel-app nginx

echo "==> [3/4] run migrations with the new image (running version keeps serving)"
if ! compose run --rm laravel-migrate; then
    # Point :latest back at the running version so a later `up` or restart
    # does not pick up the image that failed.
    for img in "$IMAGE" "$IMAGE-nginx"; do
        if docker image inspect "$img:previous" >/dev/null 2>&1; then
            docker tag "$img:previous" "$img:latest"
        fi
    done
    echo "!!  migration failed: deploy aborted, the running version was not touched" >&2
    exit 1
fi

echo "==> [4/4] roll out app, queue worker, nginx"
compose up -d --wait --remove-orphans

docker image prune -f >/dev/null
compose ps
echo "==> deploy finished"
