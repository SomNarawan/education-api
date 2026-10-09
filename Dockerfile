# syntax=docker/dockerfile:1.7
#
# Targets
#   app   (default) PHP-FPM runtime. The same image runs laravel-app,
#                   laravel-queue and laravel-migrate (see docker-compose.yml).
#   nginx           nginx-unprivileged + public/ only, proxies PHP to laravel-app.

ARG PHP_VERSION=8.2

# -----------------------------------------------------------------------------
# base: PHP-FPM + only the extensions this app needs
# -----------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS base

# Every ext-* required by the non-dev packages in composer.lock (mbstring, zlib,
# simplexml, dom, fileinfo, ctype, tokenizer, openssl, ...) is already compiled
# into the official image. The ones added here are:
#   pdo_mysql  database driver (DB_CONNECTION=mysql)
#   pcntl      lets queue:work catch SIGTERM and enforce --timeout
#   opcache    bytecode cache
# fcgi provides cgi-fcgi for the php-fpm healthcheck.
# The build stage re-checks these against composer.lock, so a missing
# extension fails the build instead of failing in production.
RUN set -eux; \
    apk add --no-cache fcgi; \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql pcntl opcache; \
    apk del --no-network .build-deps; \
    docker-php-source delete; \
    rm -rf /tmp/* /usr/src/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/php.ini      $PHP_INI_DIR/conf.d/zz-app.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Non-secret defaults; override at runtime through docker-compose environment.
# php.ini / php-fpm.conf read the PHP_* and FPM_* values via ${VAR}.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PHP_MEMORY_LIMIT=256M \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_UPLOAD_MAX_FILESIZE=25M \
    PHP_POST_MAX_SIZE=26M \
    PHP_OPCACHE_MEMORY=128 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    FPM_PM_MAX_CHILDREN=10 \
    FPM_PM_START_SERVERS=2 \
    FPM_PM_MIN_SPARE_SERVERS=2 \
    FPM_PM_MAX_SPARE_SERVERS=4 \
    FPM_PM_MAX_REQUESTS=500

WORKDIR /var/www/html

# -----------------------------------------------------------------------------
# build: composer install on the same PHP base as runtime, so the PHP version
# and extension requirements in composer.lock are verified against the real
# runtime. Composer, unzip and the composer cache never reach the final image.
# -----------------------------------------------------------------------------
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apk add --no-cache unzip
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache

# Dependencies first: this layer is reused until composer.json/lock change.
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# Packages are already in vendor/, so this only builds the authoritative
# classmap and runs package:discover for the production package set.
RUN set -eux; \
    composer install --no-dev --optimize-autoloader --classmap-authoritative --no-progress; \
    php artisan storage:link; \
    # Shown by /api/health, to tell which build is running.
    printf '{"built_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > build-info.json; \
    rm -rf docker; \
    mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs bootstrap/cache; \
    # Build contexts from Windows arrive as 0755; normalise to 0755 dirs / 0644 files.
    find . -type d -exec chmod 0755 {} +; \
    find . -type f -exec chmod 0644 {} +

# -----------------------------------------------------------------------------
# nginx: static files + FastCGI proxy, runs as uid 101
# -----------------------------------------------------------------------------
FROM nginxinc/nginx-unprivileged:stable-alpine AS nginx

# Rendered to /etc/nginx/conf.d/default.conf at start; only HEALTH_* vars are
# substituted. HEALTH_TOKEN empty = /_status disabled.
ENV NGINX_ENVSUBST_FILTER=^HEALTH_ \
    HEALTH_TOKEN=
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /var/www/html/public /var/www/html/public

EXPOSE 3009

# -----------------------------------------------------------------------------
# app: final runtime image (php-fpm / queue worker / migrations)
# -----------------------------------------------------------------------------
FROM base AS app

COPY --chmod=0755 docker/entrypoint.sh       /usr/local/bin/docker-entrypoint
COPY --chmod=0755 docker/php/healthcheck.sh  /usr/local/bin/php-fpm-healthcheck
COPY docker/php/db.php                       /usr/local/lib/laravel/db.php
COPY docker/php/status.php                   /usr/local/lib/laravel/status.php

# Code stays root-owned (read-only for the app); only the paths Laravel writes
# to belong to www-data.
COPY --from=build /var/www/html /var/www/html
RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 9000
# php-fpm finishes in-flight requests on SIGQUIT (SIGTERM would drop them).
STOPSIGNAL SIGQUIT

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
