FROM php:8.2-fpm-alpine

# 必要パッケージ
RUN apk add --no-cache \
  icu-dev zlib-dev libzip-dev oniguruma-dev mysql-client

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
  && docker-php-ext-install pdo_mysql zip intl bcmath mbstring \
  && pecl install apcu \
  && docker-php-ext-enable apcu \
  && apk del .build-deps

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
  --install-dir=/usr/local/bin --filename=composer

RUN apk add --no-cache bash

WORKDIR /var/www
