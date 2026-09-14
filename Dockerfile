FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mysqli pdo pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Remove ALL existing MPM LoadModule directives
RUN find /etc/apache2 -type f \
    \( -name "*.conf" -o -name "*.load" \) \
    -exec sed -i '/^[[:space:]]*LoadModule[[:space:]]\+mpm_/d' {} \;

# Enable ONLY prefork
RUN a2enmod mpm_prefork rewrite

# Verify that only prefork is loaded
RUN echo "=== MPM CONFIGURATION ===" \
    && apache2ctl -M 2>&1 | grep mpm \
    && echo "=== APACHE CONFIG TEST ===" \
    && apache2ctl configtest

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/

RUN composer install \
    --optimize-autoloader \
    --no-scripts \
    --no-interaction

# Listen on 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 8080

CMD ["apache2-foreground"]
