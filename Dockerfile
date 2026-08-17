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

# ----------------- ส่วนที่เพิ่มเข้ามา -----------------
# 1. ลบ config เริ่มต้นที่ทำให้เกิด error port 80 และ 443 ชนกัน
RUN rm -f /opt/docker/etc/nginx/vhost.conf /opt/docker/etc/nginx/vhost.ssl.conf

# 2. Copy ไฟล์ vhost.conf ของเราเข้าไปแทนที่
COPY vhost.conf /opt/docker/etc/nginx/vhost.conf
# --------------------------------------------------

RUN chown -R application:application storage bootstrap/cache