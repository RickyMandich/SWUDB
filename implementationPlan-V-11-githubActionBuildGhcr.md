# Implementation plan 11 — Ricostruzione UnlimitedDB · Fase 12: build dell'immagine su GitHub Actions (ghcr.io) e deploy con pull sulla VM

> Parte dell'indice [`implementationPlan-ricostruzioneUnlimitedDB.md`](implementationPlan-ricostruzioneUnlimitedDB.md). Ex piano trasversale `githubActionBuildGhcr`, numerato 11 il 2026-10-19: va eseguito **prima** del piano 13 ([`implementationPlan-13-ricostruzioneDeploy.md`](implementationPlan-13-ricostruzioneDeploy.md)), che porta il sito in produzione usando questa pipeline.
> Riguarda il branch `new` e il sito di test <https://unlimiteddb-test.mandich.dev> (che con il piano 13 diventa `unlimiteddb.mandich.dev`).
> Regola del progetto (`.agent/rules/main.md`): questo piano è l'unico output, il codice (workflow, compose, script, hook) lo applichi tu a mano.

## Perché

La VM di produzione è uno shape `VM.Standard.E2.1.Micro` (1 GB di RAM, 1/8 di OCPU). Le build dell'immagine (`npm ci` + Vite, `composer install`, stage `icon-builder` con apt, `docker-php-ext-install`) sulla VM durano molti minuti e a volte vanno in `DeadlineExceeded`: `vmstat` mostrava `st` (CPU rubata dall'hypervisor) al 60% e oltre, più swap in thrashing. Compilare lì ad ogni deploy non è sostenibile.

Soluzione: **l'immagine si costruisce su GitHub** (runner molto più veloci, con cache), si pubblica su **ghcr.io** e la VM fa solo `docker pull` + `up`.

```
push su `new`
   │
   ├─ job build   (GitHub)  docker build  →  ghcr.io/rickymandich/swudb:new  +  :sha-<commit>
   │
   └─ job deploy  (GitHub)  SSH sulla VM  →  ~/scripts/deploy-image.sh unlimiteddb new     (script generico)
                                              git pull → hook pre-deploy → docker pull → up --no-build → hook post-deploy
```

## Principio: lo script sulla VM è generico

`~/scripts/` è una cartella comune a tutti i siti della VM: non deve contenere nulla di un sito specifico (nomi, branch, comandi di un framework). Per questo:

- `deploy-image.sh` riceve il **sito** (e il branch) come argomenti. Gli argomenti non li sceglie il client SSH: stanno nel `command="..."` della riga di `authorized_keys` di quella chiave (Step 12.3), quindi ogni sito ha la sua chiave e ogni chiave può lanciare solo il proprio deploy.
- Quello che è specifico del sito (creare `public/build`, `php artisan migrate`, `queue:restart`...) sta **nella repo del sito**, in due hook opzionali: `deploy/pre-deploy.sh` e `deploy/post-deploy.sh` (Step 12.2). Lo script li esegue se esistono.
- Quali immagini scaricare non è scritto nello script: scarica tutte e sole le immagini del `docker-compose.yml` del sito che vengono dal registry (prefisso `ghcr.io/`), così `nginx` e `mariadb` non si aggiornano da soli a ogni deploy.
- Un altro sito si aggiunge senza toccare lo script: i suoi hook nella sua repo, la sua chiave con il suo `command=`, il suo workflow.

## Decisioni e assunzioni (verificale prima di partire)

