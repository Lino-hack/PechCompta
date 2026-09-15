#!/bin/sh
set -e

if [ -d storage ] && [ -d bootstrap/cache ]; then
    chown -R www-data:www-data storage bootstrap/cache || true
fi

php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

php artisan migrate --force
php artisan db:seed --force

exec "$@"