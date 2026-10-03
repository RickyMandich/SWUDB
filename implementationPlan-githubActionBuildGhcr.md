# Implementation plan — Build dell'immagine su GitHub Actions (ghcr.io) e deploy con pull sulla VM

> Piano trasversale (non numerato). Riguarda il branch `new` e il sito di test <https://unlimiteddb-test.mandich.dev>.
> Regola del progetto (`.agent/rules/vault.md`): questo piano è l'unico output, il codice (workflow, compose, script) lo applichi tu a mano.

## Perché

La VM di produzione è uno shape `VM.Standard.E2.1.Micro` (1 GB di RAM, 1/8 di OCPU). Le build dell'immagine (`npm ci` + Vite, `composer install`, stage `icon-builder` con apt, `docker-php-ext-install`) sulla VM durano molti minuti e a volte vanno in `DeadlineExceeded`: `vmstat` mostrava `st` (CPU rubata dall'hypervisor) al 60% e oltre, più swap in thrashing. Compilare lì ad ogni deploy non è sostenibile.

Soluzione: **l'immagine si costruisce su GitHub** (runner molto più veloci, con cache), si pubblica su **ghcr.io** e la VM fa solo `docker compose pull` + `up`.

```
push su `new`
   │
   ├─ job build   (GitHub)  docker build  →  ghcr.io/rickymandich/swudb:new  +  :sha-<commit>
   │
   └─ job deploy  (GitHub)  SSH sulla VM  →  ~/scripts/deploy-image.sh
                                              git pull  →  docker compose pull  →  up --no-build  →  migrate
```

## Decisioni e assunzioni (verificale prima di partire)

- **Nome immagine**: `ghcr.io/rickymandich/swudb`. Il remote `origin` è `RickyMandich/SWUDB` e ghcr.io vuole il nome tutto minuscolo. Se il repo cambia nome, va cambiato in tre punti: `env.IMAGE` del workflow, e i due `image:` del compose.
- **Trigger**: solo `push` sul branch `new`, nessun `workflow_dispatch`. I push su `laravel` restano gestiti dalla vecchia action di SWUDB, che non si tocca.
- **File del workflow**: `.github/workflows/deploy-unlimiteddb.yml`, diverso da `deploy.yml` della vecchia versione, così non c'è collisione nemmeno quando i branch verranno uniti.
- **Chiave SSH e secret nuovi**: la chiave della vecchia action (`SSH_PRIVATE_KEY`) ha un forced command legato a `deploy.sh SWUDB` e non va toccata. Si crea una chiave nuova, con forced command su uno **script nuovo** (`deploy-image.sh`). Non uso `deploy.sh` perché non l'ho visto e quasi certamente ricostruisce l'immagine sulla VM, cioè il problema da evitare. `SSH_HOST` e `SSH_USER` si riusano.
- **Migrazioni automatiche**: lo script esegue `php artisan migrate --force` ad ogni deploy. Se non lo vuoi, togli quella riga (Step 1).
- **Pacchetto ghcr privato**: di default un pacchetto nuovo può essere privato, quindi la VM deve fare un `docker login ghcr.io` una tantum (Step 6).
- **Costo**: su un repo privato le build consumano minuti di GitHub Actions (2000/mese nel piano gratuito); una build con cache dovrebbe stare sotto i 10 minuti.
- `docker-compose.dev.yml` non va toccato da questo piano: lo sviluppo locale continua a costruire in locale. L'immagine unica per `app` e `worker` è già applicata in dev (`image: unlimiteddb:dev`, `build: .` solo su `app`); lo Step 3 fa lo stesso in produzione con l'immagine di ghcr.io.
- Non rilanciare `finish-unlimiteddb.sh`: fa la build sulla VM e al passo 10 creerebbe una chiave con forced command `deploy.sh`. Di quello script restano utili solo due cose, entrambe già coperte qui sotto: restart di Traefik (Step 7) e import SQL opzionale (a mano).

## Step 0 — Pulizia sulla VM

