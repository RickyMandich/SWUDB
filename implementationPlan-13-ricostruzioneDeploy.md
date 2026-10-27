# Implementation plan 13 — Ricostruzione UnlimitedDB · Fase 13: messa in produzione (scambio degli ambienti)

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). **Ultimo piano da eseguire.**
> **Prerequisiti**: [`implementationPlan-V-10-ricostruzioneApiPubblica.md`](implementationPlan-V-10-ricostruzioneApiPubblica.md) chiuso (completato il 2026-10-26, Step 11.5.8 compreso), [`implementationPlan-12-importCartePerPagina.md`](implementationPlan-12-importCartePerPagina.md) applicato (import completo che non esaurisce la memoria, serve allo Step 13.4) e
> [`implementationPlan-V-11-githubActionBuildGhcr.md`](implementationPlan-V-11-githubActionBuildGhcr.md) applicato (pipeline `new` → ghcr.io → VM funzionante sul sito di test: **completato il 2026-10-26**, rollback compreso).
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice (compose, nginx, `.env.example`) lo applichi tu a mano.
> **Sostituisce il vecchio Step 12.2** ("redeploy pulito" con `new-site.sh`: stop di `~/sites/SWUDB`, cambio del branch di default, ricreazione della cartella): non serve più.

## Perché si scambiano gli ambienti invece di fare un merge

Nel nuovo sito cambiano i container (`app` e `worker` con l'immagine di ghcr.io, `nginx`, `db`), il compose e soprattutto lo schema del database (PK stringa `cards.id`,
pivot con `card_id`, tabelle nuove come `decks`, `collection_cards`, `system_errors`). Un merge su `laravel` con redeploy sopra la vecchia cartella obbligherebbe a ricostruire tutto
sul posto, con il sito fermo per tutto il tempo e senza una strada di ritorno.

Il sito di test è già un ambiente completo e provato (stesso compose, stessa pipeline, catalogo carte già importato, immagini già scaricate): gli si cambia solo il dominio.

```
prima:  unlimiteddb.mandich.dev        → ~/sites/SWUDB         (vecchia SWUDB, branch laravel)
        unlimiteddb-test.mandich.dev   → ~/sites/unlimiteddb   (nuovo sito, branch new)

dopo:   unlimiteddb-old.mandich.dev    → ~/sites/SWUDB         (congelato, solo consultazione)
        unlimiteddb.mandich.dev        → ~/sites/unlimiteddb   (produzione)
```

Cartelle, container, volumi e database **non si rinominano**: cambiano solo il dominio (regola di Traefik) e l'`APP_URL`. Per questo il rollback (Step 13.7) è solo routing e dura secondi.

## Cosa dipende dal dominio

