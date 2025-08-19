# ----------------------------------------------------------------------
# Composer install
# ----------------------------------------------------------------------
FROM composer:latest AS composer
COPY ./ /app/
RUN composer --no-ansi install --no-dev --ignore-platform-reqs
RUN composer --no-ansi dump-autoload --optimize

# ----------------------------------------------------------------------
# Generate .mo files
# ----------------------------------------------------------------------
FROM alpine AS translation
RUN apk add gettext
COPY resources/lang/ /data
RUN find /data -type f -name '*.po' -exec sh -c 'msgfmt "${1%.*}.po" -o"${1%.*}.mo"' shell {} \;

# ----------------------------------------------------------------------
# Build the themes + Special assets directory
# ----------------------------------------------------------------------
FROM node:20-alpine AS themes
WORKDIR /app
COPY .babelrc .browserslistrc package.json webpack.config.js yarn.lock /app/
RUN yarn --frozen-lockfile
COPY resources/assets/ /app/resources/assets
RUN npx update-browserslist-db@latest
RUN yarn build

# Create special assets directory and copy all required files
RUN mkdir -p /app/public/assets/special/bootstrap/css \
             /app/public/assets/special/bootstrap/js \
             /app/public/assets/special/bootstrap-icons && \
    cp -r node_modules/@xterm/xterm /app/public/assets/special/ && \
    cp -r node_modules/bootstrap/dist/css /app/public/assets/special/bootstrap/ && \
    cp -r node_modules/bootstrap/dist/js /app/public/assets/special/bootstrap/ && \
    cp -r node_modules/bootstrap-icons/font /app/public/assets/special/bootstrap-icons/ && \
    cp -r node_modules/bootstrap-icons/icons /app/public/assets/special/bootstrap-icons/ && \
    cp /app/resources/assets/js/install.js /app/public/assets/special/install.js && \
    cp /app/resources/assets/css/install.css /app/public/assets/special/install.css

# ----------------------------------------------------------------------
# Generate application structure
# ----------------------------------------------------------------------
FROM alpine AS data
COPY .babelrc .browserslistrc composer.json LICENSE package.json README.md webpack.config.js yarn.lock /app/
COPY bin/ /app/bin
COPY config/ /app/config
COPY db/ /app/db
COPY includes/ /app/includes
COPY public/ /app/public
COPY resources/api /app/resources/api
COPY resources/views /app/resources/views
COPY src/ /app/src
COPY storage/ /app/storage

COPY --from=translation /data/ /app/resources/lang
COPY --from=composer /app/vendor/ /app/vendor
COPY --from=composer /app/composer.lock /app/
COPY --from=themes /app/public/assets /app/public/assets/

RUN find /app/storage/ -type f -not -name VERSION -exec rm {} \;


# ----------------------------------------------------------------------
# Build the PHP/Nginx container
# ----------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS production
WORKDIR /var/www
RUN apk add --no-cache icu-dev nginx libzip-dev libpng-dev && \
    docker-php-ext-install intl pdo_mysql gd zip && \
    sed -i 's/9000/127.0.0.1:9000/' /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy files to the right places
COPY docker/entrypoint.sh /
COPY docker/config/nginx.conf /etc/nginx/nginx.conf
COPY docker/config/opcache.ini /usr/local/etc/php/php.d/opcache.ini
COPY docker/config/www.conf /usr/local/etc/php-fpm.d/www.conf

ENTRYPOINT /entrypoint.sh

EXPOSE 80

COPY --from=data /app/ /var/www

RUN chown -R www-data:www-data /var/www/storage/ && \
    rm -r /var/www/html

ARG VERSION

RUN if [[ ! -f /var/www/storage/app/VERSION ]] && [[ ! -z "${VERSION}" ]]; then \
        echo -n "${VERSION}" > /var/www/storage/app/VERSION; fi

ENV TRUSTED_PROXIES 10.0.0.0/8,::ffff:10.0.0.0/8,\
                    127.0.0.0/8,::ffff:127.0.0.0/8,\
                    172.16.0.0/12,::ffff:172.16.0.0/12,\
                    192.168.0.0/16,::ffff:192.168.0.0/16,\
                    ::1/128,fc00::/7,fec0::/10