Prima di iniziare, sulla VM:

```bash
# ferma eventuali build rimaste appese (Ctrl+C nelle shell dove giravano) e controlla che non ce ne siano
pgrep -af "docker.*build" || echo "nessuna build in corso"

# stato del disco (era al 79%)
docker system df
```

La pulizia della cache di build si fa **dopo** il primo deploy riuscito (Step 7), per non buttare layer utili se qualcosa va storto.

## Step 1 — Script di deploy sulla VM

Crea `~/scripts/deploy-image.sh` sulla VM (con `vim ~/scripts/deploy-image.sh`) e rendilo eseguibile con `chmod +x ~/scripts/deploy-image.sh`:

```bash
#!/bin/bash
# deploy-image.sh — deploy di unlimiteddb partendo dall'immagine pubblicata su ghcr.io.
# Lanciato da GitHub Actions via SSH (forced command in ~/.ssh/authorized_keys).
set -euo pipefail

SITE="unlimiteddb"
BRANCH="new"
SITE_DIR="/home/ubuntu/sites/$SITE"

# un solo deploy alla volta
exec 9>"/tmp/deploy-$SITE.lock"
flock -n 9 || { echo "Un deploy di $SITE è già in corso." >&2; exit 1; }

cd "$SITE_DIR"

# Nessuna modifica locale ai file tracciati: altrimenti "git pull --ff-only" fallirebbe
# o, peggio, qualcuno perderebbe lavoro. I file non tracciati (.env, storage) non contano.
if [[ -n "$(git status --porcelain --untracked-files=no)" ]]; then
    echo "Errore: ci sono modifiche locali non committate in $SITE_DIR:" >&2
    git status --short --untracked-files=no >&2
    exit 1
fi

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

# nginx monta ./public in sola lettura e sopra ci monta il volume build_assets in public/build:
# la cartella (gitignored) deve esistere sull'host, altrimenti Docker non può crearla ("read-only file system")
mkdir -p public/build

# Niente build sulla VM: se il pull fallisce, il deploy si ferma (set -e) invece di ripiegare su una build lentissima.
docker compose pull app worker
docker compose up -d --no-build --remove-orphans --wait --wait-timeout 180

docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan queue:restart

docker image prune -f
echo "Deploy di $SITE completato ($(git rev-parse --short HEAD))."
```

Note:
- `git pull` aggiorna anche i file che il compose monta dal checkout: `docker/nginx/default.conf`, `docker/mysql/init.sql`, `docker/supervisor/worker.conf` e `public/`. Per questo il deploy fa il pull oltre al `docker compose pull`.
- `queue:restart` fa ricaricare il codice ai processi `queue:work` del worker (supervisord li rilancia).

## Step 2 — Chiave SSH dedicata e secret su GitHub

Sulla VM:

```bash
cp ~/.ssh/authorized_keys ~/.ssh/authorized_keys.bak

# crea la chiave solo se non esiste già
[[ -f ~/.ssh/deploy_unlimiteddb ]] || ssh-keygen -t ed25519 -f ~/.ssh/deploy_unlimiteddb -C "github-actions-deploy-unlimiteddb" -N ""

# toglie una eventuale riga già presente per questo sito (es. creata da finish-unlimiteddb.sh con forced command su deploy.sh)
sed -i '/github-actions-deploy-unlimiteddb/d' ~/.ssh/authorized_keys

# forced command: questa chiave può solo lanciare deploy-image.sh
echo "command=\"/home/ubuntu/scripts/deploy-image.sh\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding $(cat ~/.ssh/deploy_unlimiteddb.pub)" >> ~/.ssh/authorized_keys

# stampa la chiave privata da incollare su GitHub
cat ~/.ssh/deploy_unlimiteddb
```

