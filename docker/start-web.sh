#!/usr/bin/env sh
set -eu

if [ -n "${RENDER_EXTERNAL_URL:-}" ] && [ -z "${APP_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your platform environment variables."
    exit 1
fi

php artisan package:discover --ansi --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache

if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    php artisan schedule:work --no-interaction &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