| Elemento | Dove | Azione |
|---|---|---|
| Regola `Host(...)` di Traefik | `docker-compose.yml` (label di `nginx`) | resa parametrica (`SITE_HOST`), Step 13.1 |
| `server_name` | `docker/nginx/default.conf` | `_`, Step 13.1 (Traefik filtra già per host; c'è un solo server block) |
| `APP_URL` | `.env` sulla VM (non in Git) | cambia al cutover (Step 13.5): serve a `asset()`, ai link delle mail e alle immagini dell'API |
| Webhook Telegram | Telegram | `setWebhook` sul nuovo host (Step 13.5.5) |
| Certificato TLS | Traefik / Let's Encrypt | automatico alla prima richiesta, se il DNS risolve |
| DNS | Cloudflare | nessuna modifica se `*.mandich.dev` è un record wildcard: verificalo per `unlimiteddb-old` (Step 13.2) |
| Mail (Resend) | dominio `mandich.dev` già verificato | nessuna azione |
| Sessioni | cookie legati all'host | chi è loggato sul nuovo sito dovrà rifare il login (previsto) |
| Cron o script sulla VM che citano il vecchio dominio | `crontab -l`, `~/scripts` | da cercare e togliere (Step 13.2) |

## Decisioni e assunzioni (verificale prima di partire)

- **Dati del vecchio sito non migrati**: utenti, mazzi e collezione della vecchia SWUDB restano nel suo database, consultabile su `unlimiteddb-old` finché non si dismette (Step 13.8). Importarli nel nuovo schema è un lavoro a parte (piano dedicato, backlog), non fa parte di questo.
- **Partenza pulita** (deciso il 2026-10-21): prima del cutover il database del nuovo sito si azzera (`migrate:fresh --seed`) e le carte si reimportano da zero (Step 13.4), niente dati di prova in produzione. **Rischio noto**: il 2026-10-18 l'import completo è andato in errore sulle liste lunghe (memory exhausted dopo ~180 pagine, 502 dell'API alla pagina 126, "attempted too many times"). Il fix è nel piano 12 [`implementationPlan-12-importCartePerPagina.md`](implementationPlan-12-importCartePerPagina.md), prerequisito di questo piano. Il cutover non parte finché un import completo non finisce senza errori su questo sito.
- **Il vecchio sito si congela**: Action disattivata e nessun cron che lo chiami, così non parte più nessuna scansione dal vecchio sito. Non serve spegnere Telegram o le mail del vecchio sito: restano consultabili e, se qualcosa parte, i messaggi sono riconoscibili dal prefisso.
- **Stesso bot Telegram** per vecchio e nuovo sito, e il prefisso dei messaggi (`TELEGRAM_MESSAGE_PREFIX`) serve a distinguere chi scrive: controlla che nei due `.env` sia **diverso** (di solito vuoto sul sito di produzione e `dev: ` altrove), e che dopo il cutover quello di `unlimiteddb` sia quello che vuoi per la produzione. Un bot ha un solo webhook, e il vecchio sito non ha nessuna route webhook (solo invii in uscita, verificato in `routes/web.php`): `setWebhook` sul nuovo host non toglie nulla al vecchio.
- **Vecchio sito, dati letti dalla repo locale `PROJECT/SWUDB`** (verifica che coincidano con la VM nello Step 13.2): cartella `~/sites/SWUDB`, host `unlimiteddb.mandich.dev` (label Traefik e `server_name`), branch `laravel`, container `SWUDB_app`/`SWUDB_nginx`/`SWUDB_db`, router e service Traefik `SWUDB` (diversi da `unlimiteddb` del nuovo), subnet `172.23.0.0/24` (il nuovo usa `172.22.0.0/24`), database `my_swudb` con utente `swudb`, **nessun servizio `worker`** (code e `Schedule::command('scan:run')` di `routes/console.php` hanno bisogno di un processo esterno: da cercare nel crontab della VM), nessun mount di `public/storage`.
- **Link esterni al vecchio sito**: con lo spostamento su `unlimiteddb-old` gli URL del vecchio sito (`/mazzo/...`, `/api/mazzi/...`, link condivisi) non funzionano più sul dominio principale: il nuovo sito ha altre rotte (`/mazzo/{utente}/{mazzo}` esiste ancora, ma gli utenti e i mazzi non sono migrati). Da accettare prima del cutover.
- **Finestra di manutenzione**: il sito vecchio è irraggiungibile da 13.5.2 a 13.5.4, circa un minuto con i comandi già pronti. Scegli un orario tranquillo.

## Step 13.1 — Host parametrico nella repo (nessun cambio di comportamento)

Da fare **prima** del cutover, con il sito di test ancora sul suo host: così il cambio di dominio diventa una modifica del `.env` sulla VM, senza commit, build e attese.

#### 13.1.1 — `SITE_HOST` nel `.env` della VM (prima del push)
```bash
cd ~/sites/unlimiteddb
grep -q '^SITE_HOST=' .env || echo 'SITE_HOST=unlimiteddb-test.mandich.dev' >> .env
grep -n 'SITE_HOST\|APP_URL' .env
```
Va fatto **prima** del push: senza la variabile il compose nuovo si rifiuta di partire (`:?` qui sotto) e il deploy si ferma.

#### 13.1.2 — `docker-compose.yml`
```diff
-      - "traefik.http.routers.unlimiteddb.rule=Host(`unlimiteddb-test.mandich.dev`)"
+      - "traefik.http.routers.unlimiteddb.rule=Host(`${SITE_HOST:?SITE_HOST mancante nel .env}`)"
```
Docker Compose legge da solo il `.env` della cartella del progetto per l'interpolazione, ed è lo stesso file che i container montano. `docker-compose.dev.yml` non va toccato.

#### 13.1.3 — `docker/nginx/default.conf`
```diff
-    server_name unlimiteddb-test.mandich.dev;
+    server_name _;
```

