# Stage 1: Build Assets
FROM node:22-alpine AS asset-builder
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

# Stage 2: Application Runtime
FROM php:8.3-fpm-alpine
WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-update --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    sqlite-dev \
    sqlite \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pdo_sqlite gd zip opcache

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .
COPY --from=asset-builder /app/public/build ./public/build

# Install production dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Configure Nginx, PHP-FPM, and Supervisor
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Make entrypoint executable
RUN chmod +x /var/www/html/docker/entrypoint.sh

# Set initial permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
