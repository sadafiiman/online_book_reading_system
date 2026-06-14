#!/bin/bash
set -e

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:defaultkeyfordevonlypleasechange=" ]; then
    php artisan key:generate --force --no-interaction || true
fi

php artisan migrate --force --seed

exec php-fpm
