FROM php:8.2.28-fpm-alpine

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
RUN apk add --no-cache bash make nodejs-lts npm

# ---------- Dusk 用の Chrome + 依存パッケージ ----------
RUN apk add --no-cache \
  chromium \
  chromium-chromedriver \
  nss \
  freetype \
  harfbuzz \
  ttf-freefont \
  && rm -rf /var/cache/*

ENV CHROME_BIN=/usr/bin/chromium-browser \
  DUSK_DRIVER_URL=http://selenium:4444/wd/hub

WORKDIR /var/www
