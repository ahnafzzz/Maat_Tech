FROM node:20-alpine AS frontend

WORKDIR /build
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci && npm run build

FROM php:8.4-cli

# Install system packages and PHP extensions required for Laravel & PostgreSQL
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libsqlite3-dev \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_pgsql pdo_mysql pdo_sqlite zip gd mbstring bcmath xml

# Copy Composer from the official Docker image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
COPY --from=frontend /build/public/build ./public/build

# Prevent Composer memory limit errors during build
ENV COMPOSER_MEMORY_LIMIT=-1

# Install production PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

RUN mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /data \
    && if [ -L public/storage ]; then unlink public/storage; fi \
    && ln -s ../storage/app/public public/storage \
    && chmod 0755 docker/start.sh \
    && chown -R www-data:www-data storage bootstrap/cache /data

VOLUME ["/app/storage", "/data"]

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 CMD curl --fail --silent --show-error "http://127.0.0.1:${PORT:-8000}/up" || exit 1

USER www-data

CMD ["docker/start.sh"]
