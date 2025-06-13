#!/bin/bash

# Ottieni il percorso completo della directory in cui si trova lo script corrente
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# Ottieni il percorso della directory del progetto (parent directory dello script)
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Funzione per incrementare APP_VERSION_TERTIARY nel file .env
increment_version() {
    local env_file="$PROJECT_DIR/.env"

    if [ ! -f "$env_file" ]; then
        echo "Errore: File .env non trovato in $env_file"
        exit 1
    fi

    # Leggi il valore corrente di APP_VERSION_TERTIARY
    current_version=$(grep "^APP_VERSION_TERTIARY=" "$env_file" | cut -d'=' -f2)

    if [ -z "$current_version" ]; then
        echo "Errore: APP_VERSION_TERTIARY non trovato nel file .env"
        exit 1
    fi

    # Incrementa il valore
    new_version=$((current_version + 1))

    # Aggiorna il file .env
    sed -i "s/^APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=$new_version/" "$env_file"

    echo "APP_VERSION_TERTIARY incrementato da $current_version a $new_version"
}

# Incrementa la versione prima di fare commit e deploy
increment_version

# Esegui gli script usando il percorso completo
"$SCRIPT_DIR/cmt.sh"
"$SCRIPT_DIR/onlyFtpOfLastCmt.sh"