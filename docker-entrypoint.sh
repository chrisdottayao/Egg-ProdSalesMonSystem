#!/bin/bash
set -e

# Disable all MPM modules to clear conflicts
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Force enable only mpm_prefork for PHP
a2enmod mpm_prefork

# Clear old caches first to pick up Railway env vars
php artisan config:clear
php artisan cache:clear

# Re-cache with latest variables loaded
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations automatically
php artisan migrate --force

# Start Apache in the foreground
exec apache2-foreground
