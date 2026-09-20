FROM php:8.4-apache

# 1. Install system dependencies (PHP libs + Node.js/npm)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    bcmath \
    gd \
    zip \
    dom \
    fileinfo \
    pdo \
    pdo_mysql \
    mbstring \
    opcache

# 3. Enable Apache modules & MPM pre-fork
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite env

# 4. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Set working directory
WORKDIR /var/www/html

# 5a. Apply custom PHP settings (upload size, execution time, memory — see
# php.ini's own comments for why each value was chosen) plus basic opcache
# production tuning. Copied into conf.d at build time since php.ini at the
# repo root stopped being picked up once deployment moved from Nixpacks to
# this Dockerfile — conf.d/*.ini files are auto-loaded by the official php
# images, and the 99- prefix ensures it loads last and wins on conflicts.
COPY php.ini /usr/local/etc/php/conf.d/99-egg-monitor.ini
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/99-egg-monitor.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/99-egg-monitor.ini \
    && echo "opcache.memory_consumption=192" >> /usr/local/etc/php/conf.d/99-egg-monitor.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/99-egg-monitor.ini

# 6. Set Composer environment variable
ENV COMPOSER_ALLOW_SUPERUSER=1

# 7. COPY ALL PROJECT FILES FIRST (This was missing before npm build!)
COPY . .

# 8. Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 9. Install Node dependencies & build Vite assets
RUN npm ci && npm run build

# 10. Set correct permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# 11. Configure Apache document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 12. Silence Apache domain warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 13. Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["docker-entrypoint.sh"]
