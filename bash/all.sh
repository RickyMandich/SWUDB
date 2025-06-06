#!/bin/bash

# Ottieni il percorso completo della directory in cui si trova lo script corrente
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# Esegui gli script usando il percorso completo
"$SCRIPT_DIR/cmt.sh"
"$SCRIPT_DIR/onlyFtpOfLastCmt.sh"