# syntax=docker/dockerfile:1
# PHP 8.4-FPM + extensions MySQL (pdo_mysql) — code monté en volume sur le VPS

FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl openssl default-mysql-client \
        libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libonig-dev libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl pdo_mysql mysqli zip gd opcache mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm", "-F"]
