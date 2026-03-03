#!/usr/bin/env sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your platform environment variables."
    exit 1
fi

APP_KEY="$(printf '%s' "${APP_KEY}" | tr -d '\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"
export APP_KEY

php artisan package:discover --ansi --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache

exec php artisan schedule:work --no-interaction
