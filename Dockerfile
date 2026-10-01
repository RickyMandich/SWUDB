# --- Stage 1: build frontend assets con Vite ---
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
RUN npm run build

# --- Stage 2: dipendenze PHP con Composer ---
# Si usa la stessa immagine php:8.2-fpm-alpine dello stage finale (non
# l'immagine standalone "composer:2", che porta con sé un PHP proprio e può
# cambiarne la versione senza preavviso, causando incompatibilità col
# composer.lock del progetto). Composer viene copiato come binario.
FROM php:8.2-fpm-alpine AS composer-builder
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: icone del sito (favicon, apple-touch, manifest) ---
# Genera tutte le varianti a partire da public/icon-mine.svg (script in
# docker/icons/generate-icons.sh). Copia solo SVG e script, quindi lo stage
# viene ricostruito (cache invalidata) solo quando uno dei due cambia.
FROM debian:bookworm-slim AS icon-builder
RUN apt-get update \
    && apt-get install -y --no-install-recommends librsvg2-bin imagemagick \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /work
COPY docker/icons/generate-icons.sh /usr/local/bin/generate-icons.sh
COPY public/icon-mine.svg /work/icon-mine.svg
RUN sed -i 's/\r$//' /usr/local/bin/generate-icons.sh \
    && sh /usr/local/bin/generate-icons.sh /work/icon-mine.svg /out/icons

# --- Stage 4: immagine finale PHP-FPM ---
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    libpng-dev libzip-dev libxml2-dev oniguruma-dev supervisor \
    && docker-php-ext-install pdo_mysql mbstring bcmath xml gd zip

WORKDIR /var/www/html

COPY --from=composer-builder /app /var/www/html
# Copia "sorgente" immutabile, usata dall'entrypoint per rinfrescare
# public/build ad ogni avvio (necessario perché in dev quel path è
# coperto da un bind mount che nasconde il contenuto dell'immagine)
COPY --from=node-builder /app/public/build /opt/build-assets
COPY --from=node-builder /app/public/build /var/www/html/public/build
# Icone generate dallo stage icon-builder: stesso percorso di consegna degli asset
# Vite (public/build/icons), quindi l'entrypoint le rinfresca insieme a loro
COPY --from=icon-builder /out/icons /opt/build-assets/icons
COPY --from=icon-builder /out/icons /var/www/html/public/build/icons

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
EXPOSE 9000
CMD ["php-fpm"]
