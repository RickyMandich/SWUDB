# Aggiungi tutti i file al commit
git add .
git status

# Leggi la versione dell'app dal file .env
if [ -f .env ]; then
    APP_VERSION=$(grep "^APP_VERSION=" .env | cut -d '=' -f2 | tr -d '"')
else
    APP_VERSION="unknown"
fi

# Crea il nome del commit con data, ora e versione
nomeCommit=$(date "+%Y %m %d %H:%M")
nomeCommit="aggiornamento $nomeCommit [v$APP_VERSION]"
git commit -m "$nomeCommit"

# Esegui il push sul repository remoto
git push -f

sleep 1
clear