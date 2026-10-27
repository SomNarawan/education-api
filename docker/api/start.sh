#!/bin/sh
# Runs php-fpm and nginx side by side (the job supervisord did in the old
# webdevops image). If either one exits, the container exits so Docker's
# restart policy can bring it back.

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
