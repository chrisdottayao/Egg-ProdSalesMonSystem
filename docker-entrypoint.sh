#!/bin/bash
set -e

# Disable all MPM modules to clear conflicts
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Force enable only mpm_prefork for PHP
a2enmod mpm_prefork

# 1. Export all container environment variables so Apache can read them
env >> /etc/apache2/envvars || true

# 2. Only clear local configuration and view files (safe without DB)
php artisan config:clear
php artisan view:clear

# 3. Ensure permissions are correct on storage and bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 4. Start Apache in the foreground
exec apache2-foreground
