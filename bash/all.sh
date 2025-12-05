#!/bin/bash

# Ottieni il percorso completo della directory in cui si trova lo script corrente
SCRIPT_DIR="$(dirname "$(readlink -f "$0")")"

# Ottieni il percorso della directory del progetto (parent directory dello script)
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Variabili per le opzioni
VERSION_MAJOR=false
VERSION_PATCH=false
COMMIT_MESSAGE=""

# Parsing delle opzioni
while getopts "vpm:h" opt; do
    case $opt in
        v)
            VERSION_MAJOR=true
            ;;
        p)
            VERSION_PATCH=true
            ;;
        m)
            COMMIT_MESSAGE="$OPTARG"
            echo "Messaggio personalizzato: $COMMIT_MESSAGE"
            ;;
        h)
            echo "Uso: $0 [-v] [-p] [-m messaggio]"
            echo "  -v  (versione): Incrementa APP_VERSION_PRIMARY e resetta APP_VERSION_SECONDARY a 0"
            echo "  -p  (patch): Incrementa APP_VERSION_SECONDARY"
            echo "  -m  (messaggio): Aggiungi un messaggio personale al commit"
            echo "Le opzioni -v e -p non possono essere usate insieme"
            exit 0
            ;;
        \?)
            echo "Opzione non valida: -$OPTARG" >&2
            echo "Uso: $0 [-v] [-p] [-m messaggio]"
            echo "  -v  (versione): Incrementa APP_VERSION_PRIMARY e resetta APP_VERSION_SECONDARY a 0"
            echo "  -p  (patch): Incrementa APP_VERSION_SECONDARY"
            echo "  -m  (messaggio): Aggiungi un messaggio personale al commit"
            echo "Le opzioni -v e -p non possono essere usate insieme"
            exit 1
            ;;
    esac
done

# Controlla che non siano state specificate entrambe le opzioni
if [ "$VERSION_MAJOR" = true ] && [ "$VERSION_PATCH" = true ]; then
    echo "Errore: Le opzioni -v e -p non possono essere usate insieme"
    exit 1
fi

# Funzione per incrementare le versioni nel file .env
increment_version() {
    local env_file="$PROJECT_DIR/.env"

    if [ ! -f "$env_file" ]; then
        echo "Errore: File .env non trovato in $env_file"
        exit 1
    fi

    if [ "$VERSION_MAJOR" = true ]; then
        # Incrementa APP_VERSION_PRIMARY e resetta APP_VERSION_SECONDARY a 0
        current_primary=$(grep "^APP_VERSION_PRIMARY=" "$env_file" | cut -d'=' -f2)

        if [ -z "$current_primary" ]; then
            echo "Errore: APP_VERSION_PRIMARY non trovato nel file .env"
            exit 1
        fi

        new_primary=$((current_primary + 1))

        # Aggiorna il file .env
        sed -i "s/^APP_VERSION_PRIMARY=.*/APP_VERSION_PRIMARY=$new_primary/" "$env_file"
        sed -i "s/^APP_VERSION_SECONDARY=.*/APP_VERSION_SECONDARY=0/" "$env_file"
        sed -i "s/^APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=0/" "$env_file"

        echo "APP_VERSION_PRIMARY incrementato da $current_primary a $new_primary"
        echo "APP_VERSION_SECONDARY resettato a 0"
        echo "APP_VERSION_TERTIARY resettato a 0"

    elif [ "$VERSION_PATCH" = true ]; then
        # Incrementa APP_VERSION_SECONDARY
        current_secondary=$(grep "^APP_VERSION_SECONDARY=" "$env_file" | cut -d'=' -f2)

        if [ -z "$current_secondary" ]; then
            echo "Errore: APP_VERSION_SECONDARY non trovato nel file .env"
            exit 1
        fi

        new_secondary=$((current_secondary + 1))

        # Aggiorna il file .env
        sed -i "s/^APP_VERSION_SECONDARY=.*/APP_VERSION_SECONDARY=$new_secondary/" "$env_file"
        sed -i "s/^APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=0/" "$env_file"

        echo "APP_VERSION_SECONDARY incrementato da $current_secondary a $new_secondary"
        echo "APP_VERSION_TERTIARY resettato a 0"

    else
        # Comportamento predefinito: incrementa solo APP_VERSION_TERTIARY
        current_tertiary=$(grep "^APP_VERSION_TERTIARY=" "$env_file" | cut -d'=' -f2)

        if [ -z "$current_tertiary" ]; then
            echo "Errore: APP_VERSION_TERTIARY non trovato nel file .env"
            exit 1
        fi

        new_tertiary=$((current_tertiary + 1))

        # Aggiorna il file .env
        sed -i "s/^APP_VERSION_TERTIARY=.*/APP_VERSION_TERTIARY=$new_tertiary/" "$env_file"

        echo "APP_VERSION_TERTIARY incrementato da $current_tertiary a $new_tertiary"
    fi
}

# Incrementa la versione prima di fare commit e deploy
increment_version

# Esegui gli script usando il percorso completo
# Passa il messaggio personalizzato a cmt.sh se presente
if [ -n "$COMMIT_MESSAGE" ]; then
    "$SCRIPT_DIR/cmt.sh" -m "$COMMIT_MESSAGE"
else
    "$SCRIPT_DIR/cmt.sh"
fi
"$SCRIPT_DIR/onlyFtpOfLastCmt.sh"