- **Nome immagine**: `ghcr.io/rickymandich/swudb`. Il remote `origin` è `RickyMandich/SWUDB` e ghcr.io vuole il nome tutto minuscolo. Se il repo cambia nome, va cambiato in tre punti: `env.IMAGE` del workflow, e i due `image:` del compose.
- **Trigger**: solo `push` sul branch `new`, nessun `workflow_dispatch`. I push su `laravel` restano gestiti dalla vecchia action di SWUDB, che non si tocca (la disattiva il piano 13).
- **File del workflow**: `.github/workflows/deploy-unlimiteddb.yml`, diverso da `deploy.yml` della vecchia versione, così non c'è collisione nemmeno quando i branch verranno uniti.
- **Chiave SSH e secret nuovi**: la chiave della vecchia action (`SSH_PRIVATE_KEY`) ha un forced command legato a `deploy.sh SWUDB` e non va toccata. Si crea una chiave nuova, con forced command sullo **script generico** (`deploy-image.sh unlimiteddb new`). Non uso `deploy.sh` perché non l'ho visto e quasi certamente ricostruisce l'immagine sulla VM, cioè il problema da evitare. `SSH_HOST` e `SSH_USER` si riusano.
- **Migrazioni automatiche**: l'hook `deploy/post-deploy.sh` esegue `php artisan migrate --force` ad ogni deploy. Se non lo vuoi, togli quella riga (Step 12.2).
- **Pacchetto ghcr privato**: di default un pacchetto nuovo può essere privato, quindi la VM deve fare un `docker login ghcr.io` una tantum (Step 12.7).
- **Costo**: su un repo privato le build consumano minuti di GitHub Actions (2000/mese nel piano gratuito); una build con cache dovrebbe stare sotto i 10 minuti.
- `docker-compose.dev.yml` non va toccato da questo piano: lo sviluppo locale continua a costruire in locale. L'immagine unica per `app` e `worker` è già applicata in dev (`image: unlimiteddb:dev`, `build: .` solo su `app`); lo Step 12.4 fa lo stesso in produzione con l'immagine di ghcr.io.
- Non rilanciare `finish-unlimiteddb.sh`: fa la build sulla VM e al passo 10 creerebbe una chiave con forced command `deploy.sh`. Di quello script restano utili solo due cose, entrambe già coperte qui sotto: restart di Traefik (Step 12.8) e import SQL opzionale (a mano).

## Step 12.0 — Pulizia sulla VM

Prima di iniziare, sulla VM:

```bash
# ferma eventuali build rimaste appese (Ctrl+C nelle shell dove giravano) e controlla che non ce ne siano
pgrep -af "docker.*build" || echo "nessuna build in corso"

# stato del disco (era al 79%)
docker system df
```

La pulizia della cache di build si fa **dopo** il primo deploy riuscito (Step 12.8), per non buttare layer utili se qualcosa va storto.

## Step 12.1 — Script di deploy generico sulla VM

Crea `~/scripts/deploy-image.sh` sulla VM (con `vim ~/scripts/deploy-image.sh`) e rendilo eseguibile con `chmod +x ~/scripts/deploy-image.sh`:

```bash
#!/bin/bash
# deploy-image.sh — deploy di un sito Docker Compose partendo dalle immagini pubblicate su un registry.
# Uso: deploy-image.sh <sito> [branch]
#   <sito>   nome della cartella in $SITES_DIR (es. quella creata da new-site.sh)
#   [branch] branch da seguire; se manca, quello già in uso nel checkout del sito
# Lanciato da GitHub Actions via SSH: gli argomenti stanno nel forced command della chiave
# (~/.ssh/authorized_keys), non li sceglie il client.
# Specifico del sito = hook opzionali nella sua repo: deploy/pre-deploy.sh e deploy/post-deploy.sh.
set -euo pipefail

SITES_DIR="${SITES_DIR:-/home/ubuntu/sites}"
REGISTRY_PREFIX="${REGISTRY_PREFIX:-ghcr.io/}"

SITE="${1:-}"
BRANCH="${2:-}"

if [[ -z "$SITE" ]]; then
    echo "Uso: $0 <sito> [branch]" >&2
    exit 2
fi
if [[ ! "$SITE" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || [[ -n "$BRANCH" && ! "$BRANCH" =~ ^[A-Za-z0-9][A-Za-z0-9._/-]*$ ]]; then
    echo "Nome del sito o del branch non valido." >&2
    exit 2
fi

SITE_DIR="$SITES_DIR/$SITE"
if [[ ! -d "$SITE_DIR/.git" || ! -f "$SITE_DIR/docker-compose.yml" ]]; then
    echo "$SITE_DIR non è un sito con repo git e docker-compose.yml." >&2
    exit 1
fi

# un solo deploy alla volta per sito
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

if [[ -z "$BRANCH" ]]; then
    BRANCH="$(git symbolic-ref --short -q HEAD || true)"
    [[ -n "$BRANCH" ]] || { echo "Checkout senza branch (detached HEAD): passa il branch come secondo argomento." >&2; exit 1; }
fi

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

# Hook specifici del sito (opzionali), eseguiti nella cartella del sito. Sono codice della repo:
# vanno trattati con la stessa cura del workflow e del compose.
export SITE SITE_DIR
run_hook() {
    local hook="$SITE_DIR/deploy/$1.sh"
    if [[ -f "$hook" ]]; then
        echo "== hook $1"
        bash "$hook"
    fi
}

run_hook pre-deploy

# Niente build sulla VM: si scaricano solo le immagini che vengono dal registry (non nginx, mariadb...).
# Se il pull fallisce il deploy si ferma (set -e) invece di ripiegare su una build lentissima.
mapfile -t IMAGES < <(docker compose config --images | sort -u | grep "^${REGISTRY_PREFIX}" || true)
if [[ ${#IMAGES[@]} -eq 0 ]]; then
    echo "Errore: il compose di $SITE non usa nessuna immagine ${REGISTRY_PREFIX}*." >&2
    exit 1
fi
for image in "${IMAGES[@]}"; do
    docker pull "$image"
done

docker compose up -d --no-build --remove-orphans --wait --wait-timeout 180

run_hook post-deploy

docker image prune -f
echo "Deploy di $SITE completato ($(git rev-parse --short HEAD), branch $BRANCH)."
```

