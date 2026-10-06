# syntax=docker/dockerfile:1

# ==========================================
# Stage 1: Build Frontend Assets
# ==========================================
FROM node:22-alpine AS frontend
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ ./resources/
COPY public/ ./public/
COPY vite.config.js ./

RUN npm run build

# ==========================================
# Stage 2: PHP Application Container
# ==========================================
FROM php:8.3-fpm-alpine AS app

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    su-exec \
    unzip \
    curl

# Install PHP extensions required by NexusCRM
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        bcmath \
        zip \
        gd \
        opcache \
        pcntl

# Install Composer
COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files (excluding those in .dockerignore)
COPY . .

# Copy compiled assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Install production PHP dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy configuration and entrypoint
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
