FROM php:8.4-fpm-alpine

WORKDIR /var/www

RUN apk add --no-cache \
        icu-dev \
        libzip-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
        opcache \
    && apk del $PHPIZE_DEPS

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY docker/php/conf.d/ $PHP_INI_DIR/conf.d/

ENV COMPOSER_HOME=/tmp/composer
