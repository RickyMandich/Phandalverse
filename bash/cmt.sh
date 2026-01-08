#!/bin/bash

# Variabile per il messaggio personalizzato
messaggio=""

# Guarda se ci sono opzioni
while getopts "m:h" opt; do
    case $opt in
        m)
            messaggio=" - $OPTARG"
            echo "Messaggio personalizzato: $messaggio"
            ;;
        h)
            echo "Uso: $0 [-m messaggio]"
            echo "  -m  (messaggio): aggiungi un messaggio personale al commit oltre a quello di default"
            exit 0
            ;;
        \?)
            echo "Opzione non valida: -$OPTARG" >&2
            echo "Uso: $0 [-m messaggio]"
            echo "  -m  (messaggio): aggiungi un messaggio personale al commit oltre a quello di default"
            exit 1
            ;;
    esac
done

# Aggiungi tutti i file al commit
git add .
# Mostra lo stato dei file
git status

# Leggi la versione dell'app dal file .env
if [ -f .env ]; then
    # Leggi le variabili di versione dal file .env
    APP_VERSION_TYPE=$(grep "^APP_VERSION_TYPE=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_PRIMARY=$(grep "^APP_VERSION_PRIMARY=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_SECONDARY=$(grep "^APP_VERSION_SECONDARY=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_TERTIARY=$(grep "^APP_VERSION_TERTIARY=" .env | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')

    # Componi la versione
    if [ -n "$APP_VERSION_PRIMARY" ] && [ -n "$APP_VERSION_SECONDARY" ] && [ -n "$APP_VERSION_TERTIARY" ]; then
        if [ -n "$APP_VERSION_TYPE" ]; then
            APP_VERSION="$APP_VERSION_TYPE"
        fi
        APP_VERSION="$APP_VERSION $APP_VERSION_PRIMARY.$APP_VERSION_SECONDARY.$APP_VERSION_TERTIARY"
    else
        APP_VERSION="unknown"
    fi
else
    APP_VERSION="unknown"
fi

# Debug: mostra la versione trovata
echo "Versione trovata: '$APP_VERSION'"

# Crea il nome del commit con data, ora e versione
nomeCommit=$(date "+%Y %m %d %H:%M")
nomeCommit="aggiornamento $nomeCommit [$APP_VERSION]$messaggio"
echo "Messaggio commit: $nomeCommit"
git commit -am "$nomeCommit"

# Esegui il push sul repository remoto
git push

sleep 1
# clear