#!/bin/bash
set -e

# Generate app key if not set

# Run migrations
php artisan migrate --force

# Clear and cache config for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache
exec "$@"
