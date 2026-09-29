# Implementation plan 11 — Ricostruzione UnlimitedDB · Fase 12: deploy

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). **Ultimo piano da eseguire**: si affronta quando il resto è pronto.
> Il deploy è automatizzato: un merge sul branch `laravel` fa partire la pipeline (build immagine, deploy sulla VM tramite `new-site.sh` e Traefik). Nessuno step manuale per il deploy in sé.

## Fase 12 — Deploy

### Step 12.1 — Verifica post-deploy
Dopo il merge, controllare che tutto sia partito:

#### 12.1.1 — Webhook Telegram
Registrato sul dominio giusto (`https://unlimiteddb.mandich.dev/telegram/webhook`, Step 9.3.5 di [`implementationPlan-09-ricostruzioneBotTelegramWebhook.md`](implementationPlan-09-ricostruzioneBotTelegramWebhook.md)).

#### 12.1.2 — Coda e worker
`failed_jobs` vuota (`php artisan queue:failed` sul container, o query diretta) e container `_worker` `Up` (`docker ps`, non solo `_app`).

#### 12.1.3 — Scan schedulato
`php artisan schedule:list` mostra `cards:scan` al lunedì. Attenzione: in produzione serve anche un processo che esegua `schedule:run` ogni minuto (cron o servizio dedicato); il compose di sviluppo non ne ha uno.

#### 12.1.4 — Immagini carta
Dopo il primo scan, `https://unlimiteddb.mandich.dev/storage/cards/{EXP}/{numero}-front.{ext}` deve rispondere 200. Un 404 indica che manca il mount `./storage/app/public` sul servizio `nginx` di `docker-compose.yml`
o l'alias `/storage/` in `docker/nginx/default.conf`.

#### 12.1.5 — Icone del sito
`https://unlimiteddb.mandich.dev/favicon.ico` e `/build/icons/apple-touch-icon.png` rispondono 200 (generate dallo stage `icon-builder` del `Dockerfile`, vedi Step 2.3 di
[`implementationPlan-V-00-ricostruzioneFondamenta.md`](implementationPlan-V-00-ricostruzioneFondamenta.md)). Un 404 indica che nginx non serve `public/build`, cioè lo stesso problema che avrebbero gli asset Vite.

### Step 12.2 — Redeploy pulito (solo a fine sviluppo, non ora)
Serve a far generare a `~/scripts/new-site.sh` un `docker-compose.yml` pulito (con il servizio `worker`) invece di continuare a correggere a mano quello attuale, che ha accumulato incoerenze (subnet, nomi DB).
**Prerequisito già fatto**: `new-site.sh` sul server genera anche il servizio `worker` (GRANT SQL a wildcard di subnet + blocco `worker` nel template).

#### 12.2.0 — Personalizzazioni della repo che il redeploy potrebbe sovrascrivere
Il passo 12.2.4 dice che `new-site.sh` rigenera sempre `Dockerfile`, `docker/entrypoint.sh`, `docker/nginx/default.conf`, `docker/mysql/init.sql` e `docker-compose.yml`. Questi file oggi contengono personalizzazioni che il template
dello script non ha (verificarlo prima di lanciare il redeploy, altrimenti vanno perse):
- `Dockerfile`: stage `node-builder` (Vite), stage `icon-builder` (icone da `public/icon-mine.svg`, script `docker/icons/generate-icons.sh`), copia degli asset in `/opt/build-assets`;
- `docker/entrypoint.sh`: refresh di `public/build` da `/opt/build-assets` a ogni avvio;
- `docker/nginx/default.conf`: `location /storage/` con alias su `storage/app/public` e `location = /favicon.ico` verso `build/icons`;
- `docker-compose.yml`: mount `./storage/app/public` sul servizio `nginx`, servizio `worker`.

Soluzioni possibili: aggiornare i template di `new-site.sh` con queste parti, oppure far sì che lo script non sovrascriva i file già presenti nella repo clonata. Da decidere prima del redeploy.

#### 12.2.1 — Fermare i container attuali
`cd ~/sites/SWUDB && docker compose down` (senza distruggere i volumi). Per conservare i dati del DB di produzione fare **prima** un dump con `db` ancora attivo:
`docker compose exec db mariadb-dump -u root <db> > backup.sql`. Se cancellare anche il volume `db_data` (`down -v`) si decide a parte.

#### 12.2.2 — Impostare il branch nuovo come default della repo
GitHub → Settings → Branches. `new-site.sh` rileva il branch da clonare con `git remote show origin | grep "HEAD branch"` e lo scrive nel nuovo `deploy.yml`: va cambiato **prima** di riclonare.

#### 12.2.3 — Disattivare la GitHub Action del vecchio sito
Nel branch vecchio commentare il trigger `on: push` di `.github/workflows/deploy.yml`, committare e pushare. I secrets SSH sono a livello di repo, non di branch: un push accidentale sul vecchio branch rilancerebbe `deploy.sh SWUDB`
contro una cartella nel frattempo ricreata.

#### 12.2.4 — Verificare il `.env` del branch nuovo
`new-site.sh` riusa il `.env` se lo trova nella repo clonata: controllare che `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` siano corretti, altrimenti si ripresenta l'incoerenza `my_swudb`/`unlimiteddb` vista in Fase 4bis.

#### 12.2.5 — Cancellare la cartella del sito vecchio
`rm -rf ~/sites/SWUDB`, solo dopo il passo 12.2.1 (altrimenti restano container e rete Docker orfani con nomi in conflitto).

#### 12.2.6 — Rilanciare `new-site.sh`
Con lo stesso URL della repo: clona il branch default, rigenera l'infrastruttura (worker incluso), legge il `.env`, fa build/up/migrate e riusa la chiave SSH esistente `~/.ssh/deploy_SWUDB` (i secrets su GitHub restano validi).

#### 12.2.7 — Ripetere la verifica
Ripetere lo Step 12.1 completo dopo il redeploy.