Note:
- `git pull` aggiorna anche i file che il compose monta dal checkout: `docker/nginx/default.conf`, `docker/mysql/init.sql`, `docker/supervisor/worker.conf` e `public/`. Per questo il deploy fa il pull oltre al `docker pull`.
- Lo script non contiene nessun nome di sito, di servizio o di comando Laravel: se ne trovi uno, è un errore del piano.
- Gli hook girano con la cartella del sito come directory corrente e vedono le variabili `SITE` e `SITE_DIR`.
- Controlla la sintassi prima di usarlo: `bash -n ~/scripts/deploy-image.sh`.

## Step 12.2 — Hook del sito nella repo

Il comportamento specifico di unlimiteddb sta nella repo, nella cartella `deploy/` (da creare). Sono i comandi che nella prima versione del piano stavano dentro lo script.

#### 12.2.1 — `deploy/pre-deploy.sh`
```bash
#!/bin/bash
# Eseguito dalla VM dopo il git pull e prima del pull dell'immagine, nella cartella del sito.
set -euo pipefail

# nginx monta ./public in sola lettura e sopra ci monta il volume build_assets in public/build:
# la cartella (gitignored) deve esistere sull'host, altrimenti Docker non può crearla ("read-only file system")
mkdir -p public/build
```

#### 12.2.2 — `deploy/post-deploy.sh`
```bash
#!/bin/bash
# Eseguito dalla VM dopo "docker compose up", nella cartella del sito.
set -euo pipefail

docker compose exec -T app php artisan migrate --force
# fa ricaricare il codice ai processi queue:work del worker (supervisord li rilancia)
docker compose exec -T app php artisan queue:restart
# docker/nginx/default.conf è montato dal checkout: il git pull lo cambia ma nginx non lo rilegge da solo
docker compose exec -T nginx nginx -s reload
```

Se non vuoi le migrazioni automatiche, togli la prima riga di comando di `post-deploy.sh`: lo script generico non cambia.

## Step 12.3 — Chiave SSH dedicata e secret su GitHub

Sulla VM (le prime due variabili sono gli unici punti che cambiano da sito a sito):

```bash
SITE=unlimiteddb
BRANCH=new

cp ~/.ssh/authorized_keys ~/.ssh/authorized_keys.bak

# crea la chiave solo se non esiste già
[[ -f ~/.ssh/deploy_$SITE ]] || ssh-keygen -t ed25519 -f ~/.ssh/deploy_$SITE -C "github-actions-deploy-$SITE" -N ""

# toglie una eventuale riga già presente per questo sito (es. creata da finish-unlimiteddb.sh con forced command su deploy.sh)
sed -i "/github-actions-deploy-$SITE/d" ~/.ssh/authorized_keys

# forced command: questa chiave può solo lanciare deploy-image.sh con questi argomenti
echo "command=\"/home/ubuntu/scripts/deploy-image.sh $SITE $BRANCH\",no-port-forwarding,no-X11-forwarding,no-agent-forwarding $(cat ~/.ssh/deploy_$SITE.pub)" >> ~/.ssh/authorized_keys

# stampa la chiave privata da incollare su GitHub
cat ~/.ssh/deploy_$SITE
```

