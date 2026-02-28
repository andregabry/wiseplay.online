FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