Su GitHub → repo `SWUDB` → Settings → Secrets and variables → Actions:
- **nuovo secret** `SSH_PRIVATE_KEY_UNLIMITEDDB` = contenuto completo della chiave, comprese le righe `BEGIN`/`END`;
- `SSH_HOST` e `SSH_USER` esistono già (li usa la action di SWUDB): non toccarli;
- `SSH_PRIVATE_KEY` **non va sovrascritto**.

## Step 3 — `docker-compose.yml`

Il servizio `app` mantiene `build: .` (per build manuali e sviluppo) ma aggiunge `image:`; il `worker` usa solo `image:` e quindi la stessa immagine.

Servizio `app`:

```diff
 services:
   app:
-    build: .
+    image: ghcr.io/rickymandich/swudb:new
+    build: .
     container_name: unlimiteddb_app
```

Servizio `worker`:

```diff
   worker:
-    build: .
+    image: ghcr.io/rickymandich/swudb:new
     container_name: unlimiteddb_worker
```

Con `up --no-build` (Step 1) il compose non costruisce mai nulla: usa l'immagine scaricata. Per costruire a mano in locale resta `docker compose build app`.

## Step 4 — Workflow GitHub Actions

Crea la cartella `.github/workflows/` (oggi non esiste) e dentro `deploy-unlimiteddb.yml`:

```yaml
name: Deploy unlimiteddb (build su GitHub, pull sulla VM)

on:
  push:
    branches: [new]

# un solo deploy alla volta, senza interrompere quello in corso
concurrency:
  group: deploy-unlimiteddb
  cancel-in-progress: false

env:
  IMAGE: ghcr.io/rickymandich/swudb

jobs:
  build:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write
    steps:
      - uses: actions/checkout@v4

      - uses: docker/setup-buildx-action@v3

      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - uses: docker/build-push-action@v6
        with:
          context: .
          platforms: linux/amd64
          push: true
          provenance: false
          tags: |
            ${{ env.IMAGE }}:new
            ${{ env.IMAGE }}:sha-${{ github.sha }}
          labels: |
            org.opencontainers.image.source=${{ github.server_url }}/${{ github.repository }}
            org.opencontainers.image.revision=${{ github.sha }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

  deploy:
    needs: build
    runs-on: ubuntu-latest
    steps:
      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1.0.3
        with:
          host: ${{ secrets.SSH_HOST }}
          username: ${{ secrets.SSH_USER }}
          key: ${{ secrets.SSH_PRIVATE_KEY_UNLIMITEDDB }}
          script: /home/ubuntu/scripts/deploy-image.sh
          command_timeout: 15m
```

Note:
- `platforms: linux/amd64`: la VM è x86_64.
- `provenance: false` evita che ghcr mostri anche un manifest "unknown/unknown" accanto all'immagine.
- Il tag `:new` è quello che usa il compose; `:sha-<commit>` serve per il rollback (Step 8).
- Il job `deploy` parte solo se `build` riesce.
- Con il forced command la riga `script:` viene ignorata dal server (parte sempre `deploy-image.sh`): resta per chiarezza.

## Step 5 — Commit e push su `new`

Da Windows, nella cartella del progetto:

```bash
git status
git add .github/workflows/deploy-unlimiteddb.yml docker-compose.yml implementationPlan-githubActionBuildGhcr.md todo.md README.md
git commit -m "CI: build immagine su GitHub Actions (ghcr.io) e deploy con pull sulla VM"
git push origin new
```

Prima di pushare controlla sulla VM che il checkout sia pulito, altrimenti il primo deploy si ferma con l'errore dello script:

```bash
git -C ~/sites/unlimiteddb status --short --untracked-files=no
```

Se elenca file modificati e sono gli stessi che hai già in repo (sincronizzati a mano), buttali con `git -C ~/sites/unlimiteddb checkout -- .` **solo dopo** aver verificato con `git diff` che non ci sia niente da salvare.

## Step 6 — Primo run e accesso a ghcr.io dalla VM

Il push fa partire la action (tab Actions su GitHub). Il job `build` senza cache impiega qualche minuto; i successivi molto meno.