Su GitHub → repo `SWUDB` → Settings → Secrets and variables → Actions:
- **nuovo secret** `SSH_PRIVATE_KEY_UNLIMITEDDB` = contenuto completo della chiave, comprese le righe `BEGIN`/`END`;
- `SSH_HOST` e `SSH_USER` esistono già (li usa la action di SWUDB): non toccarli;
- `SSH_PRIVATE_KEY` **non va sovrascritto**.

## Step 12.4 — `docker-compose.yml`

Il servizio `app` mantiene `build: .` (per build manuali e sviluppo) ma aggiunge `image:`; il `worker` usa solo `image:` e quindi la stessa immagine. Oggi entrambi usano `unlimiteddb:prod`.

Servizio `app`:

```diff
 services:
   app:
     build: .
-    image: unlimiteddb:prod
+    image: ghcr.io/rickymandich/swudb:new
     container_name: unlimiteddb_app
```

Servizio `worker`:

```diff
   worker:
-    image: unlimiteddb:prod
+    image: ghcr.io/rickymandich/swudb:new
     container_name: unlimiteddb_worker
```

Con `up --no-build` (Step 12.1) il compose non costruisce mai nulla: usa l'immagine scaricata. Il prefisso `ghcr.io/` è anche quello che lo script usa per capire cosa scaricare. Per costruire a mano in locale resta `docker compose build app`.

## Step 12.5 — Workflow GitHub Actions

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
          script: /home/ubuntu/scripts/deploy-image.sh unlimiteddb new
          command_timeout: 15m
