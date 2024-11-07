#!/bin/bash

# Aggiungi tutti i file al commit
git add .
git status

# Crea il nome del commit con data e ora
nomeCommit=$(date "+%Y %m %d %H:%M")
nomeCommit="aggiornamento $nomeCommit"
git commit -m "$nomeCommit"
clear

# Esegui il push sul repository remoto
git push

# Carica solo i file diversi dal server FTP
ftp -n ftp.swudb.altervista.org <<EOF
user swudb "Minecraft35?"
binary
cd public_html
ls -l
mget -d .
mput -R --diff *
quit
EOF

sleep 5
clear
