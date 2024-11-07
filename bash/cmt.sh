#!/bin/bash

# Funzione ricorsiva per caricare i file su FTP mantenendo il percorso relativo
function uploadFiles() {
    local dir="$1"
    local relativePath="$2"
    
    # Entra nella directory corrente
    cd "$dir"
    
    # Cerca le sottocartelle
    for subdir in $(find . -mindepth 1 -maxdepth 1 -type d | grep -v ".git"); do
        # Calcola il nuovo percorso relativo per la sottocartella
        local newRelativePath="$relativePath/${subdir#./}"
        uploadFiles "$subdir" "$newRelativePath"
        # Torna alla directory precedente dopo aver processato la sottocartella
        cd ..
    done
    
    # Carica i file nella directory corrente mantenendo il percorso relativo
    for file in *; do
        if [ -f "$file" ]; then
            ftpRequest="ftp://swudb:Minecraft35%3F@ftp.swudb.altervista.org:21$relativePath/$file"
            echo $ftpRequest
            curl -T "$file" "$ftpRequest" --ftp-pasv --ftp-create-dirs
        fi
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

# Carica i file in modo ricorsivo a partire dalla radice con percorso relativo vuoto
uploadFiles "." ""

# sleep 5
# clear
