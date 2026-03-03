#!/usr/bin/env sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your platform environment variables."
    exit 1
fi

php artisan package:discover --ansi --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache

exec php artisan schedule:work --no-interaction
