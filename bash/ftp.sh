# bash/ftp.sh

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

# Itera sui file e carica ciascuno di essi (usando while read per gestire spazi nei nomi)
find . -type f -not -path './.git/*' -not -path './node_modules/*' -not -path './vendor/*' | while IFS= read -r file; do
    # Salta righe vuote
    [ -z "$file" ] && continue

    # Costruisci il percorso FTP per il file
    relativePath=$(dirname "$file" | sed 's/^\.\///')
    fileName=$(basename "$file")
    ftpRequest="ftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_SERVER}:${FTP_PORT}/$relativePath/$fileName"

    # Esegui il comando curl per caricare il file
    curlCommand="curl -T \"$file\" \"$ftpRequest\" --ftp-pasv --ftp-create-dirs"
    echo -e "$curlCommand"
    eval "$curlCommand"
    echo "$relativePath/$fileName caricato con successo."
done