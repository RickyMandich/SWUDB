#!/bin/bash
# Eseguito dalla VM dopo il git pull e prima del pull dell'immagine, nella cartella del sito.
set -euo pipefail

# nginx monta ./public in sola lettura e sopra ci monta il volume build_assets in public/build:
# la cartella (gitignored) deve esistere sull'host, altrimenti Docker non può crearla ("read-only file system")
mkdir -p public/build
