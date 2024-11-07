#!/bin/bash

# Funzione ricorsiva per caricare i file su FTP
function uploadFiles() {
    local dir="$1"
    
    # Entra nella directory corrente
    cd "$dir"
    
    lftp -u swudb,Minecraft35? ftp.swudb.altervista.org <<EOF
    set ftp:ssl-allow no
    mirror -R --delete --exclude '.git' . /
EOF

    # Cerca le sottocartelle
    for subdir in $(find . -mindepth 1 -maxdepth 1 -type d | grep -v ".git"); do
        uploadFiles "$subdir"
        # Torna alla directory precedente dopo aver processato la sottocartella
        cd ..
    done
}

# Aggiungi tutti i file al commit
git add .
git status

# Crea il nome del commit con data e ora
nomeCommit=$(date "+%Y %m %d %H:%M")
nomeCommit="aggiornamento $nomeCommit"
git commit -m "$nomeCommit"
# clear

# Esegui il push sul repository remoto
git push

# Carica i file in modo ricorsivo
uploadFiles "."

# sleep 5
# clear
