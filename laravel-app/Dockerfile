FROM php:8.4-cli

# Install system packages and PHP extensions required for Laravel & PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql zip gd mbstring bcmath xml

# Copy Composer from the official Docker image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Prevent Composer memory limit errors during build
ENV COMPOSER_MEMORY_LIMIT=-1

# Install production PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

CMD php artisan serve --host 0.0.0.0 --port $PORT