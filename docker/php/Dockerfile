FROM php:8.3-fpm-alpine

RUN docker-php-ext-install pdo_mysql

# git: the repository hygiene test inspects "git ls-files"
RUN apk add --no-cache git

# do not advertise the PHP version in the X-Powered-By header; all times are UTC
RUN printf 'expose_php = Off\ndate.timezone = UTC\n' > "$PHP_INI_DIR/conf.d/app.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend
