FROM php:7.1-apache
RUN pecl install redis
RUN docker-php-ext-enable redis
COPY web/ /var/www/html/
