#!/bin/bash
set -e

# TEMP DIAGNOSTIC: proves whether this script actually runs at all for a
# given container start, since every fix we've tried tonight lives below
# this point and none of them have visibly taken effect. Remove this line
# once that's confirmed one way or the other.
echo "ENTRYPOINT-DID-RUN at $(date -u +%FT%TZ)"

# Disable all MPM modules to clear conflicts
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Force enable only mpm_prefork for PHP
a2enmod mpm_prefork

# IMPORTANT: composer.json's own post-install-cmd hook runs `artisan
# config:cache` (and route/view:cache) DURING `composer install` in the
# Docker build step — before Railway's runtime env vars (DB_HOST, DB_
# DATABASE, etc.) exist, since those are only injected when the container
# actually starts. That bakes bootstrap/cache/config.php into the image
# with Laravel's raw fallback defaults (127.0.0.1, database "laravel",
# user "root", empty password). Once that file exists, Laravel trusts it
# completely and stops re-reading config/*.php or calling env() for any
# command — so simply re-running `config:cache` here does NOT refresh
# anything from the live environment, it just re-serializes the same
# stale values back to the same file. `config:clear` (and the matching
# route/view/event clears) deletes the stale cache first, forcing a truly
# fresh read of the real runtime environment before we re-cache it below.
# Skipping this step was the root cause of the backup service dumping the
# wrong database at the wrong host for most of one evening's debugging,
# despite every environment variable being correctly set the whole time.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear

# Bake config/route/view/event caches now, once per container start, using
# the environment we just confirmed is fresh. Without this, Laravel
# re-parses config and recompiles Blade on every request in production,
# which was the primary cause of slow login/nav.
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