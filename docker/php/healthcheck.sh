#!/bin/sh
# Healthcheck for the laravel-app container: sends GET /up straight to
# php-fpm over FastCGI. Passing means php-fpm accepts connections and Laravel
# boots and handles a request.
set -eu

response=$(
    REQUEST_METHOD=GET \
    REQUEST_URI=/up \
    SCRIPT_NAME=/index.php \
    SCRIPT_FILENAME=/var/www/html/public/index.php \
    DOCUMENT_ROOT=/var/www/html/public \
    SERVER_NAME=localhost \
    HTTP_HOST=localhost \
    QUERY_STRING= \
    cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null
) || exit 1

# php-fpm only sends a Status header for non-200 responses.
if printf '%s' "$response" | grep -qiE '^Status: [45][0-9][0-9]'; then
    exit 1
fi

[ -n "$response" ]
