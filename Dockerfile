FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY ./var/www/html/

RUN chown -R www-data:wwww-data /var/wwww/html

EXPOSE 80
