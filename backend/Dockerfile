FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev \
    supervisor

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for laravel application
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S www -G www

# Copy application files
COPY . /var/www

# Set permissions
RUN chown -R www:www /var/www
RUN chmod -R 755 /var/www/storage
RUN chmod -R 755 /var/www/bootstrap/cache

# Switch to non-root user
USER www

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Switch back to root for supervisor
USER root

# Copy supervisor configuration
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose port 8000 for Laravel and 8080 for Reverb
EXPOSE 8000 8080

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]