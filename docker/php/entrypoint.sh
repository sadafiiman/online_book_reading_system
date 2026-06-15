#!/bin/bash
set -e

cd /var/www

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:defaultkeyfordevonlypleasechange=" ]; then
    php artisan key:generate --force --no-interaction
fi

mkdir -p storage/app/books storage/logs storage/framework/{cache,sessions,views,testing}

php artisan migrate --force --seed

exec php-fpm
