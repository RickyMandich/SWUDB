# Implementation plan 15 — Import carte: una sola catena di job, timeout applicato

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Correzione trovata sul primo reimport completo in produzione (Step 13.4 del piano 13). Data: 2026-11-25.
> Va applicato e rilasciato **prima** della prima scansione automatica (lunedì 2026-11-30 00:00) e preferibilmente prima del cutover.

## Problema (dati reali del 2026-11-25, VM Oracle da 1 GB)

Sul reimport (scan `3a523f02`, 10801 carte) i dati sono completi, ma `system_errors` ha 2042 errori aperti: 2041 `Duplicate entry ... for key 'PRIMARY'` (carte e righe `card_aspect`) e 1 download di immagine. Dai log:

| Pagina (originali) | Elaborata alle | Durata |
|---|---|---|
| 16 | 00:22:00 | — |
| 17 | 00:25:46 | 226 s |
| 18 | 00:31:15 e di nuovo 00:32:31 | 329 s (oltre `DB_QUEUE_RETRY_AFTER` = 300) |

Catena degli eventi:
1. La pagina 18 supera 300 s: la coda `database` la riassegna al secondo worker mentre il primo gira ancora.
2. Entrambi finiscono e **ognuno accoda la pagina 19**: da qui due catene parallele con lo stesso `runId` (stesso lock, quindi nessuno si ferma), "Scan completato" compare due volte.
3. Le due catene inseriscono le stesse carte quasi insieme: `Duplicate entry` sulla PK di `cards` o di `card_aspect`.

Aggravante: nell'immagine non c'è `pcntl` (`php -m` non lo elenca, il `Dockerfile` non lo installa), quindi né `--timeout=240` né `$timeout` del job vengono fatti rispettare: un job lento non viene interrotto.

Le pagine impiegano 226–329 s perché ogni pagina scarica fino a 200 immagini con 1 solo core e lo swap quasi pieno. Il `$timeout` di 240 s e il `retry_after` di 300 s erano tarati sul locale (picco 80 MB, pagine veloci).

## Step 1 — Una sola catena: `dispatchNext()` in `ImportCardsFromSwuApiJob`

La pagina successiva (e il passaggio dalla fase originali a quella varianti) si accoda solo se nessun altro job lo ha già fatto per quello scan, fase e pagina, con `Cache::add` (atomica):
- chiave `cards-import:{runId}:next:{fase}:{pagina}`, scadenza 1 giorno;
- chi perde la `add` logga un warning e non accoda.

Una pagina rielaborata può ancora produrre qualche `Duplicate entry` isolato (le due esecuzioni si sovrappongono), ma la catena resta una sola e lo scan non raddoppia.

## Step 2 — Timeout applicato e tarato sui tempi reali

1. `Dockerfile`: aggiungere `pcntl` a `docker-php-ext-install`.
2. `ImportCardsFromSwuApiJob`: `$timeout` da 240 a **600**.
3. `docker/supervisor/worker.conf` e il worker di `docker-compose.dev.yml`: `--timeout=240` → `--timeout=600`.
4. `DB_QUEUE_RETRY_AFTER` da 300 a **720** (deve restare maggiore del timeout): `.env.example` (fatto da questo piano), `.env` locale e `.env` della VM (a mano).

## Step 3 — Test

`ImportCardsFromSwuApiJobTest`: 'non accoda due volte la pagina successiva se la stessa pagina viene rielaborata' (stesso `runId`, pagina 1 eseguita due volte con `Queue::fake()`, `assertPushed` una volta sola).

## Step 4 — Rilascio e verifica

1. `.env` locale e della VM: `DB_QUEUE_RETRY_AFTER=720`.
2. `composer test` verde, poi push su `new` (la pipeline ricostruisce l'immagine con `pcntl`).
3. Sulla VM: `docker compose exec -T app php -m | grep pcntl` deve stampare `pcntl`; `docker compose exec -T app php artisan tinker --execute="echo env('DB_QUEUE_RETRY_AFTER');"` deve dire 720 (con `config:cache` assente).
4. Pulizia dei 2042 errori del 2026-11-25: da `/admin/errori`, azione in blocco, dopo aver conservato la prova della causa (questo piano).
5. Prova sul campo: la prima scansione schedulata (lunedì 00:00) non deve avere pagine elaborate due volte né `Duplicate entry`; verifica con il comando dei log usato il 2026-11-25 (`grep elaborata | sort | uniq -c | awk '$1>1'` deve essere vuoto).

## Step 5 — Documentazione

README (sezione "Scansione carte" e tabella delle variabili), `todo.md`, indice dei piani; a verifica fatta rinominare in `implementationPlan-V-15-fixCatenaDoppiaImport.md`.
