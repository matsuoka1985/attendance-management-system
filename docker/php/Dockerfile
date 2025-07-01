FROM php:8.2-fpm-alpine

# ---------- PHP 拡張 -----------
RUN apk add --no-cache icu-dev zlib-dev libzip-dev oniguruma-dev mysql-client \
  && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && docker-php-ext-install pdo_mysql zip intl bcmath mbstring \
  && pecl install apcu \
  && docker-php-ext-enable apcu \
  && apk del .build-deps

# ---------- Composer ----------
RUN curl -sS https://getcomposer.org/installer | php -- \
  --install-dir=/usr/local/bin --filename=composer

# ---------- bash + Node 20 LTS ----------
RUN apk add --no-cache bash nodejs-lts npm
#           ↑            ↑
# Alpine の nodejs-lts は現時点で v20.x に紐付く

WORKDIR /var/www
