# --- Stage 1: dipendenze PHP con Composer (solo vendor, senza codice) ---
# Si usa la stessa immagine php:8.2-fpm-alpine dello stage finale (non
# l'immagine standalone "composer:2", che porta con sé un PHP proprio e può
# cambiarne la versione senza preavviso, causando incompatibilità col
# composer.lock del progetto). Composer viene copiato come binario.
# Stage separato da composer-builder perché dipende solo da composer.json/lock:
# la sua cache non viene invalidata dalle modifiche al codice, e così anche lo
# stage node-builder (che ne copia le viste di paginazione) resta in cache.
FROM php:8.2-fpm-alpine AS composer-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# --- Stage 2: build frontend assets con Vite + Tailwind CSS 4 ---
# Tailwind 4 non ha più tailwind.config.js: la configurazione sta in
# resources/css/app.css (@import, @plugin, @theme, @source) e il plugin PostCSS
# è in postcss.config.js. I file sorgente vengono individuati in automatico
# partendo da /app (qui: resources/ e public/), più i path dichiarati con
# @source in app.css. Quelli che puntano a vendor/ esistono solo se li copiamo
# qui sotto: le classi delle viste di paginazione di Laravel (usate da
# `->links()`) altrimenti non finirebbero nel CSS.
FROM node:20-alpine AS node-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY vite.config.js postcss.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/
COPY --from=composer-deps /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views/ ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/
RUN npm run build

# --- Stage 3: autoloader ottimizzato con il codice dell'app ---
FROM composer-deps AS composer-builder
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 4: icone del sito (favicon, apple-touch, manifest) ---
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

# --- Stage 5: immagine finale PHP-FPM ---
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
