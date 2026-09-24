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

# Run whatever command this container was started with (the Dockerfile's CMD
# default, or a Railway service's Custom Start Command override — e.g. the
# cron service's "php artisan schedule:run"). Previously hardcoded to
# apache2-foreground, so a Start Command override was silently ignored and
# every service — including the cron job — always just booted the web server.
exec "$@"
