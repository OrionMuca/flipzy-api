# Multi-stage Dockerfile for Laravel
# Stage 1: Build dependencies
FROM php:8.3-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    mariadb-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies (no dev for production)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --optimize-autoloader

# Copy application files
COPY . .

# Complete composer setup
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Stage 2: Production image
FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    libpng \
    libzip \
    oniguruma \
    mariadb-client \
    nginx \
    supervisor

# Install PHP extensions (production only)
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    mariadb-dev \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Copy built application from builder
COPY --from=builder /var/www/html /var/www/html

# Set working directory
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Create supervisor log directory
RUN mkdir -p /var/log/supervisor

# Copy supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/supervisor/php-fpm.conf /etc/supervisor/conf.d/php-fpm.conf
COPY docker/supervisor/nginx.conf /etc/supervisor/conf.d/nginx.conf
COPY docker/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf
# Horizon disabled - uncomment if you want to use Horizon dashboard instead of regular workers
# COPY docker/supervisor/laravel-horizon.conf /etc/supervisor/conf.d/laravel-horizon.conf

# Copy startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port
EXPOSE 80

# Start supervisor (which manages PHP-FPM, Nginx, and workers)
CMD ["/usr/local/bin/start.sh"]

