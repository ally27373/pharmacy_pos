FROM php:8.2-apache

RUN apt-get update && apt-get install -y 
libpng-dev 
libjpeg62-turbo-dev 
libfreetype6-dev 
libzip-dev 
unzip 
git 
&& docker-php-ext-configure gd 
--with-freetype 
--with-jpeg 
&& docker-php-ext-install -j"$(nproc)" 
gd 
mysqli 
pdo 
pdo_mysql 
zip 
&& rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN composer install 
--no-dev 
--optimize-autoloader 
--no-interaction 
--prefer-dist 
--no-scripts

RUN printf '#!/bin/sh\n
set -e\n
PORT="${PORT:-80}"\n
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf\n
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf\n
exec apache2-foreground\n
' > /usr/local/bin/start-apache.sh 
&& chmod +x /usr/local/bin/start-apache.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/start-apache.sh"]
