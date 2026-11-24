#!/bin/sh
set -e

# first start: make an .env and an app key
[ -f .env ] || cp .env.example .env
grep -q '^APP_KEY=.\+' .env || php artisan key:generate --force

# the database container is healthy by now (see docker-compose.yml)
php artisan migrate --force
php artisan db:seed --force

exec php artisan serve --host=0.0.0.0 --port=8000
