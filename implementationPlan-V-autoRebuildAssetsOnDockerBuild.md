# Implementation plan: rebuild automatico degli asset frontend ad ogni `docker build`

## Problema
In dev, `app/volumes` monta `./public/build` (o il volume nominato `build_assets_dev`)
dentro il container, in modo che `nginx` serva gli asset dall'host. Ma qualunque bind
mount o volume nominato **nasconde** il contenuto che l'immagine Docker ha copiato in
quel path durante la build (`COPY --from=node-builder /app/public/build ...` nel
Dockerfile). Risultato: anche ricostruendo l'immagine con `--build`, gli asset compilati
dentro l'immagine non arrivano mai sull'host — bisogna lanciare `npm run build` a mano
sull'host per aggiornare `./public/build`.

## Soluzione
Copiare gli asset compilati, ad ogni avvio del container `app`, da un path "immutabile"
dentro l'immagine (non coperto da nessun mount) verso `/var/www/html/public/build`
(che in dev è bind-mountato su `./public/build`). Così ogni volta che si fa
`docker compose -f docker-compose.dev.yml up -d --build`, il container `app` si
riavvia, l'entrypoint copia gli asset freschi (già ricompilati durante il `docker build`)
sull'host, e `nginx`/browser vedono subito la versione aggiornata. Non serve più
lanciare `npm run build` manualmente.

## Step 1 — Dockerfile: tenere una copia "sorgente" degli asset in un path libero
File: `Dockerfile`, stage finale (quello `FROM php:8.2-fpm-alpine` senza nome, l'ultimo).

Cambiare:
```dockerfile
COPY --from=node-builder /app/public/build /var/www/html/public/build
```
in:
```dockerfile
# Copia "sorgente" immutabile, usata dall'entrypoint per rinfrescare
# public/build ad ogni avvio (necessario perché in dev quel path è
# coperto da un bind mount che nasconde il contenuto dell'immagine)
COPY --from=node-builder /app/public/build /opt/build-assets
COPY --from=node-builder /app/public/build /var/www/html/public/build
```
(la seconda riga resta invariata: serve per l'uso in produzione, dove
`public/build` NON è mountato da nessun volume/bind mount e quindi il contenuto
dell'immagine è quello effettivamente servito).

## Step 2 — entrypoint.sh: rinfrescare public/build all'avvio
File: `docker/entrypoint.sh`

Contenuto attuale:
```sh
#!/bin/sh
set -e

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
```

Nuovo contenuto:
```sh
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
```

Nota: questo blocco va eseguito solo dal container `app` (che ha l'entrypoint),
non serve nel `worker` — ma non è dannoso nemmeno lì, dato che `worker` non monta
`public/build`.

## Step 3 — docker-compose.dev.yml: passare dal volume nominato al bind mount
File: `docker-compose.dev.yml`

Stato attuale (nessuna modifica ancora applicata a questo file): sia `app` sia `nginx`
montano ancora il volume nominato `build_assets_dev` su `/var/www/html/public/build`,
e quel volume è dichiarato in fondo al file. Va sostituito ovunque con un bind mount
su `./public/build`, altrimenti il volume nominato continuerebbe a nascondere sia
gli asset appena copiati dall'entrypoint (Step 2) sia quelli generati da `npm run
build` sull'host.

Nel servizio `app`, sostituire:
```yaml
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env:/var/www/html/.env:ro
      - build_assets_dev:/var/www/html/public/build
```
con:
```yaml
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env:/var/www/html/.env:ro
      - ./public/build:/var/www/html/public/build
```

Nel servizio `nginx`, sostituire:
```yaml
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
      - build_assets_dev:/var/www/html/public/build:ro
      - ./storage/app/public:/var/www/html/storage/app/public:ro
```
con (basta togliere la riga del volume nominato: `./public` copre già tutta la
cartella `public`, incluso `build`):
```yaml
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      - ./public:/var/www/html/public:ro
      - ./storage/app/public:/var/www/html/storage/app/public:ro
```

Infine, nella sezione `volumes:` in fondo al file, togliere la riga `build_assets_dev:`
(non più referenziato da nessun servizio), lasciando solo:
```yaml
volumes:
  db_data_dev:
```

## Step 4 — verifica
```bash
docker compose -f docker-compose.dev.yml down
docker volume rm unlimiteddb_build_assets_dev   # residuo del vecchio volume nominato, se esiste ancora
docker compose -f docker-compose.dev.yml up -d --build
```
Poi controllare che `public/build/assets/` sull'host contenga i file appena generati
durante la build (hash nel nome coerente con l'ultima build dell'immagine, visibile nei
log di `docker compose ... --build` nello step `npm run build`), e che la pagina nel
browser li referenzi (view-source o devtools, tag `<link>`/`<script>` con lo stesso hash).
