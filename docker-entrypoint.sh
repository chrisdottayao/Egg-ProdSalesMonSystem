#!/bin/bash
set -e

# Disable all MPM modules to clear conflicts
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Force enable only mpm_prefork for PHP
a2enmod mpm_prefork

# 1. Export container environment variables (like APP_KEY) so Apache can read them
env | grep -E '^(APP_|DB_|LOG_|SESSION_|CACHE_|MAIL_)' >> /etc/apache2/envvars || true

# 2. Clear stale cache files first
php artisan config:clear
php artisan cache:clear

# 3. Ensure permissions are correct on storage and bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Run database migrations automatically
php artisan migrate --force

# 5. Start Apache in the foreground
exec apache2-foreground