```

Note:
- `platforms: linux/amd64`: la VM è x86_64.
- `provenance: false` evita che ghcr mostri anche un manifest "unknown/unknown" accanto all'immagine.
- Il tag `:new` è quello che usa il compose; `:sha-<commit>` serve per il rollback (Step 12.9).
- Il job `deploy` parte solo se `build` riesce.
- Con il forced command la riga `script:` viene ignorata dal server (parte sempre `deploy-image.sh unlimiteddb new`, come scritto in `authorized_keys`): resta per chiarezza, tienila identica al forced command.

## Step 12.6 — Commit e push su `new`

Da Windows, nella cartella del progetto:

```bash
git status
git add .github/workflows/deploy-unlimiteddb.yml deploy/pre-deploy.sh deploy/post-deploy.sh docker-compose.yml implementationPlan-11-githubActionBuildGhcr.md todo.md README.md
git commit -m "CI: build immagine su GitHub Actions (ghcr.io) e deploy con pull sulla VM"
git push origin new
```

Prima di pushare controlla sulla VM che il checkout sia pulito, altrimenti il primo deploy si ferma con l'errore dello script:

```bash
git -C ~/sites/unlimiteddb status --short --untracked-files=no
```

Se elenca file modificati e sono gli stessi che hai già in repo (sincronizzati a mano), buttali con `git -C ~/sites/unlimiteddb checkout -- .` **solo dopo** aver verificato con `git diff` che non ci sia niente da salvare.

## Step 12.7 — Primo run e accesso a ghcr.io dalla VM

Il push fa partire la action (tab Actions su GitHub). Il job `build` senza cache impiega qualche minuto; i successivi molto meno.

Al primo giro il job `deploy` può fallire con `pull access denied` o `unauthorized`, se il pacchetto è privato e la VM non è loggata. Due strade:

- **Pacchetto pubblico** (solo se il codice nell'immagine può essere pubblico, cioè se il repo è pubblico): GitHub → tuo profilo → Packages → `swudb` → Package settings → Change visibility → Public.
- **Pacchetto privato** (consigliato se il repo è privato): crea un personal access token *classic* con solo lo scope `read:packages`, poi sulla VM:

  ```bash
  echo "<TOKEN>" | docker login ghcr.io -u RickyMandich --password-stdin
  ```

  Le credenziali restano in `~/.docker/config.json` dell'utente `ubuntu`, lo stesso che esegue il deploy.

Poi su GitHub: Actions → il run fallito → **Re-run failed jobs** (non serve rifare la build).

## Step 12.8 — Verifica post-deploy

Sulla VM:

```bash
cd ~/sites/unlimiteddb
docker compose ps                                   # app, nginx, db, worker: tutti Up
docker compose exec -T worker sh -c "ps | grep '[q]ueue:work'"   # tanti processi quanti ne ha numprocs
curl -sI https://unlimiteddb-test.mandich.dev | head -1          # 200
curl -sI https://unlimiteddb-test.mandich.dev/favicon.ico | head -1   # 200 (icone, Step 13.6.5 del piano 13)
```

(Dopo il piano 13 l'host è `unlimiteddb.mandich.dev`.) Controlla anche un file di `/build/assets/...` dal browser (CSS/JS devono rispondere 200, non 404). Se compare un 504 su un sito appena creato, è il noto problema del provider Docker di Traefik: `docker restart traefik`.

Nel log del run su GitHub (job `deploy`) devono comparire le righe `== hook pre-deploy`, il `docker pull` e `== hook post-deploy`; se un hook manca, controlla che `deploy/` sia stata pushata.

A deploy riuscito, libera il disco dalle build fallite o appese:

```bash
docker builder prune -af
docker system df
```

Poi verifica che la action di SWUDB continui a funzionare: un push su `laravel` deve far partire la vecchia action e **non** quella nuova.

## Step 12.9 — Rollback

Ogni build pubblica anche il tag `:sha-<commit>`. Per tornare a una versione precedente, sulla VM:

```bash
cd ~/sites/unlimiteddb
docker pull ghcr.io/rickymandich/swudb:sha-<COMMIT_COMPLETO>
docker tag  ghcr.io/rickymandich/swudb:sha-<COMMIT_COMPLETO> ghcr.io/rickymandich/swudb:new
docker compose up -d --no-build
```

Il prossimo push su `new` sovrascrive di nuovo `:new` con la build più recente. Le migrazioni già eseguite non si annullano da sole: se la versione precedente non regge lo schema attuale serve un `migrate:rollback` mirato.

## Step 12.10 — Documentazione

Quando gli step 12.0–12.9 sono fatti:

1. **README.md**, sezione "Deploy": sostituisci la nota "Non ancora implementato" sulla pipeline di build con la descrizione reale, ad esempio:

   > Il branch `new` si deploya da solo: un push su `new` avvia `.github/workflows/deploy-unlimiteddb.yml`, che costruisce l'immagine su GitHub, la pubblica su `ghcr.io/rickymandich/swudb` (tag `new` e `sha-<commit>`) e poi, via SSH, lancia sulla VM lo script generico `~/scripts/deploy-image.sh unlimiteddb new` (`git pull`, `docker pull` delle immagini `ghcr.io/*` del compose, `up --no-build`). Le parti specifiche del sito stanno nella repo, negli hook `deploy/pre-deploy.sh` e `deploy/post-deploy.sh` (`migrate --force`, `queue:restart`). La VM non compila mai l'immagine. Secret richiesti: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY_UNLIMITEDDB`. Rollback con il tag `sha-<commit>`.

   Aggiungi `deploy/` alla struttura delle cartelle del README.
2. **todo.md**: spunta gli step della sezione "11 — Build su GitHub Actions".
3. Rinomina questo file in `implementationPlan-V-11-githubActionBuildGhcr.md`.

## Rapporto con il piano 13 (messa in produzione)

Il piano 13 non rigenera più il sito con `new-site.sh`: porta in produzione **questo stesso sito** (cartella `~/sites/unlimiteddb`, container `unlimiteddb_*`, database e immagini già presenti) cambiandone il dominio. Il compose, il workflow e gli hook di questo piano restano quindi validi senza modifiche, tranne la regola `Host(...)` di Traefik, che il piano 13 (Step 13.1) rende parametrica con `SITE_HOST` letto dal `.env` sulla VM: il cambio da `unlimiteddb-test.mandich.dev` a `unlimiteddb.mandich.dev` è poi una modifica del `.env`, senza nuovo deploy. Il piano 13 sposta la vecchia produzione su `unlimiteddb-old.mandich.dev`. I template di `new-site.sh` non vanno aggiornati.
