FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install gd mysqli pdo pdo_mysql zip && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

EXPOSE 80

CMD ["apache2-foreground"]
