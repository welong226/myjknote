#!/bin/sh
set -e

mkdir -p /var/www/data/shift_data /var/www/data/vouchers
chown -R www-data:www-data /var/www/data 2>/dev/null || true
chmod -R ug+rwX /var/www/data 2>/dev/null || true

# Volume mount often starts empty — seed once from image defaults
if [ ! -f /var/www/data/.seeded ]; then
  echo "Seeding /var/www/data from /var/www/seed ..."
  if [ -d /var/www/seed ]; then
    cp -a /var/www/seed/. /var/www/data/
  fi
  touch /var/www/data/.seeded
  chown -R www-data:www-data /var/www/data 2>/dev/null || true
  chmod -R ug+rwX /var/www/data 2>/dev/null || true
fi

# Railway (and similar) inject PORT; default 8080 for local/Zeabur
PORT="${PORT:-8080}"
sed -i "s/listen [0-9]\\+/listen ${PORT}/" /etc/nginx/sites-enabled/default

php-fpm -D
exec nginx -g 'daemon off;'
