#!/bin/sh
set -e

# Rinfresca public/build con gli asset compilati nell'immagine (vedi Dockerfile),
# così un bind mount/volume su quel path in dev non serve mai asset "vecchi".
# Innocuo in produzione: se non c'è mount, sovrascrive con lo stesso contenuto.
if [ -d /opt/build-assets ]; then
    rm -rf /var/www/html/public/build/*
    cp -a /opt/build-assets/. /var/www/html/public/build/
fi

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"