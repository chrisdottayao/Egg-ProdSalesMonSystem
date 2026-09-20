#!/bin/bash
set -e

# Disable all MPM modules to clear conflicts
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Force enable only mpm_prefork for PHP
a2enmod mpm_prefork

# Bake config/route/view/event caches now, once per container start — this
# is the earliest point Railway's runtime env vars (APP_KEY, DB credentials,
# etc.) are actually available, so it can't happen at Docker build time.
# Without this, Laravel re-parses config and recompiles Blade on every
# request in production, which was the primary cause of slow login/nav.
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Ensure permissions are correct on storage and bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start Apache in the foreground
exec apache2-foreground
