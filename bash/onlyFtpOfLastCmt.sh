# Leggi le credenziali FTP dal file .env
if [ -f .env ]; then
    FTP_SERVER=$(grep "^FTP_SERVER=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    FTP_USERNAME=$(grep "^FTP_USERNAME=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    FTP_PASSWORD=$(grep "^FTP_PASSWORD=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    FTP_PORT=$(grep "^FTP_PORT=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
else
    echo "Errore: File .env non trovato"
    exit 1
fi

# Funzione per caricare i file su FTP a partire dal commit
function uploadFilesFromCommit() {
    # Itera sui file modificati e carica ciascuno di essi (usando while read per gestire spazi nei nomi)
    git diff-tree --no-commit-id --name-only -r HEAD | while IFS= read -r file; do
        # Salta righe vuote
        [ -z "$file" ] && continue

        # Salta file dentro cartelle escluse
        if [[ "$file" == node_modules/* || "$file" == vendor/* || "$file" == */.obsidian/* ]]; then
            echo "Skipping $file (excluded directory)"
            continue
        fi

        # Costruisci il percorso FTP per il file
        local relativePath=$(dirname "$file")
        local fileName=$(basename "$file")
        local ftpRequest="ftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_SERVER}:${FTP_PORT}/$relativePath/$fileName"

        # Esegui il comando curl per caricare il file
        local curlCommand="curl -T \"$file\" \"$ftpRequest\" --ftp-pasv --ftp-create-dirs"
        echo -e "$curlCommand"

        # Esegui curl e cattura l'output e il codice di uscita
        local curlOutput
        curlOutput=$(eval "$curlCommand" 2>&1)
        local curlExitCode=$?

        # Controlla se curl ha restituito un errore
        if [ $curlExitCode -ne 0 ]; then
            # Se l'errore è "Failed to open/read local data", rimuovi il file dal server
            if [[ "$curlOutput" == *"Failed to open/read local data"* ]]; then
                echo "Errore durante il caricamento di $file. Rimozione dal server in corso..."

                # Comando per eliminare il file dal server FTP
                local deleteCommand="curl -Q \"DELE $relativePath/$fileName\" \"ftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_SERVER}:${FTP_PORT}/\" --ftp-pasv"
                echo -e "$deleteCommand"
                eval "$deleteCommand"

                echo "File $relativePath/$fileName rimosso dal server."
            else
                # Per altri tipi di errori, mostra l'output di curl
                echo "Errore durante il caricamento di $file:"
                echo "$curlOutput"
            fi
        else
            echo "$relativePath/$fileName caricato con successo."
        fi
    done
}

# Carica i file presenti nell'ultimo commit
uploadFilesFromCommit

sleep 1