FROM composer:2 AS composer

FROM php:8.5.12-fpm-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql \
    && php -r 'if (!in_array("argon2id", password_algos(), true)) exit(1);'
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
COPY backend/src ./src
COPY backend/public ./public
COPY backend/database ./database
COPY backend/bin ./bin
COPY docker/php.ini /usr/local/etc/php/conf.d/nord.ini
RUN chown -R root:root /var/www/html && chmod -R go-w /var/www/html
USER www-data
EXPOSE 9000
