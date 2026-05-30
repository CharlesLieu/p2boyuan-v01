FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    zip \
    unzip \
  && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pdo_mysql \
    zip

COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/p2boyuan-entrypoint

RUN chmod +x /usr/local/bin/p2boyuan-entrypoint \
  && chown -R www-data:www-data storage bootstrap/cache public

USER www-data

ENTRYPOINT ["p2boyuan-entrypoint"]
CMD ["php-fpm"]
