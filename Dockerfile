FROM baronmsk/base-web

COPY nginx/default.conf /etc/nginx/conf.d/default.conf

COPY . /var/www
