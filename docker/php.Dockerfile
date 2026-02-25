FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Install dependencies at build time
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts
