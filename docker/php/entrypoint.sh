#!/bin/bash
set -e

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:defaultkeyfordevonlypleasechange=" ]; then
    php artisan key:generate --force
fi

# Wait for DB and run migrations + seed
php artisan migrate --force --seed

# Cache config for performance
php artisan config:cache
php artisan route:cache

exec php-fpm
