FROM composer:2.8 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM node:22-alpine AS frontend_build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/css ./resources/css
COPY resources/js ./resources/js
COPY vite.config.js ./
RUN npm run build

COPY resources/ui/package.json resources/ui/package-lock.json ./resources/ui/
RUN npm --prefix resources/ui ci
COPY resources/ui ./resources/ui
RUN mkdir -p public
RUN npm --prefix resources/ui run build:laravel

FROM php:8.4-cli-alpine AS runtime
WORKDIR /var/www/html

RUN apk add --no-cache \
    bash \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    sqlite-dev \
    zip \
    && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    pcntl

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=composer_deps /app/vendor ./vendor
COPY --from=frontend_build /app/public/build ./public/build
COPY --from=frontend_build /app/public/app ./public/app

RUN chmod +x docker/start-web.sh docker/start-scheduler.sh

EXPOSE 10000

CMD ["./docker/start-web.sh"]
