#!/bin/bash
set -e

# Bind-mounted volume means this only happens once - .env persists on the host afterward.
if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:defaultkeyfordevonlypleasechange=" ]; then
    php artisan key:generate --force --no-interaction || true
fi

php artisan migrate --force --seed

exec php-fpm
