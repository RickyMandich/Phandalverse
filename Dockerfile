# --- Stage 1: build frontend assets con Vite ---
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
RUN npm run build

# --- Stage 2: dipendenze PHP con Composer ---
FROM composer:2 AS composer-builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: immagine finale PHP-FPM ---
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    libpng-dev libzip-dev libxml2-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring bcmath xml gd zip

WORKDIR /var/www/html

COPY --from=composer-builder /app /var/www/html
COPY --from=node-builder /app/public/build /var/www/html/public/build

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
EXPOSE 9000
CMD ["php-fpm"]
