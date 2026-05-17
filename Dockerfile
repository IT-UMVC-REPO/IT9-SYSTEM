FROM dunglas/frankenphp:php8.5-bookworm AS php-base

WORKDIR /app

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    redis \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=php-base /app/vendor ./vendor
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php-base AS app

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN APP_URL=http://localhost BROADCAST_CONNECTION=log FILESYSTEM_PUBLIC_URL=http://localhost/public-storage composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
