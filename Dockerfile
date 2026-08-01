FROM php:8.3-cli

# Install system dependencies and PHP extensions required by Laravel & PostgreSQL
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Prevent Composer memory limits during build
ENV COMPOSER_MEMORY_LIMIT=-1

RUN composer install --optimize-autoloader --no-dev --no-interaction

CMD php artisan serve --host 0.0.0.0 --port $PORT