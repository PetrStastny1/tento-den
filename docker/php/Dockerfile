FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql

# do not advertise the PHP version in the X-Powered-By header
RUN echo 'expose_php = Off' > "$PHP_INI_DIR/conf.d/security.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend
