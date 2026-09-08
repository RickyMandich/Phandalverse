#!/bin/bash

# Variabile per il messaggio personalizzato
messaggio=""
NO_ADD=false

# Guarda se ci sono opzioni
while getopts "nm:h" opt; do
    case $opt in
        n)
            NO_ADD=true
            ;;
        m)
            messaggio=" - $OPTARG"
            echo "Messaggio personalizzato: $messaggio"
            ;;
        h)
            echo "Uso: $0 [-n] [-m messaggio]"
            echo "  -n  (no-add): non esegue 'git add .', committa solo i file già in staging"
            echo "  -m  (messaggio): aggiungi un messaggio personale al commit oltre a quello di default"
            exit 0
            ;;
        \?)
            echo "Opzione non valida: -$OPTARG" >&2
            echo "Uso: $0 [-n] [-m messaggio]"
            echo "  -n  (no-add): non esegue 'git add .', committa solo i file già in staging"
            echo "  -m  (messaggio): aggiungi un messaggio personale al commit oltre a quello di default"
            exit 1
            ;;
    esac
done

# Aggiungi tutti i file al commit, a meno che non sia stata passata -n
if [ "$NO_ADD" = true ]; then
    echo "Opzione -n attiva: salto 'git add .', verranno committati solo i file già in staging"
else
    git add .
fi
# Mostra lo stato dei file
git status

# Leggi la versione dell'app dal file .env-overrides (tracciato in Git)
if [ -f .env-overrides ]; then
    # Leggi le variabili di versione dal file .env-overrides
    APP_VERSION_TYPE=$(grep "^APP_VERSION_TYPE=" .env-overrides | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_PRIMARY=$(grep "^APP_VERSION_PRIMARY=" .env-overrides | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_SECONDARY=$(grep "^APP_VERSION_SECONDARY=" .env-overrides | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')
    APP_VERSION_TERTIARY=$(grep "^APP_VERSION_TERTIARY=" .env-overrides | cut -d '=' -f2- | sed 's/^"//' | sed 's/"$//')

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
# Esegui il commit: con -n committa solo ciò che è già in staging,
# altrimenti (comportamento originale) usa -a per includere anche
# tutte le modifiche ai file già tracciati.
if [ "$NO_ADD" = true ]; then
    git commit -m "$nomeCommit"
else
    git commit -am "$nomeCommit"
fi

# Esegui il push sul repository remoto
git push

sleep 1
# clear