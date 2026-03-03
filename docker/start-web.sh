#!/usr/bin/env sh
set -eu

if [ -n "${RENDER_EXTERNAL_URL:-}" ] && [ -z "${APP_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set it in your platform environment variables."
    exit 1
fi

case "${APP_KEY}" in
    base64:*)
        ;;
    *)
        if [ "${#APP_KEY}" -ne 32 ]; then
            echo "APP_KEY must be a Laravel key (base64:...) or a 32-character raw string."
            exit 1
        fi
        ;;
esac

php artisan package:discover --ansi --no-interaction

attempt=1
until php artisan migrate --force --no-interaction
do
    if [ "${attempt}" -ge 20 ]; then
        echo "Database is not reachable after multiple attempts."
        exit 1
    fi

    attempt=$((attempt + 1))
    sleep 3
done

php artisan config:cache

if [ "${RUN_SCHEDULER:-true}" = "true" ]; then
    php artisan schedule:work --no-interaction &
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