Al primo giro il job `deploy` può fallire con `pull access denied` o `unauthorized`, se il pacchetto è privato e la VM non è loggata. Due strade:

- **Pacchetto pubblico** (solo se il codice nell'immagine può essere pubblico, cioè se il repo è pubblico): GitHub → tuo profilo → Packages → `swudb` → Package settings → Change visibility → Public.
- **Pacchetto privato** (consigliato se il repo è privato): crea un personal access token *classic* con solo lo scope `read:packages`, poi sulla VM:

  ```bash
  echo "<TOKEN>" | docker login ghcr.io -u RickyMandich --password-stdin
  ```

  Le credenziali restano in `~/.docker/config.json` dell'utente `ubuntu`, lo stesso che esegue il deploy.

Poi su GitHub: Actions → il run fallito → **Re-run failed jobs** (non serve rifare la build).

## Step 7 — Verifica post-deploy

Sulla VM:

```bash
cd ~/sites/unlimiteddb
docker compose ps                                   # app, nginx, db, worker: tutti Up
docker compose exec -T worker sh -c "ps | grep '[q]ueue:work'"   # tanti processi quanti ne ha numprocs
curl -sI https://unlimiteddb-test.mandich.dev | head -1          # 200
curl -sI https://unlimiteddb-test.mandich.dev/favicon.ico | head -1   # 200 (icone, Step 12.1.5 del piano 11)
```

Controlla anche un file di `/build/assets/...` dal browser (CSS/JS devono rispondere 200, non 404). Se compare un 504 su un sito appena creato, è il noto problema del provider Docker di Traefik: `docker restart traefik`.

A deploy riuscito, libera il disco dalle build fallite o appese:

```bash
docker builder prune -af
docker system df
```

Poi verifica che la action di SWUDB continui a funzionare: un push su `laravel` deve far partire la vecchia action e **non** quella nuova.

## Step 8 — Rollback

Ogni build pubblica anche il tag `:sha-<commit>`. Per tornare a una versione precedente, sulla VM:

```bash
cd ~/sites/unlimiteddb
docker pull ghcr.io/rickymandich/swudb:sha-<COMMIT_COMPLETO>
docker tag  ghcr.io/rickymandich/swudb:sha-<COMMIT_COMPLETO> ghcr.io/rickymandich/swudb:new
docker compose up -d --no-build
```

Il prossimo push su `new` sovrascrive di nuovo `:new` con la build più recente.

## Step 9 — Documentazione

Quando gli step 1–8 sono fatti:

1. **README.md**, sezione "Deploy": sostituisci la nota "Non ancora implementato" sulla pipeline di build con la descrizione reale, ad esempio:

   > Il branch `new` (sito di test <https://unlimiteddb-test.mandich.dev>) si deploya da solo: un push su `new` avvia `.github/workflows/deploy-unlimiteddb.yml`, che costruisce l'immagine su GitHub, la pubblica su `ghcr.io/rickymandich/swudb` (tag `new` e `sha-<commit>`) e poi, via SSH, lancia `~/scripts/deploy-image.sh` sulla VM (`git pull`, `docker compose pull`, `up --no-build`, `migrate --force`, `queue:restart`). La VM non compila mai l'immagine. Secret richiesti: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY_UNLIMITEDDB`. Rollback con il tag `sha-<commit>`.

2. **todo.md**: spunta gli step della sezione "Pipeline di build su GitHub Actions".
3. Rinomina questo file in `implementationPlan-V-githubActionBuildGhcr.md`.

## Impatto sul piano 11 (redeploy pulito)

Il redeploy dello Step 12.2 rigenera con `new-site.sh` `docker-compose.yml` e `.github/workflows/deploy.yml` dal template, e quindi sovrascriverebbe `image:` e il workflow di questo piano. Prima del redeploy bisogna aggiornare i template di `new-site.sh` (immagine da ghcr.io al posto di `build: .`, workflow con build su GitHub) oppure evitare di rigenerare quei file. È già annotato nello Step 12.2.0 del piano 11.
