FROM php:8.3-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx \
 && rm -rf /var/lib/apt/lists/* \
 && rm -rf /var/www/html

WORKDIR /var/www

COPY --chown=www-data:www-data . /var/www

# Ensure Bank gallery assets are present in the image
RUN test -f /var/www/Bank/004.JPG \
 && test -f /var/www/BUILD_ID \
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
