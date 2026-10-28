FROM php:8.3-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx \
 && rm -rf /var/lib/apt/lists/* \
 && rm -rf /var/www/html

WORKDIR /var/www

COPY --chown=www-data:www-data . /var/www

# Ensure Bank gallery assets are present in the image
RUN test -f /var/www/BUILD_ID \
 && test -d /var/www/Bank \
 && ls /var/www/Bank/* >/dev/null \
 && mkdir -p /var/www/data/shift_data /var/www/data/vouchers \
 && chown -R www-data:www-data /var/www/data /var/www/Bank \
 && chmod -R ug+rwX /var/www/data

RUN cat >/etc/nginx/sites-enabled/default <<'NGINX'
server {
    listen 8080;
    root /var/www;
    index index.php index.html index.htm;
    charset utf-8;
    client_max_body_size 10m;

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ^~ /Bank/ {
        try_files $uri =404;
        types {
            image/jpeg jpg jpeg JPG JPEG;
            image/png png PNG;
        }
        default_type application/octet-stream;
        access_log off;
    }

    # block browsing /data JSON; voucher images are served via voucher_api.php
    location ^~ /data/ {
        deny all;
    }

    location ^~ /scripts/ { deny all; }
    location ^~ /seed/ { deny all; }
    location ^~ /.github/ { deny all; }
    location ^~ /.cursor/ { deny all; }

    location = /auth_lib.php { deny all; }
    location = /Dockerfile { deny all; }
    location = /docker-entrypoint.sh { deny all; }
    location = /composer.json { deny all; }
    location = /railway.toml { deny all; }
    location = /zbpack.json { deny all; }
    location = /BACKUP.md { deny all; }
    location = /README.md { deny all; }
    location = /PASSWORDS.md { deny all; }
    location ~* \.(md|toml|yml|yaml)$ { deny all; }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_hide_header X-Powered-By;
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    access_log /dev/stdout;
    error_log  /dev/stderr;
}
NGINX

ENV PORT=8080
EXPOSE 8080

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

CMD ["/docker-entrypoint.sh"]