#### 13.1.4 — `.env.example`
Aggiungere in fondo:
```
# Solo produzione (docker-compose.yml): dominio del sito, letto dalla regola di Traefik
# SITE_HOST=unlimiteddb.mandich.dev
```

#### 13.1.5 — Commit, push e verifica
```bash
git add docker-compose.yml docker/nginx/default.conf .env.example
git commit -m "Compose: host del sito parametrico (SITE_HOST), server_name generico"
git push origin new
```
La pipeline del piano 11 fa il deploy (il hook `post-deploy.sh` ricarica anche nginx, così il nuovo `default.conf` è attivo). Poi:
```bash
curl -sI https://unlimiteddb-test.mandich.dev | head -1      # 200, come prima
docker compose -f ~/sites/unlimiteddb/docker-compose.yml config | grep 'routers.unlimiteddb.rule'   # Host(`unlimiteddb-test.mandich.dev`)
```

## Step 13.2 — Rilevamento sulla VM (sola lettura)

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Image}}'
cd ~/sites/SWUDB
git branch --show-current
git status --short --untracked-files=no
docker compose ps
grep -n 'traefik\|subnet\|container_name\|server_name' docker-compose.yml docker/nginx/default.conf
grep -n '^APP_URL\|^TELEGRAM\|^MAIL_MAILER\|^QUEUE_CONNECTION' .env | sed 's/\(TOKEN=\|SECRET=\).*/\1***/'

# job fuori da Docker che citano il dominio
crontab -l 2>/dev/null
grep -rn 'mandich.dev' ~/scripts 2>/dev/null | grep -v 'new-site.sh'

# DNS e spazio
nslookup unlimiteddb-old.mandich.dev
df -h / ; free -m ; docker system df
```
Da controllare (i valori attesi vengono dalla repo locale `PROJECT/SWUDB`):
- il **router Traefik** del vecchio sito è `SWUDB`, diverso da `unlimiteddb` del nuovo (se la VM mostrasse lo stesso nome, Traefik li confonderebbe);
- la **subnet** del vecchio è `172.23.0.0/24`, non `172.22.0.0/24`;
- il vecchio non ha un servizio `worker`: controlla che sulla VM non ci sia un cron o un timer che lancia `schedule:run` o `scan:run` sul vecchio sito (`crontab -l`, `systemctl list-timers`): se c'è, va tolto al passo 13.5.3;
- `TELEGRAM_MESSAGE_PREFIX` nei due `.env` (vedi "Decisioni");
- `unlimiteddb-old.mandich.dev` risolve all'IP della VM (con il wildcard su Cloudflare è automatico; se Cloudflare fa da proxy, controlla anche la modalità TLS).

Annota i nomi trovati: servono per i comandi dei passi seguenti.

## Step 13.3 — Backup

```bash
B=~/backup/pre-cutover-$(date +%F) && mkdir -p "$B" && chmod 700 "$B"

# vecchio sito (database `my_swudb`, come da suo docker-compose.yml)
cd ~/sites/SWUDB
docker compose exec -T db mariadb-dump -u root --single-transaction my_swudb > "$B/old.sql"
cp .env "$B/old.env"
tar czf "$B/old-storage.tgz" storage

# nuovo sito
cd ~/sites/unlimiteddb
docker compose exec -T db mariadb-dump -u root --single-transaction unlimiteddb > "$B/new-test.sql"
cp .env "$B/new.env"

