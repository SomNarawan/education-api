# ---- vendor build stage ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ---- runtime stage (nginx + php-fpm bundled) ----
FROM webdevops/php-nginx:8.2-alpine

ENV WEB_DOCUMENT_ROOT=/app/public \
    PHP_MEMORY_LIMIT=256M \
    PHP_DISPLAY_ERRORS=0

WORKDIR /app
COPY --from=vendor /app ./
RUN chown -R application:application storage bootstrap/cache
