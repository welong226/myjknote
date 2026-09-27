FROM php:8.3-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx \
 && rm -rf /var/lib/apt/lists/* \
 && rm -rf /var/www/html

WORKDIR /var/www

COPY --chown=www-data:www-data . /var/www

RUN mkdir -p /var/www/data/shift_data \
 && chown -R www-data:www-data /var/www/data \
 && chmod -R ug+rwX /var/www/data

# nginx: static + php-fpm on 8080 (Zeabur default)
RUN cat >/etc/nginx/sites-enabled/default <<'NGINX'
server {
    listen 8080;
    root /var/www;
    index index.php index.html index.htm;
    charset utf-8;

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Images / static under Bank — never rewrite to index.php
    location ^~ /Bank/ {
        try_files $uri =404;
        access_log off;
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

# disable default site if present
RUN rm -f /etc/nginx/sites-enabled/default.bak 2>/dev/null; true

ENV PORT=8080
EXPOSE 8080

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

CMD ["/docker-entrypoint.sh"]