ls -lh "$B"; df -h /
```
I `.env` contengono segreti: la cartella resta `700` e fuori da qualunque repo. Se il disco è troppo pieno, salta `old-storage.tgz` e tieni solo i dump e i `.env`.

## Step 13.4 — Reset pulito del database e reimport delle carte

Si fa sul sito **ancora sull'host di test**, prima del cutover: la vecchia produzione resta online per tutta la durata (lunga) dell'import. Il dump del database di prova è già in `$B/new-test.sql` (Step 13.3).

#### 13.4.1 — Prerequisiti
- Nel `.env` della VM del nuovo sito ci sono `SEED_ADMIN_NAME`, `SEED_ADMIN_EMAIL` e `SEED_ADMIN_PASSWORD` (non sono in `.env.example`: vanno aggiunte a mano): dopo il reset il `DatabaseSeeder` è l'unico modo in cui nasce l'utente admin, con email già verificata.
- Nel `.env` ci sono `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID` e `RESEND_API_KEY` con `MAIL_MAILER=resend`; `TELEGRAM_MESSAGE_PREFIX` è quello che vuoi vedere sui messaggi dell'import (finisce nello stesso bot del vecchio sito).
- Il disco regge le immagini: `df -h /` (era al 79%) e `du -sh storage/app/public/cards`. Dopo il reset le immagini si riscaricano tutte.
- Il problema dell'import del 2026-10-18 (memoria esaurita dopo ~180 pagine, 502 dell'API alla pagina 126) è risolto dal piano 12 [`implementationPlan-12-importCartePerPagina.md`](implementationPlan-12-importCartePerPagina.md): applicato **e** provato con un import completo in locale (Step 7 di quel piano). Se non lo è, fermati qui e non azzerare niente. Applicato quel piano, anche `DB_QUEUE_RETRY_AFTER=300` e `LOG_LEVEL=info` vanno messi nel `.env` della VM.

#### 13.4.2 — Reset
```bash
cd ~/sites/unlimiteddb
docker compose stop worker
docker compose exec -T app php artisan migrate:fresh --seed --force
docker compose exec -T app sh -c 'rm -rf storage/app/public/cards/*'
docker compose exec -T app php artisan cache:clear
docker compose start worker
```
`migrate:fresh` cancella **tutte** le tabelle (utenti, mazzi, collezioni, code, `system_errors`) e le ricrea; `--force` è obbligatorio con `APP_ENV=production`. `--seed` crea permessi, ruolo `admin` e l'utente admin da `SEED_ADMIN_*`. Il `rm` sul volume `storage` toglie anche le immagini del vecchio import, così non restano file orfani.

#### 13.4.3 — Reimport completo
```bash
docker compose exec -T app php artisan cards:scan
docker compose logs -f worker           # in un'altra shell, per seguire l'avanzamento
free -m; df -h /                        # ogni tanto: RAM e disco della VM
```
L'avanzamento arriva anche sul bot Telegram. Il job può durare a lungo e, se la memoria del worker è al limite, fallire come il 18/10: controlla i log della prima ora.

#### 13.4.4 — Verifiche a import finito
```bash
docker compose exec -T app php artisan queue:failed                      # vuota
docker compose exec -T db mariadb -u root unlimiteddb -e "
  SELECT COUNT(*) AS carte FROM cards;
  SELECT expansion, COUNT(*) n FROM cards GROUP BY expansion ORDER BY expansion;
  SELECT COUNT(*) AS aperti FROM system_errors WHERE status = 'open';
  SELECT COUNT(*) AS senza_fronte FROM cards WHERE front_art_path IS NULL;
  SELECT id, name, email, email_verified_at FROM users;"
du -sh storage/app/public/cards
```
- il numero di carte coincide con quello dell'API ufficiale (o con la somma riportata nel messaggio Telegram finale);
- `system_errors` aperti: nessuno, oppure solo problemi singoli già capiti (aprili da `/admin/errori`);
- un solo utente (l'admin del seeder), con email verificata;
- `https://unlimiteddb-test.mandich.dev/carte` mostra le carte con le immagini.

#### 13.4.5 — Espansioni da sistemare a mano (obbligatorio prima del cutover)
Il job crea le espansioni nuove con `rotation` = massima rotazione esistente e `legal_date` = data di pubblicazione dell'API: valori approssimativi, e `confirmed = false`. La validazione del formato Premier (`Expansion::validExpansions()`) considera solo le espansioni **confermate** delle ultime due rotazioni, quindi con un database appena azzerato **nessun mazzo Premier è valido** finché non le correggi.
Da `/admin/espansioni` (admin): per ogni espansione imposta `legal_date`, `rotation`, `group_main_expansion` e `confirmed`. I valori giusti stanno nel database di prima (`$B/new-test.sql`, tabella `expansions`), se li avevi già curati: leggili da lì invece di ricostruirli.
Verifica con un mazzo Premier di prova creato e poi cancellato, oppure da `tinker`: `App\Models\Expansion::validExpansions()`.

## Step 13.5 — Cutover

Prima di iniziare: nessun run della pipeline in corso (tab Actions), backup fatti (13.3), Step 13.1 applicato e verificato, nomi del vecchio sito annotati (13.2), **import completo riuscito e espansioni confermate (13.4)**.

