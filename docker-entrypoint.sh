#!/bin/sh
set -e
mkdir -p /var/www/data/shift_data
chown -R www-data:www-data /var/www/data 2>/dev/null || true
chmod -R ug+rwX /var/www/data 2>/dev/null || true
php-fpm -D
exec nginx -g 'daemon off;'
