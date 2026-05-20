#!/bin/sh
set -e
cd /var/www/backend
if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi
php artisan migrate --force
exec "$@"