#### 13.5.1 — Disattivare la Action del vecchio sito
Nel branch `laravel` (progetto vecchio) commentare il trigger `on: push` di `.github/workflows/deploy.yml`, committare e pushare. I secret SSH sono a livello di repo: un push sul vecchio branch rilancerebbe `deploy.sh SWUDB` contro un sito spostato. Si fa **per prima**, così nulla può rideployare il vecchio sito a metà cutover.

#### 13.5.2 — Spostare la vecchia produzione su `unlimiteddb-old`
```bash
cd ~/sites/SWUDB
cp docker-compose.yml /tmp/SWUDB.docker-compose.bak
cp docker/nginx/default.conf /tmp/SWUDB.nginx.bak
cp .env /tmp/SWUDB.env.bak

sed -i 's/unlimiteddb\.mandich\.dev/unlimiteddb-old.mandich.dev/g' docker-compose.yml docker/nginx/default.conf
sed -i 's#^APP_URL=.*#APP_URL=https://unlimiteddb-old.mandich.dev#' .env
grep -n 'mandich.dev' docker-compose.yml docker/nginx/default.conf .env      # solo -old

docker compose up -d --no-build
```
Il checkout del vecchio sito resta con file tracciati modificati: va bene, è congelato (e senza Action). Niente `--build`: sulla VM da 1 GB non si ricostruisce nulla.

#### 13.5.3 — Fermare le attività automatiche del vecchio sito
Il vecchio sito non ha un `worker`: l'unica attività automatica possibile è un cron o un timer sulla VM che lo chiama (scan settimanale). Solo se 13.2 ne ha trovato uno:
```bash
crontab -e                                 # (vim) togli o commenta le righe che chiamano il vecchio sito
```
Telegram e mail del vecchio sito si lasciano com'erano: i messaggi si riconoscono dal prefisso (vedi "Decisioni"). Se il vecchio sito dovesse mandare la scansione settimanale in contemporanea al nuovo, si distingue dal prefisso e si disattiva dal cron.

#### 13.5.4 — Spostare il nuovo sito su `unlimiteddb`
```bash
cd ~/sites/unlimiteddb
sed -i 's#^SITE_HOST=.*#SITE_HOST=unlimiteddb.mandich.dev#' .env
sed -i 's#^APP_URL=.*#APP_URL=https://unlimiteddb.mandich.dev#' .env
grep -n 'SITE_HOST\|APP_URL' .env

docker compose up -d --no-build          # ricrea solo nginx (la label cambia)
docker compose restart app worker        # rileggono APP_URL
```
Se il sito dà 504 o 404 dopo qualche secondo è il noto problema del provider Docker di Traefik: `docker restart traefik`.

#### 13.5.5 — Webhook Telegram
```bash
curl -F "url=https://unlimiteddb.mandich.dev/telegram/webhook" -F "secret_token=<TELEGRAM_WEBHOOK_SECRET>" https://api.telegram.org/bot<TOKEN>/setWebhook
curl https://api.telegram.org/bot<TOKEN>/getWebhookInfo      # url nuovo, nessun last_error_message
```

## Step 13.6 — Verifica post-cutover

#### 13.6.1 — Host
```bash
curl -sI https://unlimiteddb.mandich.dev | head -1            # 200
curl -s  https://unlimiteddb.mandich.dev/carte | head -c 300  # HTML del nuovo sito
curl -sI https://unlimiteddb-old.mandich.dev | head -1        # 200, vecchio sito
curl -sI https://unlimiteddb-test.mandich.dev | head -1       # 404: l'host di test non è più instradato (atteso)
```

#### 13.6.2 — Webhook Telegram
`getWebhookInfo` come sopra; da Telegram `/search <nome carta>` risponde, `/scan` dalla chat admin parte. I messaggi del nuovo sito hanno il prefisso scelto per la produzione.

#### 13.6.3 — Coda e worker
`failed_jobs` vuota (`docker compose exec -T app php artisan queue:failed`), container `unlimiteddb_worker` `Up` (`docker ps`, non solo `_app`).

#### 13.6.4 — Scan schedulato
`docker compose exec -T app php artisan schedule:list` mostra `cards:scan` al lunedì, e `schedule:work` gira nel worker (`docker compose exec -T worker supervisorctl -c /etc/supervisor/worker.conf status`).

