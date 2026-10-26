#!/bin/bash
# Eseguito dalla VM dopo "docker compose up", nella cartella del sito.
set -euo pipefail

docker compose exec -T app php artisan migrate --force
# fa ricaricare il codice ai processi queue:work del worker (supervisord li rilancia)
docker compose exec -T app php artisan queue:restart
# docker/nginx/default.conf è montato dal checkout: il git pull lo cambia ma nginx non lo rilegge da solo
docker compose exec -T nginx nginx -s reload
