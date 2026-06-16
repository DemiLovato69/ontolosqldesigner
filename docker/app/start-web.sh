#!/bin/sh
set -eu

cd /var/www/html/backend

mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

chmod -R 775 storage bootstrap/cache || true

php-fpm -D
exec nginx -g 'daemon off;'