#### 13.6.5 — Immagini carta e icone
`https://unlimiteddb.mandich.dev/storage/cards/{EXP}/{numero}-front.{ext}` risponde 200 (un 404 indica il mount `./storage/app/public` su `nginx` o l'alias `/storage/`); `/favicon.ico` e `/build/icons/apple-touch-icon.png` rispondono 200 (generate dallo stage `icon-builder`, vedi Step 2.3 di [`implementationPlan-V-00-ricostruzioneFondamenta.md`](implementationPlan-V-00-ricostruzioneFondamenta.md)); CSS e JS di `/build/assets/...` rispondono 200.

#### 13.6.6 — Mail e link
Una registrazione di prova (o `/render-mail/new-cards`): i link nelle mail iniziano con `https://unlimiteddb.mandich.dev` (`APP_URL`), non con l'host di test.

#### 13.6.7 — Giro funzionale
Login admin, `/carte`, `/nuove-uscite`, `/mazzi`, `/collezione`, `/api/cards/search?nome=Luke` (le immagini dell'API puntano al nuovo dominio).

#### 13.6.8 — Vecchio sito
Raggiungibile su `unlimiteddb-old` (login e pagine funzionano con il nuovo `APP_URL`), nessun cron rimasto che lo chiami, nessuna scansione partita da lì.

## Step 13.7 — Rollback

Il database del nuovo sito non cambia con lo scambio: tornare indietro è solo routing.
```bash
# 1. nuovo sito di nuovo su test
cd ~/sites/unlimiteddb
sed -i 's#^SITE_HOST=.*#SITE_HOST=unlimiteddb-test.mandich.dev#' .env
sed -i 's#^APP_URL=.*#APP_URL=https://unlimiteddb-test.mandich.dev#' .env
docker compose up -d --no-build && docker compose restart app worker

# 2. vecchio sito di nuovo su unlimiteddb (container SWUDB_*, router SWUDB)
cd ~/sites/SWUDB
cp /tmp/SWUDB.docker-compose.bak docker-compose.yml
cp /tmp/SWUDB.nginx.bak docker/nginx/default.conf
cp /tmp/SWUDB.env.bak .env
docker compose up -d --no-build
```
Poi: rimettere il cron del vecchio sito se l'avevi tolto (13.5.3) e, solo se il ritorno è definitivo, riattivare la Action vecchia. Il webhook Telegram va tolto (`deleteWebhook`) o lasciato com'è: il vecchio sito non lo usa, quindi resta puntato sul nuovo host senza danni, ma `/scan` e `/search` rispondono solo se il nuovo sito è raggiungibile su quell'host (con il rollback il nuovo host è `unlimiteddb-test`: rifai `setWebhook` con quell'URL se vuoi i comandi del bot). Gli utenti registrati sul nuovo sito nel frattempo restano nel suo database.

## Step 13.8 — Dismissione del vecchio sito (dopo qualche settimana, non subito)

1. Backup finale (comandi di 13.3).
2. `cd ~/sites/SWUDB && docker compose down` (senza `-v`; i volumi si cancellano solo dopo aver controllato il backup).
3. Togliere la riga `github-actions-deploy-SWUDB` da `~/.ssh/authorized_keys` e il secret `SSH_PRIVATE_KEY` dalla repo su GitHub.
4. Togliere il record/routing di `unlimiteddb-old` (con il wildcard basta che non ci sia più un container). Dopo `down`, `~/sites/SWUDB` e il volume `SWUDB_db_data` restano su disco: cancellali solo dopo aver verificato il backup.
5. Non rinominare ora il branch `new` in `main`: il trigger del workflow, il `command=` della chiave SSH e il compose lo citano in tre posti.

## Step 13.9 — Documentazione

1. **README.md**: "Sito" → `https://unlimiteddb.mandich.dev`; compose di produzione descritto con `SITE_HOST` (non più host fisso); comando `setWebhook` con il nuovo URL; sezione "Deploy" con il cutover fatto e il vecchio sito su `unlimiteddb-old`.
2. **todo.md**: spunta la sezione "13 — Messa in produzione, scambio degli ambienti".
3. **Indice** (`implementationPlan-ricostruzioneUnlimitedDB.md`): stato del piano 13 a completato.
4. Rinomina questo file in `implementationPlan-V-13-ricostruzioneDeploy.md`.
