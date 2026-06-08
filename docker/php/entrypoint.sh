#!/bin/sh
mkdir -p /var/www/html/backend/storage/framework/sessions \
    /var/www/html/backend/storage/framework/views \
    /var/www/html/backend/storage/framework/cache \
    /var/www/html/backend/storage/logs \
    /var/www/html/backend/bootstrap/cache

find /var/www/html/backend/storage /var/www/html/backend/bootstrap/cache -type d -exec chmod 777 {} \;

if [ "$#" -gt 0 ]; then
    exec "$@"
else
    exec php-fpm
fi
