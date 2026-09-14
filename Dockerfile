FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev unzip git 
&& docker-php-ext-configure gd --with-freetype --with-jpeg 
&& docker-php-ext-install gd mysqli pdo pdo_mysql zip 
&& rm -rf /var/lib/apt/lists/*
RUN apt-get update && apt-get install -y libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev unzip git && docker-php-ext-configure gd --with-freetype --with-jpeg && docker-php-ext-install gd mysqli pdo pdo_mysql zip && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

@@ -15,13 +12,6 @@ COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

RUN echo '#!/bin/sh' > /usr/local/bin/start.sh 
&& echo 'PORT=${PORT:-80}' >> /usr/local/bin/start.sh 
&& echo 'sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf' >> /usr/local/bin/start.sh 
&& echo 'sed -i "s/<VirtualHost \*:.*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf' >> /usr/local/bin/start.sh 
&& echo 'exec apache2-foreground' >> /usr/local/bin/start.sh 
&& chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
CMD ["apache2-foreground"]
