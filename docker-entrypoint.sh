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

# Write API keys from env into data/keys.php (not in git; survives via volume)
if [ -n "${MYJK_SHIFT_KEY:-}" ] || [ -n "${MYJK_PERSONAL_KEY:-}" ]; then
  php -r '
    $k = [
      "shift" => (string)(getenv("MYJK_SHIFT_KEY") ?: ""),
      "personal" => (string)(getenv("MYJK_PERSONAL_KEY") ?: ""),
    ];
    $out = "<?php\nreturn " . var_export($k, true) . ";\n";
    file_put_contents("/var/www/data/keys.php", $out);
    chmod("/var/www/data/keys.php", 0640);
  '
  chown www-data:www-data /var/www/data/keys.php 2>/dev/null || true
  echo "Wrote /var/www/data/keys.php from MYJK_*_KEY env"
fi

# Let PHP getenv() see container env as a fallback
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
  if grep -q '^;*clear_env' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null; then
    sed -i 's/^;*clear_env.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf
  else
    echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf
  fi
fi

# Railway (and similar) inject PORT; default 8080 for local/Zeabur
PORT="${PORT:-8080}"
sed -i "s/listen [0-9]\\+/listen ${PORT}/" /etc/nginx/sites-enabled/default

php-fpm -D
exec nginx -g 'daemon off;'
