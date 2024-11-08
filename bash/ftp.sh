# Ottieni l'elenco dei file modificati nell'ultimo commit
changedFiles=$(ls)

# Itera sui file modificati e carica ciascuno di essi
for file in $changedFiles; do
    # Costruisci il percorso FTP per il file
    local relativePath=$(dirname "$file")
    local fileName=$(basename "$file")
    local ftpRequest="ftp://swudb:Minecraft35%3F@ftp.swudb.altervista.org:21/$relativePath/$fileName"

    # Esegui il comando curl per caricare il file
    local curlCommand="curl -T \"$file\" \"$ftpRequest\" --ftp-pasv --ftp-create-dirs"
    echo -e "$curlCommand"
    eval "$curlCommand"
    echo "$relativePath/$fileName caricato con successo."
done